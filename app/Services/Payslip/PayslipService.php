<?php

namespace App\Services\Payslip;

use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Slip Gaji (CLAUDE.md STEP 13, §18). Hanya bisa dibuat untuk periode yang
 * sudah FINAL/ARSIP (§17 — data belum final tidak boleh didokumenkan sbg
 * slip resmi). Nomor dokumen berurutan per bulan/tahun (§19), lintas versi
 * periode (kalau ada revisi) supaya tidak pernah tabrakan dgn constraint
 * unique `payslips.nomor_dokumen`.
 */
class PayslipService
{
    private const ROMAWI_BULAN = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function bisaGenerate(SalaryPeriod $period): bool
    {
        return in_array($period->status, [SalaryPeriod::STATUS_FINAL, SalaryPeriod::STATUS_ARSIP], true);
    }

    public function generate(SalaryRecord $record, User $user, bool $isRevisi = false): Payslip
    {
        $period = $record->salaryPeriod;

        if (! $this->bisaGenerate($period)) {
            throw new \RuntimeException('Slip gaji hanya bisa dibuat untuk periode berstatus FINAL atau ARSIP.');
        }

        return DB::transaction(function () use ($record, $period, $user, $isRevisi) {
            $urutan = Payslip::whereHas(
                'salaryRecord.salaryPeriod',
                fn ($q) => $q->where('bulan', $period->bulan)->where('tahun', $period->tahun)
            )->lockForUpdate()->count() + 1;

            $nomorDokumen = sprintf('%s/%s/%d/%04d', Settings::get('prefix_nomor_slip'), self::ROMAWI_BULAN[$period->bulan], $period->tahun, $urutan);

            $record->loadMissing(['employee.unit', 'components', 'deductionRecords.deductionType']);

            $pdf = Pdf::loadView('pdf.slip-gaji', [
                'record' => $record,
                'nomorDokumen' => $nomorDokumen,
                'period' => $period,
                'nama' => $record->employee?->nama ?? $record->nama_snapshot,
                'penghasilan' => $record->components->where('kategori', SalaryComponent::KATEGORI_PENGHASILAN),
                'potonganPusat' => $record->components->where('kategori', SalaryComponent::KATEGORI_POTONGAN_PUSAT),
                'isRevisi' => $isRevisi,
            ])->setPaper('a4');

            $filename = str_replace('/', '-', $nomorDokumen).'.pdf';
            $path = "payslips/{$record->id}/{$filename}";
            Storage::disk('local')->put($path, $pdf->output());

            $payslip = Payslip::create([
                'salary_record_id' => $record->id,
                'nomor_dokumen' => $nomorDokumen,
                'path_file' => $path,
                'is_revisi' => $isRevisi,
                'dibuat_oleh' => $user->id,
            ]);

            AuditLogger::log('Generate Slip Gaji', "Membuat slip gaji {$nomorDokumen} untuk {$record->nama_snapshot} periode {$period->nama_periode}.", [
                'salary_period_id' => $period->id,
                'salary_record_id' => $record->id,
                'payslip_id' => $payslip->id,
            ]);

            return $payslip;
        });
    }

    /**
     * Generate slip untuk 1 batch pegawai yang belum punya slip di periode ini
     * (dipanggil berulang dari UI lewat wire:poll supaya tidak 1 request raksasa
     * yang berisiko timeout — belum ada queue worker aktif di server, lih.
     * PROGRESS.md lanjutan 26).
     *
     * @return int jumlah yang berhasil dibuat pada batch ini
     */
    public function generateBatch(SalaryPeriod $period, User $user, int $batchSize = 20): int
    {
        if (! $this->bisaGenerate($period)) {
            throw new \RuntimeException('Slip gaji hanya bisa dibuat untuk periode berstatus FINAL atau ARSIP.');
        }

        $records = $period->salaryRecords()->whereDoesntHave('payslips')->limit($batchSize)->get();

        foreach ($records as $record) {
            $this->generate($record, $user);
        }

        return $records->count();
    }
}
