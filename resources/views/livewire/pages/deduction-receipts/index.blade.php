<?php

use App\Models\SalaryPeriod;
use App\Services\DeductionReceipt\DeductionReceiptService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public SalaryPeriod $period;

    public bool $generating = false;

    public function mount(SalaryPeriod $period): void
    {
        Gate::authorize('deduction_receipts.manage');
        $this->period = $period;
    }

    public function generateSatu(int $deductionRecordId): void
    {
        Gate::authorize('deduction_receipts.manage');

        $record = app(DeductionReceiptService::class)->recordsForPeriod($this->period)->findOrFail($deductionRecordId);

        try {
            app(DeductionReceiptService::class)->generate($record, auth()->user());
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function mulaiGenerateSemua(): void
    {
        Gate::authorize('deduction_receipts.manage');

        if (! app(DeductionReceiptService::class)->bisaGenerate($this->period)) {
            session()->flash('error', 'Bukti potongan hanya bisa dibuat untuk periode berstatus FINAL atau ARSIP.');

            return;
        }

        $this->generating = true;
    }

    public function prosesBatch(): void
    {
        Gate::authorize('deduction_receipts.manage');

        try {
            $dibuat = app(DeductionReceiptService::class)->generateBatch($this->period, auth()->user());
        } catch (\Throwable $e) {
            $this->generating = false;
            session()->flash('error', 'Proses pembuatan bukti potongan gagal: '.$e->getMessage());

            return;
        }

        if ($dibuat === 0) {
            $this->generating = false;
            session()->flash('status', 'Semua bukti potongan periode ini sudah dibuat.');
        }
    }

    public function with(): array
    {
        $deductionRecords = app(DeductionReceiptService::class)->recordsForPeriod($this->period)
            ->with(['salaryRecord.employee', 'deductionType', 'receipts' => fn ($q) => $q->latest()])
            ->get()
            ->sortBy(fn ($r) => $r->salaryRecord->employee?->nama ?? $r->salaryRecord->nama_snapshot)
            ->values();

        return [
            'deductionRecords' => $deductionRecords,
            'jumlahSudah' => $deductionRecords->filter(fn ($r) => $r->receipts->isNotEmpty())->count(),
            'jumlahTotal' => $deductionRecords->count(),
            'bisaGenerate' => app(DeductionReceiptService::class)->bisaGenerate($this->period),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('salary-periods.show', $period) }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke {{ $period->nama_periode }}
        </a>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Bukti Potongan — {{ $period->nama_periode }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $jumlahSudah }} dari {{ $jumlahTotal }} entri potongan sudah punya bukti. Satu dokumen per jenis potongan per pegawai.</p>
        </div>

        @if ($bisaGenerate && $jumlahSudah < $jumlahTotal)
            <div wire:loading.remove wire:target="mulaiGenerateSemua">
                <x-primary-button type="button" wire:click="mulaiGenerateSemua">Generate Semua yang Belum Ada</x-primary-button>
            </div>
        @endif
    </div>

    @if (! $bisaGenerate)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            Periode ini belum FINAL — bukti potongan baru bisa dibuat setelah periode difinalisasi (§17 CLAUDE.md).
        </div>
    @endif

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
            {{ session('error') }}
        </div>
    @endif

    @if ($generating)
        <div wire:poll.1s="prosesBatch" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Sedang membuat bukti potongan ({{ $jumlahSudah }}/{{ $jumlahTotal }})... jangan tutup halaman ini.
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">NIP</th>
                        <th scope="col" class="px-5 py-3 font-medium">Nama</th>
                        <th scope="col" class="px-5 py-3 font-medium">Jenis Potongan</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Nominal</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status Bukti</th>
                        <th scope="col" class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($deductionRecords as $record)
                        @php $receipt = $record->receipts->first(); @endphp
                        <tr wire:key="dr-{{ $record->id }}">
                            <td class="px-5 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $record->salaryRecord->nip_snapshot }}</td>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">{{ $record->salaryRecord->employee?->nama ?? $record->salaryRecord->nama_snapshot }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $record->deductionType->nama }}</td>
                            <td class="px-5 py-3 text-right text-slate-700 dark:text-slate-200">Rp {{ number_format($record->nominal, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                @if ($receipt)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $receipt->nomor_dokumen }}</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Belum ada</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($receipt)
                                    <a href="{{ route('deduction-receipts.preview', $receipt) }}" target="_blank" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Preview</a>
                                    <a href="{{ route('deduction-receipts.download', $receipt) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Download</a>
                                @elseif ($bisaGenerate)
                                    <button type="button" wire:click="generateSatu({{ $record->id }})" wire:loading.attr="disabled" @disabled($generating) class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Generate</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data potongan di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
