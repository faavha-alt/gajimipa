<?php

namespace App\Http\Controllers;

use App\Exports\RekapSetoranBankExport;
use App\Exports\RekapSetoranJenisExport;
use App\Models\SalaryPeriod;
use App\Services\Report\RekapSetoranService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Streaming PDF/Excel Rekap Setoran Potongan (§20 CLAUDE.md). Live query,
 * bukan dari file tersimpan (beda dari Payslip/DeductionReceipt) — rekap
 * per jenis potongan dipersist sbg histori ke submission_records lewat
 * RekapSetoranService::generate(), tapi PDF/Excel di sini selalu me-render
 * data terkini, bukan snapshot histori.
 */
class RekapSetoranController extends Controller
{
    public function jenisPdf(SalaryPeriod $period)
    {
        Gate::authorize('submission_records.view');

        $rekap = app(RekapSetoranService::class)->perJenisPotongan($period);
        $namaFile = 'Rekap-Setoran-Jenis-'.str_replace(' ', '-', $period->nama_periode).'.pdf';

        return Pdf::loadView('pdf.rekap-setoran-jenis', ['period' => $period, 'rekap' => $rekap])
            ->setPaper('a4')
            ->stream($namaFile);
    }

    public function jenisExcel(SalaryPeriod $period)
    {
        Gate::authorize('submission_records.view');

        $namaFile = 'Rekap-Setoran-Jenis-'.str_replace(' ', '-', $period->nama_periode).'.xlsx';

        return Excel::download(new RekapSetoranJenisExport($period), $namaFile);
    }

    public function bankPdf(SalaryPeriod $period)
    {
        Gate::authorize('submission_records.view');

        $perBank = app(RekapSetoranService::class)->perBank($period);
        $namaFile = 'Rekap-Setoran-Bank-'.str_replace(' ', '-', $period->nama_periode).'.pdf';

        return Pdf::loadView('pdf.rekap-setoran-bank', ['period' => $period, 'perBank' => $perBank])
            ->setPaper('a4')
            ->stream($namaFile);
    }

    public function bankExcel(SalaryPeriod $period)
    {
        Gate::authorize('submission_records.view');

        $namaFile = 'Rekap-Setoran-Bank-'.str_replace(' ', '-', $period->nama_periode).'.xlsx';

        return Excel::download(new RekapSetoranBankExport($period), $namaFile);
    }
}
