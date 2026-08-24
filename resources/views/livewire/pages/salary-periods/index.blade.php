<?php

use App\Models\SalaryPeriod;
use App\Services\Salary\SalaryPeriodService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public string $bulan = '';

    public string $tahun = '';

    private const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function openCreate(): void
    {
        Gate::authorize('periods.create');

        $this->reset(['bulan', 'tahun']);
        $this->tahun = (string) now()->year;
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('periods.create');

        $validated = $this->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
        ]);

        try {
            app(SalaryPeriodService::class)->create((int) $validated['bulan'], (int) $validated['tahun']);
        } catch (\RuntimeException $e) {
            $this->addError('bulan', $e->getMessage());

            return;
        }

        $this->showModal = false;
        session()->flash('status', 'Periode berhasil dibuat.');
    }

    public function hapusPeriode(int $periodId): void
    {
        Gate::authorize('periods.delete');

        $period = SalaryPeriod::findOrFail($periodId);

        try {
            app(SalaryPeriodService::class)->hapusPeriode($period, auth()->user());
            session()->flash('status', "Periode {$period->nama_periode} (v{$period->versi}) berhasil dihapus total.");
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with(): array
    {
        return [
            'periods' => SalaryPeriod::query()
                ->withCount('salaryRecords')
                ->orderByDesc('tahun')
                ->orderByDesc('bulan')
                ->orderByDesc('versi')
                ->paginate(15),
            'namaBulan' => self::NAMA_BULAN,
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Periode Gaji</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Siklus DRAFT → VERIFIKASI → FINAL → ARSIP untuk tiap periode penggajian (CLAUDE.md §7).</p>
        </div>

        @can('periods.create')
            <x-primary-button wire:click="openCreate" type="button" class="w-fit">
                + Buat Periode
            </x-primary-button>
        @endcan
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

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Periode</th>
                        <th scope="col" class="px-5 py-3 font-medium">Versi</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium">Terkunci Oleh</th>
                        <th scope="col" class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($periods as $period)
                        <tr wire:key="period-{{ $period->id }}">
                            <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">
                                {{ $period->nama_periode }}
                                @if ($period->status_supersede)
                                    <span class="ms-1 text-xs font-normal text-slate-500 dark:text-slate-400">(digantikan)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">v{{ $period->versi }}</td>
                            <td class="px-5 py-3">
                                <x-period-status-badge :status="$period->status" />
                            </td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">
                                @if ($period->locked_by_user_id)
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" /></svg>
                                        {{ $period->lockedBy?->name ?? '—' }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('salary-periods.show', $period) }}" wire:navigate class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                    Detail
                                </a>
                                @can('periods.delete')
                                    @if ($period->status === 'DRAFT')
                                        <button
                                            type="button"
                                            wire:click="hapusPeriode({{ $period->id }})"
                                            wire:confirm="Hapus total periode {{ $period->nama_periode }} (v{{ $period->versi }})? Seluruh data gaji, potongan, dan hasil import di periode ini akan ikut terhapus permanen. Tindakan ini tidak bisa dibatalkan."
                                            class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                        >
                                            Hapus
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada periode gaji.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($periods->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $periods->links() }}
            </div>
        @endif
    </div>

    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" x-on:click="show = false"></div>

        <div
            x-show="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            class="relative mx-auto mb-6 w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900"
        >
            <form wire:submit="save" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Buat Periode Gaji</h2>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="bulan" value="Bulan" />
                        <select wire:model="bulan" id="bulan" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">—</option>
                            @foreach ($namaBulan as $num => $nama)
                                <option value="{{ $num }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('bulan')" />
                    </div>

                    <div>
                        <x-input-label for="tahun" value="Tahun" />
                        <x-text-input wire:model="tahun" id="tahun" type="number" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('tahun')" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                    <x-primary-button type="submit">Buat</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
