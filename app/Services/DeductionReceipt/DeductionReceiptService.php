<?php

namespace App\Services\DeductionReceipt;

use App\Models\DeductionReceipt;
use App\Models\DeductionRecord;
use App\Models\SalaryPeriod;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Bukti Potongan (CLAUDE.md STEP 14, §19) — satu dokumen per jenis potongan
 * per pegawai per periode (1:1 dengan deduction_records, bukan gabungan
 * semua potongan sekaligus seperti Slip Gaji). Hanya bisa dibuat untuk
 * periode FINAL/ARSIP (§17), pola sama persis dengan PayslipService.
 */
class DeductionReceiptService
{
    private const ROMAWI_BULAN = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function bisaGenerate(SalaryPeriod $period): bool
    {
        return in_array($period->status, [SalaryPeriod::STATUS_FINAL, SalaryPeriod::STATUS_ARSIP], true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<DeductionRecord>
     */
    public function recordsForPeriod(SalaryPeriod $period)
    {
        return DeductionRecord::whereHas('salaryRecord', fn ($q) => $q->where('salary_period_id', $period->id));
    }

    public function generate(DeductionRecord $record, User $user, bool $isRevisi = false): DeductionReceipt
    {
        $record->loadMissing(['salaryRecord.salaryPeriod', 'salaryRecord.employee', 'deductionType']);
        $period = $record->salaryRecord->salaryPeriod;

        if (! $this->bisaGenerate($period)) {
            throw new \RuntimeException('Bukti potongan hanya bisa dibuat untuk periode berstatus FINAL atau ARSIP.');
        }

        return DB::transaction(function () use ($record, $period, $user, $isRevisi) {
            $urutan = DeductionReceipt::whereHas(
                'deductionRecord.salaryRecord.salaryPeriod',
                fn ($q) => $q->where('bulan', $period->bulan)->where('tahun', $period->tahun)
            )->lockForUpdate()->count() + 1;

            $nomorDokumen = sprintf('%s/%s/%d/%04d', Settings::get('prefix_nomor_potongan'), self::ROMAWI_BULAN[$period->bulan], $period->tahun, $urutan);

            $salaryRecord = $record->salaryRecord;

            $pdf = Pdf::loadView('pdf.bukti-potongan', [
                'record' => $record,
                'nomorDokumen' => $nomorDokumen,
                'period' => $period,
                'nama' => $salaryRecord->employee?->nama ?? $salaryRecord->nama_snapshot,
                'nip' => $salaryRecord->nip_snapshot,
                'isRevisi' => $isRevisi,
            ])->setPaper('a4');

            $filename = str_replace('/', '-', $nomorDokumen).'.pdf';
            $path = "deduction-receipts/{$record->id}/{$filename}";
            Storage::disk('local')->put($path, $pdf->output());

            $receipt = DeductionReceipt::create([
                'deduction_record_id' => $record->id,
                'nomor_dokumen' => $nomorDokumen,
                'path_file' => $path,
                'is_revisi' => $isRevisi,
                'dibuat_oleh' => $user->id,
            ]);

            AuditLogger::log('Generate Bukti Potongan', "Membuat bukti potongan {$nomorDokumen} ({$record->deductionType->nama}) untuk {$salaryRecord->nama_snapshot} periode {$period->nama_periode}.", [
                'salary_period_id' => $period->id,
                'deduction_record_id' => $record->id,
                'deduction_receipt_id' => $receipt->id,
            ]);

            return $receipt;
        });
    }

    /**
     * Sama seperti PayslipService::generateBatch() — diproses per batch lewat
     * wire:poll, belum ada queue worker aktif di server (lih. PROGRESS.md
     * lanjutan 26).
     *
     * @return int jumlah yang berhasil dibuat pada batch ini
     */
    public function generateBatch(SalaryPeriod $period, User $user, int $batchSize = 20): int
    {
        if (! $this->bisaGenerate($period)) {
            throw new \RuntimeException('Bukti potongan hanya bisa dibuat untuk periode berstatus FINAL atau ARSIP.');
        }

        $records = $this->recordsForPeriod($period)->whereDoesntHave('receipts')->limit($batchSize)->get();

        foreach ($records as $record) {
            $this->generate($record, $user);
        }

        return $records->count();
    }
}
