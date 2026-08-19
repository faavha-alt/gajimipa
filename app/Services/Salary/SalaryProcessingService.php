<?php

namespace App\Services\Salary;

use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Proses Gaji (CLAUDE.md STEP 11, §15): menggabungkan data yang sudah ada di
 * salary_records (dari Import Gaji Pusat, STEP 8) dengan deduction_records
 * (dari Import/Input Potongan, STEP 10) menjadi angka final:
 *
 *   Bersih dari Pusat − Total Potongan Fakultas = Gaji Bersih Final
 *
 * Sengaja berupa aksi EKSPLISIT yang bisa dijalankan berkali-kali (bukan
 * recalculate otomatis tiap ada perubahan potongan) — operator boleh koreksi
 * potongan berulang kali selama DRAFT, baru "Proses Gaji" saat sudah yakin.
 */
class SalaryProcessingService
{
    /**
     * Hitung ulang tanpa menyimpan — dipakai untuk preview sebelum commit.
     *
     * @return array<int,array{salary_record_id:int,nip:string,nama:string,bersih_pusat:float,total_potongan_fakultas_lama:float,total_potongan_fakultas_baru:float,gaji_bersih_final_lama:float,gaji_bersih_final_baru:float,berubah:bool}>
     */
    public function preview(SalaryPeriod $period): array
    {
        return $period->salaryRecords()
            ->with('employee:id,nama')
            ->withSum('deductionRecords as total_potongan_baru', 'nominal')
            ->orderBy('nama_snapshot')
            ->get()
            ->map(function (SalaryRecord $record) {
                $totalPotonganBaru = (float) ($record->total_potongan_baru ?? 0);
                $gajiBersihBaru = (float) $record->bersih_pusat - $totalPotonganBaru;

                return [
                    'salary_record_id' => $record->id,
                    'nip' => $record->nip_snapshot,
                    // Nama diambil dari Master Pegawai (sumber otoritatif), bukan
                    // nama_snapshot dari file Excel — nmpeg sering ada whitespace/
                    // format tidak konsisten (docs/excel-gaji-pusat.md §2).
                    // nama_snapshot tetap tersimpan untuk audit trail, hanya tidak
                    // dipakai sebagai nilai tampilan utama.
                    'nama' => $record->employee?->nama ?? $record->nama_snapshot,
                    'bersih_pusat' => (float) $record->bersih_pusat,
                    'total_potongan_fakultas_lama' => (float) $record->total_potongan_fakultas,
                    'total_potongan_fakultas_baru' => $totalPotonganBaru,
                    'gaji_bersih_final_lama' => (float) $record->gaji_bersih_final,
                    'gaji_bersih_final_baru' => $gajiBersihBaru,
                    'berubah' => round((float) $record->total_potongan_fakultas, 2) !== round($totalPotonganBaru, 2),
                ];
            })
            ->all();
    }

    /**
     * @return array{updated:int}
     */
    public function proses(SalaryPeriod $period, User $user): array
    {
        if ($period->status !== SalaryPeriod::STATUS_DRAFT) {
            throw new \RuntimeException('Proses gaji hanya bisa dilakukan selama periode berstatus DRAFT.');
        }

        if (! $period->salaryRecords()->exists()) {
            throw new \RuntimeException('Belum ada data gaji pusat untuk periode ini — import Gaji Pusat dulu.');
        }

        $updated = 0;

        DB::transaction(function () use ($period, &$updated) {
            $period->salaryRecords()
                ->withSum('deductionRecords as total_potongan_baru', 'nominal')
                ->each(function (SalaryRecord $record) use (&$updated) {
                    $totalPotonganFakultas = (float) ($record->total_potongan_baru ?? 0);

                    $record->update([
                        'total_potongan_fakultas' => $totalPotonganFakultas,
                        'gaji_bersih_final' => (float) $record->bersih_pusat - $totalPotonganFakultas,
                    ]);

                    $updated++;
                });
        });

        AuditLogger::log('Proses Gaji', "Proses gaji periode {$period->nama_periode}: {$updated} pegawai dihitung ulang.", [
            'salary_period_id' => $period->id,
            'jumlah_pegawai' => $updated,
        ]);

        return ['updated' => $updated];
    }
}
