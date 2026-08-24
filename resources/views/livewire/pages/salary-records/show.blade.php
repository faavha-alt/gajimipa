<?php

use App\Models\SalaryComponent;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public SalaryPeriod $period;

    public SalaryRecord $salaryRecord;

    public function mount(SalaryPeriod $period, SalaryRecord $salaryRecord): void
    {
        Gate::authorize('periods.view');
        abort_unless($salaryRecord->salary_period_id === $period->id, 404);

        $this->period = $period;
        $this->salaryRecord = $salaryRecord->load(['components', 'deductionRecords.deductionType', 'employee.unit']);
    }

    public function with(): array
    {
        return [
            'nama' => $this->salaryRecord->employee?->nama ?? $this->salaryRecord->nama_snapshot,
            'penghasilan' => $this->salaryRecord->components->where('kategori', SalaryComponent::KATEGORI_PENGHASILAN),
            'potonganPusat' => $this->salaryRecord->components->where('kategori', SalaryComponent::KATEGORI_POTONGAN_PUSAT),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6 print:max-w-none">
    <div class="flex items-center justify-between print:hidden">
        <a href="{{ route('salary-records.index', $period) }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke daftar pegawai
        </a>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" /></svg>
            Cetak
        </button>
    </div>

    {{-- Identitas --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 print:border-slate-300 print:shadow-none">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/60 px-6 py-3 dark:border-slate-800 dark:bg-slate-800/30 print:bg-white">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Slip Gaji &middot; {{ $period->nama_periode }} (v{{ $period->versi }})</p>
            <x-period-status-badge :status="$period->status" />
        </div>

        <div class="flex flex-wrap items-center gap-4 p-6">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg font-bold text-white">
                {{ Str::of($nama)->explode(' ')->filter()->map(fn ($w) => Str::substr($w, 0, 1))->take(2)->join('') }}
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-lg font-bold text-slate-900 dark:text-white">{{ $nama }}</h2>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    NIP {{ $salaryRecord->nip_snapshot }}
                    @if ($salaryRecord->unit_snapshot) &middot; {{ $salaryRecord->unit_snapshot }} @endif
                </p>
            </div>
            @if ($salaryRecord->golongan_snapshot || $salaryRecord->jabatan_snapshot)
                <div class="flex gap-2 text-xs">
                    @if ($salaryRecord->golongan_snapshot)
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">Gol. {{ $salaryRecord->golongan_snapshot }}</span>
                    @endif
                    @if ($salaryRecord->jabatan_snapshot)
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">Jab. {{ $salaryRecord->jabatan_snapshot }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Rincian --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 print:border-slate-300 print:shadow-none">
        {{-- Penghasilan --}}
        <div class="flex items-center gap-2 border-b border-slate-100 bg-emerald-50/40 px-6 py-2.5 dark:border-slate-800 dark:bg-emerald-500/5 print:bg-white">
            <x-nav-icon name="chart" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Penghasilan</p>
        </div>
        <dl class="divide-y divide-slate-100 px-6 dark:divide-slate-800">
            @forelse ($penghasilan as $item)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">{{ $item->nama_komponen }}</dt>
                    <dd class="font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($item->nominal, 0, ',', '.') }}</dd>
                </div>
            @empty
                <p class="py-4 text-center text-xs text-slate-500 dark:text-slate-400">Tidak ada komponen bernilai &gt; 0.</p>
            @endforelse
        </dl>
        <div class="flex items-center justify-between border-y border-slate-100 bg-slate-50/70 px-6 py-3 text-sm font-semibold dark:border-slate-800 dark:bg-slate-800/40">
            <span class="text-slate-700 dark:text-slate-200">Total Penghasilan Kotor</span>
            <span class="text-slate-900 dark:text-white">Rp {{ number_format($salaryRecord->total_penghasilan_kotor, 0, ',', '.') }}</span>
        </div>

        {{-- Potongan Pusat --}}
        <div class="flex items-center gap-2 border-b border-slate-100 bg-rose-50/40 px-6 py-2.5 dark:border-slate-800 dark:bg-rose-500/5 print:bg-white">
            <x-nav-icon name="minus-circle" class="h-4 w-4 text-rose-600 dark:text-rose-400" />
            <p class="text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">Potongan Pusat</p>
            <span class="text-xs font-normal normal-case text-slate-500 dark:text-slate-400">(BPJS, PFK, PPh, dll.)</span>
        </div>
        <dl class="divide-y divide-slate-100 px-6 dark:divide-slate-800">
            @forelse ($potonganPusat as $item)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">{{ $item->nama_komponen }}</dt>
                    <dd class="font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($item->nominal, 0, ',', '.') }}</dd>
                </div>
            @empty
                <p class="py-4 text-center text-xs text-slate-500 dark:text-slate-400">Tidak ada komponen bernilai &gt; 0.</p>
            @endforelse
        </dl>
        <div class="flex items-center justify-between border-y border-slate-100 bg-slate-50/70 px-6 py-3 text-sm font-semibold dark:border-slate-800 dark:bg-slate-800/40">
            <span class="text-slate-700 dark:text-slate-200">Total Potongan Pusat</span>
            <span class="text-slate-900 dark:text-white">Rp {{ number_format($salaryRecord->total_potongan_pusat, 0, ',', '.') }}</span>
        </div>

        {{-- Bersih Pusat --}}
        <div class="flex items-center justify-between bg-slate-800 px-6 py-3.5 text-white dark:bg-slate-950">
            <span class="text-sm font-medium text-slate-300">Bersih dari Pusat</span>
            <span class="text-lg font-bold">Rp {{ number_format($salaryRecord->bersih_pusat, 0, ',', '.') }}</span>
        </div>

        {{-- Potongan Fakultas --}}
        <div class="flex items-center gap-2 border-b border-slate-100 bg-amber-50/40 px-6 py-2.5 dark:border-slate-800 dark:bg-amber-500/5 print:bg-white">
            <x-nav-icon name="minus-circle" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
            <p class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">Potongan Fakultas</p>
        </div>
        <dl class="divide-y divide-slate-100 px-6 dark:divide-slate-800">
            @forelse ($salaryRecord->deductionRecords as $item)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <dt class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                        {{ $item->deductionType->nama }}
                        @if ($item->sumber === 'MANUAL')
                            <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Manual</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Import</span>
                        @endif
                    </dt>
                    <dd class="font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($item->nominal, 0, ',', '.') }}</dd>
                </div>
            @empty
                <p class="py-4 text-center text-xs text-slate-500 dark:text-slate-400">Belum ada potongan fakultas untuk pegawai ini.</p>
            @endforelse
        </dl>
        <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/70 px-6 py-3 text-sm font-semibold dark:border-slate-800 dark:bg-slate-800/40">
            <span class="text-slate-700 dark:text-slate-200">Total Potongan Fakultas</span>
            <span class="text-slate-900 dark:text-white">Rp {{ number_format($salaryRecord->total_potongan_fakultas, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- Gaji Bersih Final --}}
    <div class="rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 p-6 text-white shadow-lg shadow-indigo-600/20 print:border print:border-slate-300 print:bg-none print:text-slate-900 print:shadow-none">
        <div class="flex items-center justify-between">
            <span class="text-sm font-semibold uppercase tracking-wider text-indigo-100 print:text-slate-500">Gaji Bersih Final</span>
            <span class="text-2xl font-bold">Rp {{ number_format($salaryRecord->gaji_bersih_final, 0, ',', '.') }}</span>
        </div>
    </div>
</div>
