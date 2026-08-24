<?php

namespace App\Services\Deduction;

use App\Models\DeductionRate;
use App\Models\DeductionRecord;
use App\Models\Golongan;
use App\Models\RecurringDeduction;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Potongan Berulang — otomatis mengusulkan baris Data Potongan dari daftar
 * pinjaman/iuran tetap pegawai (bukan input ulang dari nol tiap periode).
 * Aksi eksplisit lewat tombol "Terapkan Potongan Berulang" di halaman Data
 * Potongan, dengan preview dulu sebelum disimpan — konsisten dengan pola
 * Proses Gaji/Finalisasi di proyek ini (tidak ada yang jalan otomatis diam2).
 */
class RecurringDeductionService
{
    public function bisaTerapkan(SalaryPeriod $period): bool
    {
        return $period->status === SalaryPeriod::STATUS_DRAFT;
    }

    /**
     * @return Collection<int,array{recurring_deduction_id:int,nip:string,nama:string,jenis:string,mode:string,nominal:?float,catatan:?string,bisa_diterapkan:bool,alasan_dilewati:?string}>
     */
    public function preview(SalaryPeriod $period): Collection
    {
        return RecurringDeduction::where('status', RecurringDeduction::STATUS_AKTIF)
            ->with(['employee.golongan', 'employee.employeeStatus', 'deductionType'])
            ->get()
            ->map(function (RecurringDeduction $rd) use ($period) {
                $salaryRecord = SalaryRecord::where('salary_period_id', $period->id)
                    ->where('employee_id', $rd->employee_id)
                    ->first();

                $sudahDiterapkan = $salaryRecord && DeductionRecord::where('recurring_deduction_id', $rd->id)
                    ->where('salary_record_id', $salaryRecord->id)
                    ->exists();

                [$nominal, $catatan] = $this->hitungNominal($rd, $period);

                $alasanDilewati = match (true) {
                    ! $salaryRecord => 'Belum ada data gaji pusat periode ini',
                    $sudahDiterapkan => 'Sudah diterapkan di periode ini',
                    $nominal === null => 'Tarif belum diatur',
                    default => null,
                };

                return [
                    'recurring_deduction_id' => $rd->id,
                    'nip' => $rd->employee->nip,
                    'nama' => $rd->employee->nama,
                    'jenis' => $rd->deductionType->nama,
                    'mode' => $rd->mode,
                    'nominal' => $nominal,
                    'catatan' => $catatan,
                    'bisa_diterapkan' => $alasanDilewati === null,
                    'alasan_dilewati' => $alasanDilewati,
                ];
            })
            ->sortBy('nama')
            ->values();
    }

    /**
     * @return array{jumlah:int}
     */
    public function terapkan(SalaryPeriod $period, User $user): array
    {
        if (! $this->bisaTerapkan($period)) {
            throw new \RuntimeException('Potongan berulang hanya bisa diterapkan ke periode berstatus DRAFT.');
        }

        $preview = $this->preview($period);
        $diterapkan = 0;

        DB::transaction(function () use ($preview, $period, $user, &$diterapkan) {
            foreach ($preview as $row) {
                if (! $row['bisa_diterapkan']) {
                    continue;
                }

                $rd = RecurringDeduction::findOrFail($row['recurring_deduction_id']);
                $salaryRecord = SalaryRecord::where('salary_period_id', $period->id)
                    ->where('employee_id', $rd->employee_id)
                    ->first();

                DeductionRecord::create([
                    'salary_record_id' => $salaryRecord->id,
                    'deduction_type_id' => $rd->deduction_type_id,
                    'recurring_deduction_id' => $rd->id,
                    'nominal' => $row['nominal'],
                    'keterangan' => $rd->keterangan,
                    'sumber' => DeductionRecord::SUMBER_BERULANG,
                    'dibuat_oleh' => $user->id,
                ]);

                if ($rd->mode === RecurringDeduction::MODE_ANGSURAN) {
                    $rd->cicilan_ke++;
                    if ($rd->cicilan_ke >= $rd->jumlah_cicilan) {
                        $rd->status = RecurringDeduction::STATUS_LUNAS;
                    }
                    $rd->save();
                }

                $diterapkan++;
            }

            AuditLogger::log(
                'Terapkan Potongan Berulang',
                "Menerapkan {$diterapkan} potongan berulang ke periode {$period->nama_periode}.",
                ['salary_period_id' => $period->id, 'jumlah' => $diterapkan]
            );
        });

        return ['jumlah' => $diterapkan];
    }

    /**
     * @return array{0:?float,1:?string}
     */
    private function hitungNominal(RecurringDeduction $rd, SalaryPeriod $period): array
    {
        return match ($rd->mode) {
            RecurringDeduction::MODE_TETAP => [(float) $rd->nominal, null],
            RecurringDeduction::MODE_ANGSURAN => [(float) $rd->nominal, 'Cicilan ke-'.($rd->cicilan_ke + 1)." dari {$rd->jumlah_cicilan}"],
            RecurringDeduction::MODE_TARIF_GOLONGAN => $this->cariTarifGolongan($rd->deduction_type_id, $rd->employee->golongan, $period),
            RecurringDeduction::MODE_TARIF_STATUS_PEGAWAI => $this->cariTarif($rd->deduction_type_id, 'employee_status_id', $rd->employee->employee_status_id, $period),
            default => [null, null],
        };
    }

    /**
     * Tarif golongan berlaku per KELOMPOK golongan (mis. "III"), bukan per
     * sub-golongan ("III/a" vs "III/b") — lihat Golongan::kelompok().
     *
     * @return array{0:?float,1:?string}
     */
    private function cariTarifGolongan(int $deductionTypeId, ?Golongan $golongan, SalaryPeriod $period): array
    {
        if (! $golongan) {
            return [null, 'Pegawai belum punya Golongan'];
        }

        $tanggalPeriode = Carbon::create($period->tahun, $period->bulan, 1);

        $tarif = DeductionRate::where('deduction_type_id', $deductionTypeId)
            ->where('golongan_kelompok', $golongan->kelompok())
            ->where('berlaku_mulai', '<=', $tanggalPeriode)
            ->orderByDesc('berlaku_mulai')
            ->first();

        return $tarif
            ? [(float) $tarif->nominal, 'Tarif Golongan '.$golongan->kelompok().' berlaku sejak '.$tarif->berlaku_mulai->format('d-m-Y')]
            : [null, null];
    }

    /**
     * @return array{0:?float,1:?string}
     */
    private function cariTarif(int $deductionTypeId, string $kolom, ?int $nilai, SalaryPeriod $period): array
    {
        if (! $nilai) {
            return [null, 'Pegawai belum punya Status Pegawai'];
        }

        $tanggalPeriode = Carbon::create($period->tahun, $period->bulan, 1);

        $tarif = DeductionRate::where('deduction_type_id', $deductionTypeId)
            ->where($kolom, $nilai)
            ->where('berlaku_mulai', '<=', $tanggalPeriode)
            ->orderByDesc('berlaku_mulai')
            ->first();

        return $tarif
            ? [(float) $tarif->nominal, 'Tarif berlaku sejak '.$tarif->berlaku_mulai->format('d-m-Y')]
            : [null, null];
    }
}
