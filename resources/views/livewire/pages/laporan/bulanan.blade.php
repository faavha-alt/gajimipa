<?php

use App\Models\SalaryPeriod;
use App\Services\Report\LaporanService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public SalaryPeriod $period;

    public function mount(SalaryPeriod $period): void
    {
        Gate::authorize('laporan.view');
        $this->period = $period;
    }

    public function with(): array
    {
        return app(LaporanService::class)->bulanan($this->period);
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
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Laporan Bulanan — {{ $period->nama_periode }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $totals['jumlah_pegawai'] }} pegawai.</p>
        </div>
        <div class="flex gap-2">
            <x-secondary-button href="{{ route('laporan.bulanan-pdf', $period) }}" target="_blank" rel="noopener">Download PDF</x-secondary-button>
            <x-secondary-button href="{{ route('laporan.bulanan-excel', $period) }}">Download Excel</x-secondary-button>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Pegawai</p>
            <p class="mt-1 text-lg font-bold text-slate-800 dark:text-white">{{ $totals['jumlah_pegawai'] }}</p>
        </div>
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

    {{-- Per Unit --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Rekap per Unit</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Unit</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Jumlah Pegawai</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Penghasilan Kotor</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Gaji Bersih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($perUnit as $unit => $row)
                        <tr>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">{{ $unit }}</td>
                            <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">{{ $row['jumlah_pegawai'] }}</td>
                            <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">Rp {{ number_format($row['total_penghasilan_kotor'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($row['total_gaji_bersih'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Per Jenis Potongan --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Rekap per Jenis Potongan</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Jenis Potongan</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Jumlah Pegawai</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($perJenisPotongan as $row)
                        <tr>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">{{ $row['nama'] }}</td>
                            <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">{{ $row['jumlah_pegawai'] }}</td>
                            <td class="px-5 py-3 text-right font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($row['total_nominal'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada data potongan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Daftar Pegawai --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Pegawai</h3>
        </div>
        <div class="max-h-[32rem] overflow-auto">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">NIP</th>
                        <th scope="col" class="px-5 py-3 font-medium">Nama</th>
                        <th scope="col" class="px-5 py-3 font-medium">Unit</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Penghasilan Kotor</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Pot. Fakultas</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Gaji Bersih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($pegawai as $r)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $r->nip_snapshot }}</td>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">{{ $r->employee?->nama ?? $r->nama_snapshot }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $r->unit_snapshot ?: '-' }}</td>
                            <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">Rp {{ number_format($r->total_penghasilan_kotor, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">Rp {{ number_format($r->total_potongan_fakultas, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($r->gaji_bersih_final, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
