<?php

namespace App\Services\Report;

use App\Models\SalaryImport;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use Illuminate\Support\Collection;

/**
 * Ringkasan Dashboard periode aktif (CLAUDE.md §10). "Periode Aktif" =
 * periode paling baru (tahun+bulan terbesar) yang bukan versi superseded,
 * apapun statusnya (DRAFT s/d ARSIP) — supaya dashboard selalu menyorot
 * periode yang sedang paling relevan dikerjakan operator, bukan cuma yang
 * sudah final. Angka penghasilan/potongan/gaji-bersih & rekap per unit/jenis
 * potongan dipakai ulang dari `LaporanService::bulanan()` (§21) supaya tidak
 * duplikasi logic agregasi yang sama, konsisten dgn pola STEP 16.
 */
class DashboardService
{
    private const ABBR_BULAN = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    public function ringkasan(): array
    {
        $periodeAktif = SalaryPeriod::where('status_supersede', false)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->first();

        if (! $periodeAktif) {
            return ['periodeAktif' => null];
        }

        $laporan = app(LaporanService::class)->bulanan($periodeAktif);

        return [
            'periodeAktif' => $periodeAktif,
            'totals' => $laporan['totals'],
            'perUnit' => $laporan['perUnit'],
            'perJenisPotongan' => $laporan['perJenisPotongan'],
            'sudahImport' => SalaryImport::where('salary_period_id', $periodeAktif->id)->exists(),
            'trenBulanan' => $this->trenBulanan(),
            'historiPeriode' => $this->historiPeriode($periodeAktif),
        ];
    }

    /**
     * @return Collection<int,array{label:string,penghasilan:float,potongan:float}>
     */
    private function trenBulanan(int $jumlah = 6): Collection
    {
        return SalaryPeriod::where('status_supersede', false)
            ->orderByDesc('tahun')->orderByDesc('bulan')
            ->limit($jumlah)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (SalaryPeriod $p) => [
                'label' => self::ABBR_BULAN[$p->bulan]." '".substr((string) $p->tahun, 2),
                'penghasilan' => (float) SalaryRecord::where('salary_period_id', $p->id)->sum('total_penghasilan_kotor'),
                'potongan' => (float) SalaryRecord::where('salary_period_id', $p->id)->sum('total_potongan_pusat')
                    + (float) SalaryRecord::where('salary_period_id', $p->id)->sum('total_potongan_fakultas'),
            ]);
    }

    /**
     * @return Collection<int,array{nama_periode:string,status:string,gaji_bersih:float}>
     */
    private function historiPeriode(SalaryPeriod $kecuali, int $jumlah = 5): Collection
    {
        return SalaryPeriod::where('status_supersede', false)
            ->where('id', '!=', $kecuali->id)
            ->orderByDesc('tahun')->orderByDesc('bulan')
            ->limit($jumlah)
            ->get()
            ->map(fn (SalaryPeriod $p) => [
                'nama_periode' => $p->nama_periode,
                'status' => $p->status,
                'gaji_bersih' => (float) SalaryRecord::where('salary_period_id', $p->id)->sum('gaji_bersih_final'),
            ]);
    }

    /**
     * Versi personal Dashboard utk role Pegawai (§10/§23 — cuma boleh lihat
     * datanya sendiri, tidak boleh lihat total fakultas). Dipisah dari
     * ringkasan() di atas, bukan sekadar filter — supaya tidak ada jalur kode
     * yang bisa membocorkan agregat fakultas ke Pegawai.
     */
    public function ringkasanPegawai(int $employeeId): array
    {
        $records = SalaryRecord::query()
            ->join('salary_periods', 'salary_periods.id', '=', 'salary_records.salary_period_id')
            ->where('salary_records.employee_id', $employeeId)
            ->where('salary_periods.status_supersede', false)
            ->orderByDesc('salary_periods.tahun')
            ->orderByDesc('salary_periods.bulan')
            ->select('salary_records.*')
            ->with('salaryPeriod')
            ->get();

        return [
            'terbaru' => $records->first(),
            'histori' => $records->slice(1, 5)->values(),
        ];
    }
}
