<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use Illuminate\Support\Facades\Storage;

/**
 * Streaming preview/download slip gaji (§18 CLAUDE.md). Bukan Livewire karena
 * respons berupa file binary, bukan render Blade biasa.
 */
class PayslipController extends Controller
{
    public function preview(Payslip $payslip)
    {
        $this->authorizeAkses($payslip);

        return Storage::disk('local')->response($payslip->path_file, null, ['Content-Type' => 'application/pdf']);
    }

    public function download(Payslip $payslip)
    {
        $this->authorizeAkses($payslip);

        $filename = str_replace('/', '-', $payslip->nomor_dokumen).'.pdf';

        return Storage::disk('local')->download($payslip->path_file, $filename);
    }

    private function authorizeAkses(Payslip $payslip): void
    {
        $user = auth()->user();

        if ($user->can('payslips.manage')) {
            return;
        }

        $payslip->loadMissing('salaryRecord');
        abort_unless($user->employee_id && $user->employee_id === $payslip->salaryRecord->employee_id, 403);
    }
}
