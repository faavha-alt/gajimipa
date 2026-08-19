<?php

use App\Models\SalaryPeriod;
use App\Services\Salary\SalaryPeriodService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public SalaryPeriod $period;

    public bool $showKembalikanModal = false;

    public string $alasanKembali = '';

    public bool $showRevisiModal = false;

    public string $alasanRevisi = '';

    public function mount(SalaryPeriod $period): void
    {
        $this->period = $period;
    }

    private function refreshPeriod(): void
    {
        $this->period = $this->period->fresh();
    }

    public function submitVerifikasi(): void
    {
        Gate::authorize('periods.submit');

        try {
            app(SalaryPeriodService::class)->ajukanVerifikasi($this->period, auth()->user());
            session()->flash('status', 'Periode diajukan untuk verifikasi.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->refreshPeriod();
    }

    public function openKembalikan(): void
    {
        Gate::authorize('periods.verify');
        $this->alasanKembali = '';
        $this->showKembalikanModal = true;
    }

    public function kembalikanKeDraft(): void
    {
        Gate::authorize('periods.verify');

        $this->validate(['alasanKembali' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            app(SalaryPeriodService::class)->kembalikanKeDraft($this->period, auth()->user(), $this->alasanKembali);
            session()->flash('status', 'Periode dikembalikan ke DRAFT.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->showKembalikanModal = false;
        $this->refreshPeriod();
    }

    public function finalisasi(): void
    {
        Gate::authorize('periods.verify');

        try {
            app(SalaryPeriodService::class)->finalisasi($this->period, auth()->user());
            session()->flash('status', 'Periode berhasil difinalisasi.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->refreshPeriod();
    }

    public function arsipkan(): void
    {
        Gate::authorize('periods.archive');

        try {
            app(SalaryPeriodService::class)->arsipkan($this->period);
            session()->flash('status', 'Periode berhasil diarsipkan.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->refreshPeriod();
    }

    public function openRevisi(): void
    {
        Gate::authorize('periods.revise');
        $this->alasanRevisi = '';
        $this->showRevisiModal = true;
    }

    public function ajukanRevisi()
    {
        Gate::authorize('periods.revise');

        $this->validate(['alasanRevisi' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $versiBaru = app(SalaryPeriodService::class)->ajukanRevisi($this->period, auth()->user(), $this->alasanRevisi);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->showRevisiModal = false;
            $this->refreshPeriod();

            return;
        }

        session()->flash('status', 'Revisi diajukan — versi baru dibuat.');
        $this->redirect(route('salary-periods.show', $versiBaru), navigate: true);
    }

    public function with(): array
    {
        return [
            'revisi' => $this->period->revisi()->orderByDesc('versi')->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('salary-periods.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke daftar periode
        </a>
    </div>

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

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $period->nama_periode }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Versi {{ $period->versi }}</p>
            </div>
            <x-period-status-badge :status="$period->status" class="text-sm" />
        </div>

        @if ($period->locked_by_user_id)
            <div class="mt-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" /></svg>
                Sedang diproses oleh <strong>{{ $period->lockedBy?->name }}</strong> sejak {{ $period->locked_at?->translatedFormat('d M Y H:i') }} WIB.
            </div>
        @endif

        @if ($period->periode_asal_id)
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                Versi ini adalah hasil revisi dari
                <a href="{{ route('salary-periods.show', $period->periode_asal_id) }}" wire:navigate class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">periode versi {{ $period->versi - 1 }}</a>.
                @if ($period->alasan_revisi)
                    <span class="block mt-1 italic">Alasan: {{ $period->alasan_revisi }}</span>
                @endif
            </div>
        @endif

        @if ($revisi->isNotEmpty())
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                Sudah direvisi menjadi
                <a href="{{ route('salary-periods.show', $revisi->first()) }}" wire:navigate class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">versi {{ $revisi->first()->versi }}</a>
                — versi ini ditandai digantikan (superseded), tetap tersimpan sebagai arsip.
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-2">
            @can('periods.submit')
                @if ($period->status === 'DRAFT')
                    <x-primary-button wire:click="submitVerifikasi" type="button">Ajukan Verifikasi</x-primary-button>
                @endif
            @endcan

            @can('periods.verify')
                @if ($period->status === 'VERIFIKASI')
                    <x-primary-button wire:click="finalisasi" type="button">Finalisasi</x-primary-button>
                    <x-secondary-button wire:click="openKembalikan" type="button">Kembalikan ke Draft</x-secondary-button>
                @endif
            @endcan

            @can('periods.archive')
                @if ($period->status === 'FINAL')
                    <x-secondary-button wire:click="arsipkan" type="button" wire:confirm="Arsipkan periode ini?">Arsipkan</x-secondary-button>
                @endif
            @endcan

            @can('periods.revise')
                @if ($period->status === 'FINAL' && ! $period->status_supersede)
                    <x-secondary-button wire:click="openRevisi" type="button">Ajukan Revisi</x-secondary-button>
                @endif
            @endcan

            @php
                $canActNow = match ($period->status) {
                    'DRAFT' => auth()->user()->can('periods.submit'),
                    'VERIFIKASI' => auth()->user()->can('periods.verify'),
                    'FINAL' => auth()->user()->can('periods.archive') || (auth()->user()->can('periods.revise') && ! $period->status_supersede),
                    default => false,
                };
            @endphp
            @unless ($canActNow)
                <p class="text-sm text-slate-400">Tidak ada aksi yang bisa dilakukan untuk peran Anda pada status ini.</p>
            @endunless
        </div>
    </div>

    <div x-data="{ show: @entangle('showKembalikanModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" x-on:click="show = false"></div>
        <div x-show="show" class="relative mx-auto mb-6 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900">
            <form wire:submit="kembalikanKeDraft" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Kembalikan ke Draft</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Alasan wajib diisi — akan tercatat di audit log.</p>

                <div class="mt-4">
                    <x-input-label for="alasanKembali" value="Alasan" />
                    <textarea wire:model="alasanKembali" id="alasanKembali" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="mis. Ada NIP yang belum sesuai di baris 12"></textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('alasanKembali')" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                    <x-primary-button type="submit">Kembalikan</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <div x-data="{ show: @entangle('showRevisiModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" x-on:click="show = false"></div>
        <div x-show="show" class="relative mx-auto mb-6 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900">
            <form wire:submit="ajukanRevisi" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Ajukan Revisi</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Data FINAL tidak diubah langsung — sistem akan membuat versi baru (§17 CLAUDE.md). Alasan wajib diisi.</p>

                <div class="mt-4">
                    <x-input-label for="alasanRevisi" value="Alasan" />
                    <textarea wire:model="alasanRevisi" id="alasanRevisi" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="mis. Ada koreksi tunjangan setelah slip terbit"></textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('alasanRevisi')" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                    <x-primary-button type="submit">Ajukan Revisi</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
