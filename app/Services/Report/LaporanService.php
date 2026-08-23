<?php

namespace App\Services\Report;

use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use Illuminate\Support\Collection;

/**
 * Laporan Bulanan & Tahunan (CLAUDE.md STEP 16, §21). Murni live query &
 * export — tidak ada "Generate"/persist snapshot spt Rekap Setoran (§20),
 * karena §21 tidak minta histori tersimpan, cuma "lihat & export".
 */
class LaporanService
{
    private const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * @return array{pegawai:Collection,perUnit:Collection,perJenisPotongan:Collection,totals:array}
     */
    public function bulanan(SalaryPeriod $period): array
    {
        $pegawai = SalaryRecord::where('salary_period_id', $period->id)
            ->with('employee:id,nama')
            ->orderBy('nama_snapshot')
            ->get();

        $perUnit = $pegawai
            ->groupBy(fn (SalaryRecord $r) => $r->unit_snapshot ?: 'Belum Ada Unit')
            ->sortKeys()
            ->map(fn (Collection $group) => [
                'jumlah_pegawai' => $group->count(),
                'total_penghasilan_kotor' => (float) $group->sum('total_penghasilan_kotor'),
                'total_gaji_bersih' => (float) $group->sum('gaji_bersih_final'),
            ]);

        return [
            'pegawai' => $pegawai,
            'perUnit' => $perUnit,
            'perJenisPotongan' => app(RekapSetoranService::class)->perJenisPotongan($period),
            'totals' => [
                'jumlah_pegawai' => $pegawai->count(),
                'total_penghasilan_kotor' => (float) $pegawai->sum('total_penghasilan_kotor'),
                'total_potongan_pusat' => (float) $pegawai->sum('total_potongan_pusat'),
                'bersih_pusat' => (float) $pegawai->sum('bersih_pusat'),
                'total_potongan_fakultas' => (float) $pegawai->sum('total_potongan_fakultas'),
                'total_gaji_bersih' => (float) $pegawai->sum('gaji_bersih_final'),
            ],
        ];
    }

    /**
     * Tahun-tahun yang punya minimal 1 periode FINAL/ARSIP aktif (bukan
     * superseded) — dipakai sbg pilihan di halaman landing Laporan.
     *
     * @return array<int,int>
     */
    public function tahunTersedia(): array
    {
        return SalaryPeriod::whereIn('status', [SalaryPeriod::STATUS_FINAL, SalaryPeriod::STATUS_ARSIP])
            ->where('status_supersede', false)
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->all();
    }

    /**
     * @return array{perBulan:Collection,totals:array}
     */
    public function tahunan(int $tahun): array
    {
        $periodes = SalaryPeriod::where('tahun', $tahun)
            ->whereIn('status', [SalaryPeriod::STATUS_FINAL, SalaryPeriod::STATUS_ARSIP])
            ->where('status_supersede', false)
            ->withSum('salaryRecords as total_penghasilan_kotor', 'total_penghasilan_kotor')
            ->withSum('salaryRecords as total_potongan_pusat', 'total_potongan_pusat')
            ->withSum('salaryRecords as total_potongan_fakultas', 'total_potongan_fakultas')
            ->withSum('salaryRecords as total_gaji_bersih', 'gaji_bersih_final')
            ->withCount('salaryRecords')
            ->orderBy('bulan')
            ->get();

        $perBulan = $periodes->map(fn (SalaryPeriod $p) => [
            'bulan' => $p->bulan,
            'nama_bulan' => self::NAMA_BULAN[$p->bulan],
            'periode' => $p,
            'jumlah_pegawai' => $p->salary_records_count,
            'total_penghasilan_kotor' => (float) ($p->total_penghasilan_kotor ?? 0),
            'total_potongan_pusat' => (float) ($p->total_potongan_pusat ?? 0),
            'total_potongan_fakultas' => (float) ($p->total_potongan_fakultas ?? 0),
            'total_gaji_bersih' => (float) ($p->total_gaji_bersih ?? 0),
        ]);

        return [
            'perBulan' => $perBulan,
            'totals' => [
                'total_penghasilan_kotor' => $perBulan->sum('total_penghasilan_kotor'),
                'total_potongan_pusat' => $perBulan->sum('total_potongan_pusat'),
                'total_potongan_fakultas' => $perBulan->sum('total_potongan_fakultas'),
                'total_gaji_bersih' => $perBulan->sum('total_gaji_bersih'),
            ],
        ];
    }
}
