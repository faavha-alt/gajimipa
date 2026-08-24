<?php

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\RecurringDeduction;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    private const MODE_LABELS = [
        RecurringDeduction::MODE_TETAP => 'Tetap',
        RecurringDeduction::MODE_ANGSURAN => 'Angsuran',
        RecurringDeduction::MODE_TARIF_GOLONGAN => 'Ikut Tarif Golongan',
        RecurringDeduction::MODE_TARIF_STATUS_PEGAWAI => 'Ikut Tarif Status Pegawai',
    ];

    #[Url]
    public string $search = '';

    public string $filterStatus = '';

    public string $filterGolonganId = '';

    public string $filterEmployeeStatusId = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $employeeId = '';

    public string $deductionTypeId = '';

    public string $mode = '';

    public string $nominal = '';

    public string $jumlahCicilan = '';

    public string $keterangan = '';

    public function mount(): void
    {
        Gate::authorize('recurring_deductions.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterGolonganId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterEmployeeStatusId(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('recurring_deductions.manage');

        $this->reset(['editingId', 'employeeId', 'deductionTypeId', 'mode', 'nominal', 'jumlahCicilan', 'keterangan']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('recurring_deductions.manage');

        $rd = RecurringDeduction::findOrFail($id);
        $this->editingId = $rd->id;
        $this->employeeId = (string) $rd->employee_id;
        $this->deductionTypeId = (string) $rd->deduction_type_id;
        $this->mode = $rd->mode;
        $this->nominal = $rd->nominal !== null ? (string) $rd->nominal : '';
        $this->jumlahCicilan = $rd->jumlah_cicilan !== null ? (string) $rd->jumlah_cicilan : '';
        $this->keterangan = (string) $rd->keterangan;
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('recurring_deductions.manage');

        $validated = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'deductionTypeId' => ['required', 'exists:deduction_types,id'],
            'mode' => ['required', 'in:'.implode(',', array_keys(self::MODE_LABELS))],
            'nominal' => ['required_if:mode,'.RecurringDeduction::MODE_TETAP.','.RecurringDeduction::MODE_ANGSURAN, 'nullable', 'numeric', 'min:0'],
            'jumlahCicilan' => ['required_if:mode,'.RecurringDeduction::MODE_ANGSURAN, 'nullable', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $isTarif = in_array($validated['mode'], [RecurringDeduction::MODE_TARIF_GOLONGAN, RecurringDeduction::MODE_TARIF_STATUS_PEGAWAI], true);
        $isAngsuran = $validated['mode'] === RecurringDeduction::MODE_ANGSURAN;

        $rd = RecurringDeduction::updateOrCreate(
            ['id' => $this->editingId],
            [
                'employee_id' => $validated['employeeId'],
                'deduction_type_id' => $validated['deductionTypeId'],
                'mode' => $validated['mode'],
                'nominal' => $isTarif ? null : $validated['nominal'],
                'jumlah_cicilan' => $isAngsuran ? $validated['jumlahCicilan'] : null,
                'keterangan' => $validated['keterangan'] ?: null,
                'status' => $this->editingId ? RecurringDeduction::where('id', $this->editingId)->value('status') : RecurringDeduction::STATUS_AKTIF,
                'dibuat_oleh' => $this->editingId ? RecurringDeduction::where('id', $this->editingId)->value('dibuat_oleh') : auth()->id(),
            ]
        );

        AuditLogger::log(
            $this->editingId ? 'Ubah Potongan Berulang' : 'Buat Potongan Berulang',
            "{$rd->employee->nama} — {$rd->deductionType->nama} (".self::MODE_LABELS[$rd->mode].')',
            ['recurring_deduction_id' => $rd->id]
        );

        $this->showModal = false;
        session()->flash('status', $this->editingId ? 'Potongan berulang berhasil diperbarui.' : 'Potongan berulang berhasil ditambahkan.');
        $this->reset(['editingId', 'employeeId', 'deductionTypeId', 'mode', 'nominal', 'jumlahCicilan', 'keterangan']);
    }

    public function hentikan(int $id): void
    {
        Gate::authorize('recurring_deductions.manage');

        $rd = RecurringDeduction::findOrFail($id);
        $rd->update(['status' => RecurringDeduction::STATUS_DIHENTIKAN]);

        AuditLogger::log('Hentikan Potongan Berulang', "{$rd->employee->nama} — {$rd->deductionType->nama}", ['recurring_deduction_id' => $rd->id]);
        session()->flash('status', 'Potongan berulang dihentikan.');
    }

    public function aktifkanLagi(int $id): void
    {
        Gate::authorize('recurring_deductions.manage');

        $rd = RecurringDeduction::findOrFail($id);
        $rd->update(['status' => RecurringDeduction::STATUS_AKTIF]);

        AuditLogger::log('Aktifkan Lagi Potongan Berulang', "{$rd->employee->nama} — {$rd->deductionType->nama}", ['recurring_deduction_id' => $rd->id]);
        session()->flash('status', 'Potongan berulang diaktifkan lagi.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('recurring_deductions.manage');

        $rd = RecurringDeduction::findOrFail($id);

        if ($rd->deductionRecords()->exists()) {
            session()->flash('error', 'Tidak bisa dihapus karena sudah pernah diterapkan ke Data Potongan — gunakan "Hentikan" untuk menghentikan.');

            return;
        }

        AuditLogger::log('Hapus Potongan Berulang', "{$rd->employee->nama} — {$rd->deductionType->nama}", ['recurring_deduction_id' => $rd->id]);
        $rd->delete();
        session()->flash('status', 'Potongan berulang berhasil dihapus.');
    }

    public function with(): array
    {
        return [
            'items' => RecurringDeduction::query()
                ->with(['employee.golongan', 'employee.employeeStatus', 'deductionType'])
                ->when($this->search, fn ($q) => $q->whereHas('employee', fn ($q) => $q
                    ->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('nip', 'like', "%{$this->search}%")
                ))
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterGolonganId, fn ($q) => $q->whereHas('employee', fn ($q) => $q->where('golongan_id', $this->filterGolonganId)))
                ->when($this->filterEmployeeStatusId, fn ($q) => $q->whereHas('employee', fn ($q) => $q->where('employee_status_id', $this->filterEmployeeStatusId)))
                ->latest()
                ->paginate(20),
            'employees' => Employee::where('status_aktif', true)->orderBy('nama')->get(['id', 'nip', 'nama']),
            'deductionTypes' => DeductionType::where('status_aktif', true)->orderBy('nama')->get(),
            'golongans' => Golongan::where('status_aktif', true)->orderBy('nama')->get(),
            'employeeStatuses' => EmployeeStatus::where('status_aktif', true)->orderBy('nama')->get(),
            'modeLabels' => self::MODE_LABELS,
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Potongan Berulang</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Daftar pinjaman/iuran tetap per pegawai yang otomatis diusulkan ke Data Potongan tiap periode baru — tidak perlu input ulang dari nol.</p>
        </div>

        <div class="flex w-fit gap-2">
            @can('recurring_deductions.manage')
                <x-secondary-button href="{{ route('recurring-deductions.tarif') }}" wire:navigate>Tarif per Golongan/Status</x-secondary-button>
                <x-secondary-button href="{{ route('recurring-deductions.bulk-create') }}" wire:navigate>+ Tambah Massal</x-secondary-button>
                <x-primary-button wire:click="openCreate" type="button">+ Tambah</x-primary-button>
            @endcan
        </div>
    </div>

    <x-flash :status="session('status')" :error="session('error')" />

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari NIP atau nama..." aria-label="Cari NIP atau nama..."
                class="w-full max-w-xs rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
            <select wire:model.live="filterStatus" aria-label="Filter Status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Status</option>
                <option value="AKTIF">Aktif</option>
                <option value="LUNAS">Lunas</option>
                <option value="DIHENTIKAN">Dihentikan</option>
            </select>
            <select wire:model.live="filterGolonganId" aria-label="Filter Golongan" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Golongan</option>
                @foreach ($golongans as $g)
                    <option value="{{ $g->id }}">{{ $g->nama }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterEmployeeStatusId" aria-label="Filter Status Pegawai" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Status Pegawai</option>
                @foreach ($employeeStatuses as $s)
                    <option value="{{ $s->id }}">{{ $s->nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Pegawai</th>
                        <th scope="col" class="px-5 py-3 font-medium">Golongan</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status Pegawai</th>
                        <th scope="col" class="px-5 py-3 font-medium">Jenis Potongan</th>
                        <th scope="col" class="px-5 py-3 font-medium">Mode</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Nominal/Progres</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($items as $rd)
                        <tr wire:key="rd-{{ $rd->id }}">
                            <td class="px-5 py-3">
                                <span class="block font-medium text-slate-700 dark:text-slate-200">{{ $rd->employee->nama }}</span>
                                <span class="block font-mono text-xs text-slate-500 dark:text-slate-400">{{ $rd->employee->nip }}</span>
                            </td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $rd->employee->golongan?->nama ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $rd->employee->employeeStatus?->nama ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $rd->deductionType->nama }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $modeLabels[$rd->mode] }}</td>
                            <td class="px-5 py-3 text-right text-slate-600 dark:text-slate-300">
                                @if ($rd->mode === 'ANGSURAN')
                                    Rp {{ number_format($rd->nominal, 0, ',', '.') }} &middot; {{ $rd->cicilan_ke }}/{{ $rd->jumlah_cicilan }}
                                @elseif ($rd->nominal !== null)
                                    Rp {{ number_format($rd->nominal, 0, ',', '.') }}
                                @else
                                    <span class="text-slate-500 dark:text-slate-400">Ikut tarif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @php
                                    $statusTone = match ($rd->status) {
                                        'AKTIF' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                        'LUNAS' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                                        default => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">{{ ucfirst(strtolower($rd->status)) }}</span>
                            </td>
                            <td class="px-5 py-3">
                                @can('recurring_deductions.manage')
                                    <div class="flex justify-end gap-1.5">
                                        @if ($rd->status === 'AKTIF')
                                            <button wire:click="openEdit({{ $rd->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Edit</button>
                                            <button wire:click="hentikan({{ $rd->id }})" wire:confirm="Hentikan potongan berulang ini?" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-500/10">Hentikan</button>
                                        @elseif ($rd->status === 'DIHENTIKAN')
                                            <button wire:click="aktifkanLagi({{ $rd->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10">Aktifkan Lagi</button>
                                        @endif
                                        <button wire:click="delete({{ $rd->id }})" wire:confirm="Hapus potongan berulang ini?" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Hapus</button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada potongan berulang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    <x-modal-crud show="showModal" :title="$editingId ? 'Edit Potongan Berulang' : 'Tambah Potongan Berulang'" max-width="md">
        <form wire:submit="save" class="p-6">
            <div class="space-y-4">
                    <div>
                        <x-input-label for="employeeId" value="Pegawai" />
                        <select wire:model="employeeId" id="employeeId" @if($editingId) disabled @endif class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/50">
                            <option value="">— Pilih Pegawai —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->nip }} — {{ $employee->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('employeeId')" />
                    </div>

                    <div>
                        <x-input-label for="deductionTypeId" value="Jenis Potongan" />
                        <select wire:model="deductionTypeId" id="deductionTypeId" @if($editingId) disabled @endif class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/50">
                            <option value="">— Pilih Jenis Potongan —</option>
                            @foreach ($deductionTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('deductionTypeId')" />
                    </div>

                    <div>
                        <x-input-label for="mode" value="Mode" />
                        <select wire:model.live="mode" id="mode" @if($editingId) disabled @endif class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/50">
                            <option value="">— Pilih Mode —</option>
                            @foreach ($modeLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('mode')" />
                    </div>

                    @if (in_array($mode, ['TETAP', 'ANGSURAN']))
                        <div>
                            <x-input-label for="nominal" value="Nominal per Bulan" />
                            <x-text-input wire:model="nominal" id="nominal" type="number" step="0.01" class="mt-1 block w-full" />
                            <x-input-error class="mt-2" :messages="$errors->get('nominal')" />
                        </div>
                    @endif

                    @if ($mode === 'ANGSURAN')
                        <div>
                            <x-input-label for="jumlahCicilan" value="Jumlah Cicilan (kali)" />
                            <x-text-input wire:model="jumlahCicilan" id="jumlahCicilan" type="number" min="1" class="mt-1 block w-full" />
                            <x-input-error class="mt-2" :messages="$errors->get('jumlahCicilan')" />
                        </div>
                    @endif

                    @if (in_array($mode, ['TARIF_GOLONGAN', 'TARIF_STATUS_PEGAWAI']))
                        <p class="rounded-xl bg-indigo-50 px-3 py-2.5 text-xs text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                            Nominal tidak diketik manual — otomatis mengikuti tabel <a href="{{ route('recurring-deductions.tarif') }}" wire:navigate class="font-semibold underline">Tarif per {{ $mode === 'TARIF_GOLONGAN' ? 'Golongan' : 'Status Pegawai' }}</a> pegawai ini saat diterapkan ke periode.
                        </p>
                    @endif

                    <div>
                        <x-input-label for="keterangan" value="Keterangan (opsional)" />
                        <x-text-input wire:model="keterangan" id="keterangan" type="text" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('keterangan')" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                    <x-primary-button type="submit">Simpan</x-primary-button>
                </div>
        </form>
    </x-modal-crud>
</div>
