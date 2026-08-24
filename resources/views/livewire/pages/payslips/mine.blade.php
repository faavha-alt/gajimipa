<?php

use App\Models\Payslip;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->employee_id, 403, 'Akun ini belum ditautkan ke data Master Pegawai — hubungi Operator.');
    }

    public function with(): array
    {
        return [
            'payslips' => Payslip::query()
                ->whereHas('salaryRecord', fn ($q) => $q->where('employee_id', auth()->user()->employee_id))
                ->with('salaryRecord.salaryPeriod')
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Slip Gaji Saya</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Histori slip gaji yang sudah diterbitkan untuk Anda.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Periode</th>
                        <th scope="col" class="px-5 py-3 font-medium">Nomor Dokumen</th>
                        <th scope="col" class="px-5 py-3 font-medium">Gaji Bersih</th>
                        <th scope="col" class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($payslips as $slip)
                        <tr wire:key="slip-{{ $slip->id }}">
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">
                                {{ $slip->salaryRecord->salaryPeriod->nama_periode }}
                                @if ($slip->is_revisi)
                                    <span class="ms-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Revisi</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $slip->nomor_dokumen }}</td>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">Rp {{ number_format($slip->salaryRecord->gaji_bersih_final, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('payslips.preview', $slip) }}" target="_blank" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Preview</a>
                                <a href="{{ route('payslips.download', $slip) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Download</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada slip gaji.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
