<?php

use App\Models\DeductionRate;
use App\Models\DeductionType;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $filterDeductionTypeId = '';

    public bool $showModal = false;

    public string $deductionTypeId = '';

    public string $tipe = 'GOLONGAN';

    public string $golonganKelompok = '';

    public string $employeeStatusId = '';

    public string $nominal = '';

    public string $berlakuMulai = '';

    public function mount(): void
    {
        Gate::authorize('recurring_deductions.view');
    }

    public function updatingFilterDeductionTypeId(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('recurring_deductions.manage');

        $this->reset(['deductionTypeId', 'golonganKelompok', 'employeeStatusId', 'nominal']);
        $this->tipe = 'GOLONGAN';
        $this->berlakuMulai = now()->toDateString();
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('recurring_deductions.manage');

        $kelompokTersedia = Golongan::where('status_aktif', true)->get()->map->kelompok()->unique()->values()->all();

        $validated = $this->validate([
            'deductionTypeId' => ['required', 'exists:deduction_types,id'],
            'tipe' => ['required', 'in:GOLONGAN,STATUS_PEGAWAI'],
            'golonganKelompok' => ['required_if:tipe,GOLONGAN', 'nullable', 'in:'.implode(',', $kelompokTersedia)],
            'employeeStatusId' => ['required_if:tipe,STATUS_PEGAWAI', 'nullable', 'exists:employee_statuses,id'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'berlakuMulai' => ['required', 'date'],
        ]);

        $rate = DeductionRate::create([
            'deduction_type_id' => $validated['deductionTypeId'],
            'golongan_kelompok' => $validated['tipe'] === 'GOLONGAN' ? $validated['golonganKelompok'] : null,
            'employee_status_id' => $validated['tipe'] === 'STATUS_PEGAWAI' ? $validated['employeeStatusId'] : null,
            'nominal' => $validated['nominal'],
            'berlaku_mulai' => $validated['berlakuMulai'],
        ]);

        AuditLogger::log('Tambah Tarif Potongan', "{$rate->deductionType->nama} — Rp".number_format($rate->nominal, 0, ',', '.').' mulai '.$rate->berlaku_mulai->format('d-m-Y'), ['deduction_rate_id' => $rate->id]);

        $this->showModal = false;
        session()->flash('status', 'Tarif berhasil ditambahkan.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('recurring_deductions.manage');

        $rate = DeductionRate::findOrFail($id);
        AuditLogger::log('Hapus Tarif Potongan', "{$rate->deductionType->nama}", ['deduction_rate_id' => $rate->id]);
        $rate->delete();

        session()->flash('status', 'Tarif berhasil dihapus.');
    }

    public function with(): array
    {
        return [
            'rates' => DeductionRate::query()
                ->with(['deductionType', 'employeeStatus'])
                ->when($this->filterDeductionTypeId, fn ($q) => $q->where('deduction_type_id', $this->filterDeductionTypeId))
                ->orderBy('deduction_type_id')
                ->orderByDesc('berlaku_mulai')
                ->paginate(20),
            'deductionTypes' => DeductionType::where('status_aktif', true)->orderBy('nama')->get(),
            'kelompokGolongan' => Golongan::where('status_aktif', true)->get()->map->kelompok()->unique()->sort()->values(),
            'employeeStatuses' => EmployeeStatus::where('status_aktif', true)->orderBy('nama')->get(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('recurring-deductions.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">&larr; Potongan Berulang</a>
            </div>
            <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Tarif per Golongan/Status Pegawai</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Dipakai potongan bermode "Ikut Tarif" (mis. GOTA per Golongan, Paguyuban per Status Pegawai). Tarif baru menambah baris histori, bukan menimpa tarif lama — periode lama tetap memakai tarif yang berlaku saat itu.</p>
        </div>

        @can('recurring_deductions.manage')
            <x-primary-button wire:click="openCreate" type="button" class="w-fit">+ Tambah Tarif</x-primary-button>
        @endcan
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
            <select wire:model.live="filterDeductionTypeId" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Jenis Potongan</option>
                @foreach ($deductionTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3 font-medium">Jenis Potongan</th>
                        <th class="px-5 py-3 font-medium">Kategori</th>
                        <th class="px-5 py-3 font-medium text-right">Nominal</th>
                        <th class="px-5 py-3 font-medium">Berlaku Mulai</th>
                        <th class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($rates as $rate)
                        <tr wire:key="rate-{{ $rate->id }}">
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $rate->deductionType->nama }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                                @if ($rate->golongan_kelompok)
                                    <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">Golongan {{ $rate->golongan_kelompok }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">{{ $rate->employeeStatus->nama }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-medium text-slate-700 dark:text-slate-200">Rp{{ number_format($rate->nominal, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $rate->berlaku_mulai->format('d-m-Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                @can('recurring_deductions.manage')
                                    <button wire:click="delete({{ $rate->id }})" wire:confirm="Hapus tarif ini? Data potongan yang sudah pernah dibuat tidak ikut berubah." type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Hapus</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada tarif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rates->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $rates->links() }}
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
            class="relative mx-auto mb-6 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900"
        >
            <form wire:submit="save" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Tambah Tarif</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="deductionTypeId" value="Jenis Potongan" />
                        <select wire:model="deductionTypeId" id="deductionTypeId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Pilih Jenis Potongan —</option>
                            @foreach ($deductionTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('deductionTypeId')" />
                    </div>

                    <div>
                        <x-input-label value="Berdasarkan" />
                        <div class="mt-1 flex gap-4 text-sm text-slate-600 dark:text-slate-300">
                            <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="tipe" value="GOLONGAN"> Golongan</label>
                            <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="tipe" value="STATUS_PEGAWAI"> Status Pegawai</label>
                        </div>
                    </div>

                    @if ($tipe === 'GOLONGAN')
                        <div>
                            <x-input-label for="golonganKelompok" value="Golongan" />
                            <select wire:model="golonganKelompok" id="golonganKelompok" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">— Pilih Golongan —</option>
                                @foreach ($kelompokGolongan as $k)
                                    <option value="{{ $k }}">Golongan {{ $k }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400">Berlaku ke semua sub-golongan (mis. Golongan III mencakup III/a, III/b, III/c, III/d).</p>
                            <x-input-error class="mt-2" :messages="$errors->get('golonganKelompok')" />
                        </div>
                    @else
                        <div>
                            <x-input-label for="employeeStatusId" value="Status Pegawai" />
                            <select wire:model="employeeStatusId" id="employeeStatusId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">— Pilih Status Pegawai —</option>
                                @foreach ($employeeStatuses as $s)
                                    <option value="{{ $s->id }}">{{ $s->nama }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('employeeStatusId')" />
                        </div>
                    @endif

                    <div>
                        <x-input-label for="nominal" value="Nominal per Bulan" />
                        <x-text-input wire:model="nominal" id="nominal" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('nominal')" />
                    </div>

                    <div>
                        <x-input-label for="berlakuMulai" value="Berlaku Mulai" />
                        <x-text-input wire:model="berlakuMulai" id="berlakuMulai" type="date" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('berlakuMulai')" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                    <x-primary-button type="submit">Simpan</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
