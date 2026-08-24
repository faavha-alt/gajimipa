<?php

use App\Models\Payslip;
use App\Models\SalaryPeriod;
use App\Services\Email\EmailService;
use App\Services\Payslip\PayslipService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public SalaryPeriod $period;

    public bool $generating = false;

    public bool $emailing = false;

    public function mount(SalaryPeriod $period): void
    {
        Gate::authorize('payslips.manage');
        $this->period = $period;
    }

    public function generateSatu(int $salaryRecordId): void
    {
        Gate::authorize('payslips.manage');

        $record = $this->period->salaryRecords()->findOrFail($salaryRecordId);

        try {
            app(PayslipService::class)->generate($record, auth()->user());
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function mulaiGenerateSemua(): void
    {
        Gate::authorize('payslips.manage');

        if (! app(PayslipService::class)->bisaGenerate($this->period)) {
            session()->flash('error', 'Slip gaji hanya bisa dibuat untuk periode berstatus FINAL atau ARSIP.');

            return;
        }

        $this->generating = true;
    }

    public function prosesBatch(): void
    {
        Gate::authorize('payslips.manage');

        try {
            $dibuat = app(PayslipService::class)->generateBatch($this->period, auth()->user());
        } catch (\Throwable $e) {
            $this->generating = false;
            session()->flash('error', 'Proses pembuatan slip gagal: '.$e->getMessage());

            return;
        }

        if ($dibuat === 0) {
            $this->generating = false;
            session()->flash('status', 'Semua slip gaji periode ini sudah dibuat.');
        }
    }

    public function mulaiKirimEmail(): void
    {
        Gate::authorize('payslips.manage');

        if (! app(EmailService::class)->bisaKirim($this->period)) {
            session()->flash('error', 'Kirim email hanya bisa dilakukan saat periode FINAL/ARSIP dan SMTP diaktifkan (Pengaturan → SMTP Email).');

            return;
        }

        if (app(EmailService::class)->sisaKirim($this->period) === 0) {
            session()->flash('status', 'Semua email slip periode ini sudah terkirim.');

            return;
        }

        $this->emailing = true;
    }

    public function prosesBatchEmail(): void
    {
        Gate::authorize('payslips.manage');

        try {
            $sisa = app(EmailService::class)->kirimBatch($this->period, auth()->user());
        } catch (\Throwable $e) {
            $this->emailing = false;
            session()->flash('error', 'Proses kirim email gagal: '.$e->getMessage());

            return;
        }

        if ($sisa === 0) {
            $this->emailing = false;
            session()->flash('status', 'Semua email slip periode ini sudah dikirim.');
        }
    }

    public function kirimUlang(int $payslipId): void
    {
        Gate::authorize('payslips.manage');

        $payslip = Payslip::with('salaryRecord.employee')->findOrFail($payslipId);

        app(EmailService::class)->kirimSatu($payslip, auth()->user());
    }

    public function with(): array
    {
        $salaryRecords = $this->period->salaryRecords()
            ->with(['employee', 'payslips' => fn ($q) => $q->with('emailLogs')->latest()])
            ->orderBy('nama_snapshot')
            ->get();

        return [
            'salaryRecords' => $salaryRecords,
            'jumlahSudah' => $salaryRecords->filter(fn ($r) => $r->payslips->isNotEmpty())->count(),
            'jumlahTotal' => $salaryRecords->count(),
            'bisaGenerate' => app(PayslipService::class)->bisaGenerate($this->period),
            'bisaKirimEmail' => app(EmailService::class)->bisaKirim($this->period),
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
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Slip Gaji — {{ $period->nama_periode }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $jumlahSudah }} dari {{ $jumlahTotal }} pegawai sudah punya slip.</p>
        </div>

        @if ($bisaGenerate && $jumlahSudah < $jumlahTotal)
            <div wire:loading.remove wire:target="mulaiGenerateSemua">
                <x-primary-button type="button" wire:click="mulaiGenerateSemua">Generate Semua yang Belum Ada</x-primary-button>
            </div>
        @endif

        @if ($bisaKirimEmail && $jumlahSudah > 0)
            <div wire:loading.remove wire:target="mulaiKirimEmail">
                <x-secondary-button type="button" wire:click="mulaiKirimEmail">
                    <span wire:loading.remove wire:target="mulaiKirimEmail">Kirim Email Slip</span>
                    <span wire:loading wire:target="mulaiKirimEmail">Menyiapkan…</span>
                </x-secondary-button>
            </div>
        @endif
    </div>

    @if (! $bisaGenerate)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            Periode ini belum FINAL — slip gaji baru bisa dibuat setelah periode difinalisasi (§17 CLAUDE.md).
        </div>
    @endif

    @if ($bisaGenerate && $jumlahSudah > 0 && ! $bisaKirimEmail)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            Pengiriman email belum bisa digunakan — aktifkan dan isi konfigurasi SMTP di <a href="{{ route('settings.index') }}" wire:navigate class="font-semibold underline">Pengaturan → SMTP Email</a>.
        </div>
    @endif

    <x-flash :status="session('status')" :error="session('error')" />

    @if ($generating)
        <div wire:poll.1s="prosesBatch" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Sedang membuat slip gaji ({{ $jumlahSudah }}/{{ $jumlahTotal }})... jangan tutup halaman ini.
            </div>
        </div>
    @endif

    @if ($emailing)
        <div wire:poll.1s="prosesBatchEmail" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Sedang mengirim email slip gaji... jangan tutup halaman ini. Pegawai tanpa alamat email akan ditandai Gagal.
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
                        <th scope="col" class="px-5 py-3 font-medium">Status Slip</th>
                        <th scope="col" class="px-5 py-3 font-medium">Email</th>
                        <th scope="col" class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($salaryRecords as $record)
                        @php
                            $slip = $record->payslips->first();
                            $emailStatus = $slip?->emailLogs->sortByDesc('id')->first()?->status;
                        @endphp
                        <tr wire:key="sr-{{ $record->id }}">
                            <td class="px-5 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $record->nip_snapshot }}</td>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">{{ $record->employee?->nama ?? $record->nama_snapshot }}</td>
                            <td class="px-5 py-3">
                                @if ($slip)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $slip->nomor_dokumen }}</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Belum ada</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if (! $slip)
                                    <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                @elseif ($emailStatus === \App\Models\EmailLog::STATUS_TERKIRIM)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Terkirim</span>
                                @elseif ($emailStatus === \App\Models\EmailLog::STATUS_DIKIRIM_ULANG)
                                    <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Dikirim Ulang</span>
                                @elseif ($emailStatus === \App\Models\EmailLog::STATUS_GAGAL)
                                    <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">Gagal</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Belum Kirim</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($slip)
                                    <a href="{{ route('payslips.preview', $slip) }}" target="_blank" rel="noopener" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 dark:text-indigo-400 dark:hover:bg-indigo-500/10 dark:focus:bg-indigo-500/10">Preview</a>
                                    <a href="{{ route('payslips.download', $slip) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 dark:text-indigo-400 dark:hover:bg-indigo-500/10 dark:focus:bg-indigo-500/10">Download</a>
                                    @if ($emailStatus === \App\Models\EmailLog::STATUS_GAGAL && $bisaKirimEmail)
                                        <button type="button" wire:click="kirimUlang({{ $slip->id }})" wire:loading.attr="disabled" @disabled($emailing) class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 focus:bg-rose-50 focus:ring-2 focus:ring-rose-500 dark:text-rose-400 dark:hover:bg-rose-500/10 dark:focus:bg-rose-500/10">Kirim Ulang</button>
                                    @endif
                                @elseif ($bisaGenerate)
                                    <button type="button" wire:click="generateSatu({{ $record->id }})" wire:loading.attr="disabled" @disabled($generating) class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 dark:text-indigo-400 dark:hover:bg-indigo-500/10 dark:focus:bg-indigo-500/10">Generate</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data gaji di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
