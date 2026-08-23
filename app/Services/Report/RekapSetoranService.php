<?php

namespace App\Services\Report;

use App\Models\DeductionRecord;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\SubmissionRecord;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekap Setoran Potongan (CLAUDE.md STEP 15, §20). Sistem tidak melakukan
 * transfer/pembayaran — cuma bikin rekap yang dipakai untuk proses setoran
 * DI LUAR aplikasi. Dua bentuk rekap:
 *
 *  1. Per Jenis Potongan (§20 asli) — jumlah pegawai & total per Jenis
 *     Potongan, dipersist ke `submission_records` (skema sudah ada sejak
 *     STEP 4) setiap kali "Generate" ditekan, sesuai keputusan E3
 *     (docs/keputusan-desain.md) — snapshot historis siapa/kapan digenerate.
 *  2. Per Bank/Rekening (kebutuhan tambahan user, 2026-08-20) — daftar
 *     pegawai + no. rekening per bank, total potongan fakultas per pegawai,
 *     untuk dikirim ke bank proses tarik tunai. Live query, TIDAK dipersist
 *     (belum ada tabel snapshot utk ini — beda dari rekap per jenis yang
 *     sudah punya `submission_records` sejak awal).
 */
class RekapSetoranService
{
    public function bisaGenerate(SalaryPeriod $period): bool
    {
        return in_array($period->status, [SalaryPeriod::STATUS_FINAL, SalaryPeriod::STATUS_ARSIP], true);
    }

    /**
     * @return Collection<int,array{deduction_type_id:int,nama:string,jumlah_pegawai:int,total_nominal:float}>
     */
    public function perJenisPotongan(SalaryPeriod $period): Collection
    {
        return DB::table('deduction_records')
            ->join('salary_records', 'salary_records.id', '=', 'deduction_records.salary_record_id')
            ->join('deduction_types', 'deduction_types.id', '=', 'deduction_records.deduction_type_id')
            ->where('salary_records.salary_period_id', $period->id)
            ->selectRaw('deduction_types.id as deduction_type_id, deduction_types.nama, COUNT(DISTINCT deduction_records.salary_record_id) as jumlah_pegawai, SUM(deduction_records.nominal) as total_nominal')
            ->groupBy('deduction_types.id', 'deduction_types.nama')
            ->orderBy('deduction_types.nama')
            ->get()
            ->map(fn ($row) => [
                'deduction_type_id' => $row->deduction_type_id,
                'nama' => $row->nama,
                'jumlah_pegawai' => (int) $row->jumlah_pegawai,
                'total_nominal' => (float) $row->total_nominal,
            ]);
    }

    /**
     * @return Collection<string,Collection<int,array{nama:string,nip:string,nama_rekening:?string,no_rekening:?string,total:float}>> dikelompokkan per nama bank ("Belum Ada Bank" utk yg belum diisi)
     */
    public function perBank(SalaryPeriod $period): Collection
    {
        $records = SalaryRecord::where('salary_period_id', $period->id)
            ->where('total_potongan_fakultas', '>', 0)
            ->with('employee.bank')
            ->get();

        return $records
            ->groupBy(fn (SalaryRecord $r) => $r->employee?->bank?->nama ?? 'Belum Ada Bank')
            ->sortKeys()
            ->map(fn (Collection $group) => $group
                ->map(fn (SalaryRecord $r) => [
                    'nama' => $r->employee?->nama ?? $r->nama_snapshot,
                    'nip' => $r->nip_snapshot,
                    'nama_rekening' => $r->employee?->nama_rekening,
                    'no_rekening' => $r->employee?->no_rekening,
                    'total' => (float) $r->total_potongan_fakultas,
                ])
                ->sortBy('nama')
                ->values()
            );
    }

    /**
     * Persist rekap per Jenis Potongan sbg histori (§20 + keputusan E3 —
     * generate manual, hanya utk periode FINAL/ARSIP). Menghapus dulu
     * submission_records lama milik periode ini sebelum ganti dgn hasil
     * terbaru — supaya "Generate Ulang" tidak numpuk histori duplikat kalau
     * dijalankan berkali-kali pada periode yg sama.
     *
     * @return Collection<int,SubmissionRecord>
     */
    public function generate(SalaryPeriod $period, User $user): Collection
    {
        if (! $this->bisaGenerate($period)) {
            throw new \RuntimeException('Rekap setoran hanya bisa dibuat untuk periode berstatus FINAL atau ARSIP.');
        }

        $rekap = $this->perJenisPotongan($period);

        return DB::transaction(function () use ($period, $user, $rekap) {
            SubmissionRecord::where('salary_period_id', $period->id)->delete();

            $records = $rekap->map(fn ($row) => SubmissionRecord::create([
                'salary_period_id' => $period->id,
                'deduction_type_id' => $row['deduction_type_id'],
                'jumlah_pegawai' => $row['jumlah_pegawai'],
                'total_nominal' => $row['total_nominal'],
                'dibuat_oleh' => $user->id,
            ]));

            AuditLogger::log('Generate Rekap Setoran', "Membuat rekap setoran potongan periode {$period->nama_periode}: {$rekap->count()} jenis potongan.", [
                'salary_period_id' => $period->id,
                'jumlah_jenis' => $rekap->count(),
            ]);

            return $records;
        });
    }
}
