<?php

namespace App\Services\Salary;

use App\Models\DeductionRecord;
use App\Models\SalaryPeriod;

/**
 * Validasi Sebelum Finalisasi (CLAUDE.md §16). Sebagian besar dari 9 poin
 * checklist §16 sebenarnya sudah dijamin struktural oleh skema DB + validasi
 * saat import/input (STEP 8-10): NIP wajib ada & unik per periode (unique
 * constraint salary_period_id+employee_id), potongan wajib punya pegawai (FK
 * NOT NULL), nominal negatif ditolak sejak Import/Input. Service ini adalah
 * lapis pertahanan terakhir sebelum FINAL (defense-in-depth) + satu-satunya
 * pengecekan yang BUKAN sekadar struktural: apakah Proses Gaji (STEP 11)
 * sudah dijalankan dan hasilnya masih sinkron dengan Data Potongan terkini.
 */
class SalaryPeriodValidationService
{
    /**
     * @return array<int,array{label:string,ok:bool,detail:?string}>
     */
    public function checklist(SalaryPeriod $period): array
    {
        $jumlahPegawai = $period->salaryRecords()->count();

        $checks = [];

        $checks[] = [
            'label' => 'Ada data gaji pusat untuk periode ini',
            'ok' => $jumlahPegawai > 0,
            'detail' => $jumlahPegawai > 0 ? "{$jumlahPegawai} pegawai." : 'Belum ada data — import Gaji Pusat dulu.',
        ];

        if ($jumlahPegawai === 0) {
            return $checks;
        }

        $nonaktif = $period->salaryRecords()->whereHas('employee', fn ($q) => $q->where('status_aktif', false))->count();
        $checks[] = [
            'label' => 'Semua pegawai berstatus aktif di Master Pegawai',
            'ok' => $nonaktif === 0,
            'detail' => $nonaktif > 0 ? "{$nonaktif} pegawai sudah nonaktif tapi masih punya data gaji di periode ini." : null,
        ];

        $negatifPusat = $period->salaryRecords()
            ->where(fn ($q) => $q->where('total_penghasilan_kotor', '<', 0)
                ->orWhere('total_potongan_pusat', '<', 0)
                ->orWhere('bersih_pusat', '<', 0))
            ->count();
        $negatifFakultas = DeductionRecord::whereIn('salary_record_id', $period->salaryRecords()->pluck('id'))
            ->where('nominal', '<', 0)
            ->count();
        $checks[] = [
            'label' => 'Tidak ada nominal negatif (penghasilan, potongan pusat, maupun potongan fakultas)',
            'ok' => $negatifPusat === 0 && $negatifFakultas === 0,
            'detail' => ($negatifPusat + $negatifFakultas) > 0 ? ($negatifPusat + $negatifFakultas).' baris bernominal negatif ditemukan.' : null,
        ];

        $belumSinkron = $period->salaryRecords()
            ->withSum('deductionRecords as total_potongan_baru', 'nominal')
            ->get()
            ->filter(fn ($r) => round((float) $r->total_potongan_fakultas, 2) !== round((float) ($r->total_potongan_baru ?? 0), 2));
        $checks[] = [
            'label' => 'Gaji Bersih Final sudah diproses & sinkron dengan Data Potongan terkini',
            'ok' => $belumSinkron->isEmpty(),
            'detail' => $belumSinkron->isNotEmpty()
                ? $belumSinkron->count().' pegawai belum diproses ulang — jalankan Proses Gaji lagi.'
                : null,
        ];

        return $checks;
    }

    public function isValid(SalaryPeriod $period): bool
    {
        return collect($this->checklist($period))->every(fn ($check) => $check['ok']);
    }

    /**
     * @return array<int,string>
     */
    public function errors(SalaryPeriod $period): array
    {
        return collect($this->checklist($period))
            ->reject(fn ($check) => $check['ok'])
            ->map(fn ($check) => $check['label'].' — '.$check['detail'])
            ->values()
            ->all();
    }
}
