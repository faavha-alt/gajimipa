<?php

use App\Models\SalaryPeriod;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public SalaryPeriod $period;

    public string $search = '';

    public function mount(SalaryPeriod $period): void
    {
        Gate::authorize('periods.view');

        $this->period = $period;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'records' => $this->period->salaryRecords()
                ->with('employee:id,nama')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('nip_snapshot', 'like', "%{$this->search}%")
                    ->orWhere('nama_snapshot', 'like', "%{$this->search}%")
                    ->orWhereHas('employee', fn ($q) => $q->where('nama', 'like', "%{$this->search}%"))
                ))
                ->orderBy('nama_snapshot')
                ->paginate(20),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('salary-periods.show', $period) }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke {{ $period->nama_periode }}
        </a>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Rincian Gaji per Pegawai</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $period->nama_periode }} (v{{ $period->versi }}) — klik "Detail" untuk lihat rincian tunjangan & potongan per item.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 p-4 dark:border-slate-800">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari NIP atau nama..." aria-label="Cari NIP atau nama..."
                class="w-full max-w-sm rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">NIP</th>
                        <th scope="col" class="px-5 py-3 font-medium">Nama</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Penghasilan Kotor</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Bersih Pusat</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Potongan Fakultas</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Gaji Bersih Final</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($records as $record)
                        <tr wire:key="salary-{{ $record->id }}">
                            <td class="px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $record->nip_snapshot }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $record->employee?->nama ?? $record->nama_snapshot }}</td>
                            <td class="px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($record->total_penghasilan_kotor, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($record->bersih_pusat, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($record->total_potongan_fakultas, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($record->gaji_bersih_final, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('salary-records.show', [$period, $record]) }}" wire:navigate class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data gaji untuk periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $records->links() }}
            </div>
        @endif
    </div>
</div>
