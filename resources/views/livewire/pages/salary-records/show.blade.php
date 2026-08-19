<?php

use App\Models\SalaryComponent;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use Illuminate\Support\Facades\Gate;
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
            'penghasilan' => $this->salaryRecord->components->where('kategori', SalaryComponent::KATEGORI_PENGHASILAN),
            'potonganPusat' => $this->salaryRecord->components->where('kategori', SalaryComponent::KATEGORI_POTONGAN_PUSAT),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('salary-records.index', $period) }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke daftar pegawai
        </a>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $salaryRecord->employee?->nama ?? $salaryRecord->nama_snapshot }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    NIP {{ $salaryRecord->nip_snapshot }}
                    @if ($salaryRecord->unit_snapshot) &middot; {{ $salaryRecord->unit_snapshot }} @endif
                </p>
            </div>
            <div class="text-right text-xs text-slate-400">
                <p>{{ $period->nama_periode }} (v{{ $period->versi }})</p>
                @if ($salaryRecord->golongan_snapshot || $salaryRecord->jabatan_snapshot)
                    <p class="mt-1">Gol. {{ $salaryRecord->golongan_snapshot ?? '—' }} &middot; Jab. {{ $salaryRecord->jabatan_snapshot ?? '—' }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Penghasilan --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 bg-emerald-50/50 px-5 py-3 dark:border-slate-800 dark:bg-emerald-500/5">
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Penghasilan (dari Pusat)</p>
            </div>
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($penghasilan as $item)
                        <tr>
                            <td class="px-5 py-2.5 text-slate-600 dark:text-slate-300">{{ $item->nama_komponen }}</td>
                            <td class="px-5 py-2.5 text-right text-slate-700 dark:text-slate-200">{{ number_format($item->nominal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-4 text-center text-xs text-slate-400">Tidak ada komponen bernilai &gt; 0.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-800 dark:bg-slate-800/50">
                        <td class="px-5 py-2.5 text-slate-700 dark:text-slate-200">Total Penghasilan Kotor</td>
                        <td class="px-5 py-2.5 text-right text-slate-900 dark:text-white">{{ number_format($salaryRecord->total_penghasilan_kotor, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Potongan Pusat --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 bg-rose-50/50 px-5 py-3 dark:border-slate-800 dark:bg-rose-500/5">
                <p class="text-sm font-semibold text-rose-700 dark:text-rose-300">Potongan Pusat (BPJS, PFK, PPh, dll.)</p>
            </div>
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($potonganPusat as $item)
                        <tr>
                            <td class="px-5 py-2.5 text-slate-600 dark:text-slate-300">{{ $item->nama_komponen }}</td>
                            <td class="px-5 py-2.5 text-right text-slate-700 dark:text-slate-200">{{ number_format($item->nominal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-4 text-center text-xs text-slate-400">Tidak ada komponen bernilai &gt; 0.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-800 dark:bg-slate-800/50">
                        <td class="px-5 py-2.5 text-slate-700 dark:text-slate-200">Total Potongan Pusat</td>
                        <td class="px-5 py-2.5 text-right text-slate-900 dark:text-white">{{ number_format($salaryRecord->total_potongan_pusat, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Bersih Pusat --}}
    <div class="rounded-2xl bg-gradient-to-r from-slate-800 to-slate-700 p-5 text-white dark:from-slate-800 dark:to-slate-900">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-300">Bersih dari Pusat (Penghasilan − Potongan Pusat)</span>
            <span class="text-xl font-bold">Rp{{ number_format($salaryRecord->bersih_pusat, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- Potongan Fakultas --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-amber-50/50 px-5 py-3 dark:border-slate-800 dark:bg-amber-500/5">
            <p class="text-sm font-semibold text-amber-700 dark:text-amber-300">Potongan Fakultas</p>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-2 font-medium">Jenis Potongan</th>
                    <th class="px-5 py-2 font-medium">Sumber</th>
                    <th class="px-5 py-2 font-medium text-right">Nominal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($salaryRecord->deductionRecords as $item)
                    <tr>
                        <td class="px-5 py-2.5 text-slate-600 dark:text-slate-300">{{ $item->deductionType->nama }}</td>
                        <td class="px-5 py-2.5">
                            @if ($item->sumber === 'MANUAL')
                                <span class="inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Manual</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Import</span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-right text-slate-700 dark:text-slate-200">{{ number_format($item->nominal, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-4 text-center text-xs text-slate-400">Belum ada potongan fakultas untuk pegawai ini.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-800 dark:bg-slate-800/50">
                    <td colspan="2" class="px-5 py-2.5 text-slate-700 dark:text-slate-200">Total Potongan Fakultas</td>
                    <td class="px-5 py-2.5 text-right text-slate-900 dark:text-white">{{ number_format($salaryRecord->total_potongan_fakultas, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Gaji Bersih Final --}}
    <div class="rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 p-6 text-white shadow-lg shadow-indigo-600/20">
        <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-indigo-200">GAJI BERSIH FINAL</span>
            <span class="text-2xl font-bold">Rp{{ number_format($salaryRecord->gaji_bersih_final, 0, ',', '.') }}</span>
        </div>
    </div>
</div>
