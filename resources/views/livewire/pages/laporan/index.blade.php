<?php

use App\Models\SalaryPeriod;
use App\Services\Report\LaporanService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('laporan.view');
    }

    public function with(): array
    {
        return [
            'periods' => SalaryPeriod::whereIn('status', [SalaryPeriod::STATUS_FINAL, SalaryPeriod::STATUS_ARSIP])
                ->where('status_supersede', false)
                ->orderByDesc('tahun')
                ->orderByDesc('bulan')
                ->get(),
            'tahunTersedia' => app(LaporanService::class)->tahunTersedia(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Laporan</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Laporan bulanan & tahunan (§21 CLAUDE.md) — lihat & ekspor PDF/Excel.</p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Laporan Bulanan</h3>
                <p class="text-xs text-slate-400 dark:text-slate-500">Pilih periode (hanya FINAL/ARSIP, versi aktif).</p>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($periods as $period)
                    <a href="{{ route('laporan.bulanan', $period) }}" wire:navigate class="flex items-center justify-between px-5 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <span class="text-slate-700 dark:text-slate-200">{{ $period->nama_periode }} (v{{ $period->versi }})</span>
                        <x-period-status-badge :status="$period->status" />
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-400">Belum ada periode FINAL/ARSIP.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Laporan Tahunan</h3>
                <p class="text-xs text-slate-400 dark:text-slate-500">Rekap seluruh periode dalam 1 tahun & perbandingan antarbulan.</p>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($tahunTersedia as $tahun)
                    <a href="{{ route('laporan.tahunan', $tahun) }}" wire:navigate class="flex items-center justify-between px-5 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <span class="text-slate-700 dark:text-slate-200">Tahun {{ $tahun }}</span>
                        <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-400">Belum ada periode FINAL/ARSIP.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
