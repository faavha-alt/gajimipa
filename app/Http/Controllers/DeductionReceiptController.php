<?php

namespace App\Http\Controllers;

use App\Models\DeductionReceipt;
use Illuminate\Support\Facades\Storage;

/**
 * Streaming preview/download bukti potongan (§19 CLAUDE.md). Bukan Livewire
 * karena respons berupa file binary, bukan render Blade biasa.
 */
class DeductionReceiptController extends Controller
{
    public function preview(DeductionReceipt $deductionReceipt)
    {
        $this->authorizeAkses($deductionReceipt);

        return Storage::disk('local')->response($deductionReceipt->path_file, null, ['Content-Type' => 'application/pdf']);
    }

    public function download(DeductionReceipt $deductionReceipt)
    {
        $this->authorizeAkses($deductionReceipt);

        $filename = str_replace('/', '-', $deductionReceipt->nomor_dokumen).'.pdf';

        return Storage::disk('local')->download($deductionReceipt->path_file, $filename);
    }

    private function authorizeAkses(DeductionReceipt $deductionReceipt): void
    {
        $user = auth()->user();

        if ($user->can('deduction_receipts.manage')) {
            return;
        }

        $deductionReceipt->loadMissing('deductionRecord.salaryRecord');
        abort_unless($user->employee_id && $user->employee_id === $deductionReceipt->deductionRecord->salaryRecord->employee_id, 403);
    }
}
