<?php

use App\Services\Report\LaporanService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $tahun;

    public function mount(int $tahun): void
    {
        Gate::authorize('laporan.view');
        $this->tahun = $tahun;
    }

    public function with(): array
    {
        return app(LaporanService::class)->tahunan($this->tahun);
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('laporan.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke Laporan
        </a>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Laporan Tahunan — {{ $tahun }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Hanya periode FINAL/ARSIP versi aktif yang dihitung.</p>
        </div>
        <div class="flex gap-2">
            <x-secondary-button href="{{ route('laporan.tahunan-pdf', $tahun) }}" target="_blank">Download PDF</x-secondary-button>
            <x-secondary-button href="{{ route('laporan.tahunan-excel', $tahun) }}">Download Excel</x-secondary-button>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Penghasilan Kotor</p>
            <p class="mt-1 text-lg font-bold text-slate-800 dark:text-white">Rp {{ number_format($totals['total_penghasilan_kotor'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Potongan Pusat</p>
            <p class="mt-1 text-lg font-bold text-slate-800 dark:text-white">Rp {{ number_format($totals['total_potongan_pusat'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Potongan Fakultas</p>
            <p class="mt-1 text-lg font-bold text-slate-800 dark:text-white">Rp {{ number_format($totals['total_potongan_fakultas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm dark:border-indigo-500/20 dark:bg-indigo-500/10">
            <p class="text-xs uppercase tracking-wide text-indigo-500">Gaji Bersih</p>
            <p class="mt-1 text-lg font-bold text-indigo-700 dark:text-indigo-300">Rp {{ number_format($totals['total_gaji_bersih'], 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Perbandingan Antarbulan --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Perbandingan Antarbulan</h3>
        </div>
        <div class="divide-y divide-slate-100 p-5 dark:divide-slate-800">
            @php $maxBersih = $perBulan->max('total_gaji_bersih') ?: 1; @endphp
            @forelse ($perBulan as $row)
                <div class="flex items-center gap-4 py-3">
                    <span class="w-28 shrink-0 text-sm text-slate-600 dark:text-slate-300">{{ $row['nama_bulan'] }}</span>
                    <div class="h-2.5 flex-1 rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-2.5 rounded-full bg-indigo-500" style="width: {{ max(2, round($row['total_gaji_bersih'] / $maxBersih * 100)) }}%"></div>
                    </div>
                    <span class="w-40 shrink-0 text-right text-sm font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($row['total_gaji_bersih'], 0, ',', '.') }}</span>
                    <span class="w-16 shrink-0 text-right text-xs text-slate-500 dark:text-slate-400">{{ $row['jumlah_pegawai'] }} pegawai</span>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada periode FINAL/ARSIP pada tahun ini.</p>
            @endforelse
        </div>
    </div>
</div>
