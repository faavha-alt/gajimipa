<?php

use App\Models\EmailLog;
use App\Models\Payslip;
use App\Models\SalaryPeriod;
use App\Services\Email\EmailService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public string $filterPeriodId = '';

    public function mount(): void
    {
        Gate::authorize('payslips.manage');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPeriodId(): void
    {
        $this->resetPage();
    }

    public function resetFilter(): void
    {
        $this->reset(['search', 'filterStatus', 'filterPeriodId']);
        $this->resetPage();
    }

    public function kirimUlang(int $payslipId): void
    {
        Gate::authorize('payslips.manage');

        $payslip = Payslip::with('salaryRecord.employee')->findOrFail($payslipId);

        app(EmailService::class)->kirimSatu($payslip, auth()->user());
    }

    public function with(): array
    {
        $logs = EmailLog::query()
            ->with(['payslip.salaryRecord.employee', 'payslip.salaryRecord.salaryPeriod', 'creator'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('email_tujuan', 'like', "%{$this->search}%")
                    ->orWhereHas('payslip.salaryRecord', fn ($q) => $q
                        ->where('nama_snapshot', 'like', "%{$this->search}%")
                        ->orWhere('nip_snapshot', 'like', "%{$this->search}%"))
                    ->orWhereHas('payslip.salaryRecord.employee', fn ($q) => $q
                        ->where('nama', 'like', "%{$this->search}%")
                        ->orWhere('nip', 'like', "%{$this->search}%"));
            }))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPeriodId, fn ($q) => $q->whereHas(
                'payslip.salaryRecord.salaryPeriod',
                fn ($q) => $q->whereKey($this->filterPeriodId)
            ))
            ->latest('id')
            ->paginate(20);

        return [
            'logs' => $logs,
            'periods' => SalaryPeriod::orderByDesc('tahun')->orderByDesc('bulan')->get(['id', 'bulan', 'tahun']),
            'statusLabels' => [
                EmailLog::STATUS_TERKIRIM => ['Terkirim', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
                EmailLog::STATUS_DIKIRIM_ULANG => ['Dikirim Ulang', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'],
                EmailLog::STATUS_GAGAL => ['Gagal', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
                EmailLog::STATUS_BELUM_DIKIRIM => ['Belum Kirim', 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'],
            ],
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Riwayat Email Slip Gaji</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Log seluruh pengiriman email slip — siapa, ke siapa, kapan, dan apakah terkirim atau gagal (§22). Email hanya terkirim lewat tombol, tidak pernah otomatis.</p>
        </div>
    </div>

    <x-flash :status="session('status')" />

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
            <div>
                <x-input-label for="cari" value="Cari" />
                <input
                    id="cari"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Nama, NIP, atau email tujuan..." aria-label="Cari nama, NIP, atau email tujuan"
                    class="mt-1 w-64 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                >
            </div>

            <div>
                <x-input-label for="filter-status" value="Status" />
                <select wire:model.live="filterStatus" id="filter-status" aria-label="Filter status" class="mt-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="">Semua Status</option>
                    <option value="{{ \App\Models\EmailLog::STATUS_TERKIRIM }}">Terkirim</option>
                    <option value="{{ \App\Models\EmailLog::STATUS_DIKIRIM_ULANG }}">Dikirim Ulang</option>
                    <option value="{{ \App\Models\EmailLog::STATUS_GAGAL }}">Gagal</option>
                    <option value="{{ \App\Models\EmailLog::STATUS_BELUM_DIKIRIM }}">Belum Kirim</option>
                </select>
            </div>

            <div>
                <x-input-label for="filter-periode" value="Periode" />
                <select wire:model.live="filterPeriodId" id="filter-periode" aria-label="Filter periode" class="mt-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="">Semua Periode</option>
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_periode }}</option>
                    @endforeach
                </select>
            </div>

            <x-secondary-button type="button" wire:click="resetFilter">Reset</x-secondary-button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Waktu</th>
                        <th scope="col" class="px-5 py-3 font-medium">Periode</th>
                        <th scope="col" class="px-5 py-3 font-medium">Pegawai</th>
                        <th scope="col" class="px-5 py-3 font-medium">Email Tujuan</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium">Catatan</th>
                        <th scope="col" class="px-5 py-3 font-medium">Dicatat Oleh</th>
                        <th scope="col" class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($logs as $log)
                        @php $record = $log->payslip?->salaryRecord; @endphp
                        <tr wire:key="el-{{ $log->id }}">
                            <td class="whitespace-nowrap px-5 py-3 text-slate-500 dark:text-slate-400">{{ $log->dikirim_pada?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-500 dark:text-slate-400">{{ $record?->salaryPeriod?->nama_periode ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ $record?->employee?->nama ?? $record?->nama_snapshot ?? '—' }}</span>
                                @if ($record?->nip_snapshot)
                                    <span class="block font-mono text-xs text-slate-400 dark:text-slate-500">{{ $record->nip_snapshot }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $log->email_tujuan ?: '—' }}</td>
                            <td class="px-5 py-3">
                                @php [$label, $tone] = $statusLabels[$log->status] ?? [$log->status, 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']; @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ $label }}</span>
                            </td>
                            <td class="max-w-xs px-5 py-3">
                                @if ($log->pesan_error)
                                    <span class="block truncate text-xs text-rose-600 dark:text-rose-400" title="{{ $log->pesan_error }}">{{ $log->pesan_error }}</span>
                                @else
                                    <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $log->creator?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                @if ($log->status === \App\Models\EmailLog::STATUS_GAGAL && $log->payslip)
                                    <button type="button" wire:click="kirimUlang({{ $log->payslip->id }})" wire:loading.attr="disabled" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 focus:bg-rose-50 focus:ring-2 focus:ring-rose-500 dark:text-rose-400 dark:hover:bg-rose-500/10 dark:focus:bg-rose-500/10">Kirim Ulang</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada riwayat email.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
