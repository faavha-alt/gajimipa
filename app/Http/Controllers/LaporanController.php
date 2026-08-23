<?php

namespace App\Http\Controllers;

use App\Exports\LaporanBulananExport;
use App\Exports\LaporanTahunanExport;
use App\Models\SalaryPeriod;
use App\Services\Report\LaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Streaming PDF/Excel Laporan Bulanan & Tahunan (§21 CLAUDE.md). Live query,
 * tidak ada snapshot tersimpan — beda dari Rekap Setoran per jenis (§20)
 * yang dipersist ke submission_records.
 */
class LaporanController extends Controller
{
    public function bulananPdf(SalaryPeriod $period)
    {
        Gate::authorize('laporan.view');

        $data = app(LaporanService::class)->bulanan($period);
        $namaFile = 'Laporan-Bulanan-'.str_replace(' ', '-', $period->nama_periode).'.pdf';

        return Pdf::loadView('pdf.laporan-bulanan', [
            'period' => $period,
            ...$data,
        ])->setPaper('a4')->stream($namaFile);
    }

    public function bulananExcel(SalaryPeriod $period)
    {
        Gate::authorize('laporan.view');

        $namaFile = 'Laporan-Bulanan-'.str_replace(' ', '-', $period->nama_periode).'.xlsx';

        return Excel::download(new LaporanBulananExport($period), $namaFile);
    }

    public function tahunanPdf(int $tahun)
    {
        Gate::authorize('laporan.view');

        $data = app(LaporanService::class)->tahunan($tahun);
        $namaFile = "Laporan-Tahunan-{$tahun}.pdf";

        return Pdf::loadView('pdf.laporan-tahunan', [
            'tahun' => $tahun,
            ...$data,
        ])->setPaper('a4')->stream($namaFile);
    }

    public function tahunanExcel(int $tahun)
    {
        Gate::authorize('laporan.view');

        $namaFile = "Laporan-Tahunan-{$tahun}.xlsx";

        return Excel::download(new LaporanTahunanExport($tahun), $namaFile);
    }
}
