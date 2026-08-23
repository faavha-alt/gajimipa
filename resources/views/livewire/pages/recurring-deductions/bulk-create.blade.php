<?php

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\RecurringDeduction;
use App\Models\Unit;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    private const MODE_LABELS = [
        RecurringDeduction::MODE_TETAP => 'Tetap',
        RecurringDeduction::MODE_ANGSURAN => 'Angsuran',
        RecurringDeduction::MODE_TARIF_GOLONGAN => 'Ikut Tarif Golongan',
        RecurringDeduction::MODE_TARIF_STATUS_PEGAWAI => 'Ikut Tarif Status Pegawai',
    ];

    public string $deductionTypeId = '';

    public string $mode = '';

    public string $nominal = '';

    public string $jumlahCicilan = '';

    public string $keterangan = '';

    public string $search = '';

    public string $filterUnitId = '';

    public string $filterGolonganId = '';

    public string $filterEmployeeStatusId = '';

    public array $selected = [];

    public bool $selectAllVisible = false;

    public function mount(): void
    {
        Gate::authorize('recurring_deductions.manage');
    }

    public function updatedSelectAllVisible(bool $value): void
    {
        $idsTampil = $this->employeeQuery()->pluck('id')->diff($this->sudahTerdaftarIds())->all();

        $this->selected = $value
            ? array_unique(array_merge($this->selected, $idsTampil))
            : array_diff($this->selected, $idsTampil);
    }

    public function submit(): void
    {
        Gate::authorize('recurring_deductions.manage');

        $validated = $this->validate([
            'deductionTypeId' => ['required', 'exists:deduction_types,id'],
            'mode' => ['required', 'in:'.implode(',', array_keys(self::MODE_LABELS))],
            'nominal' => ['required_if:mode,'.RecurringDeduction::MODE_TETAP.','.RecurringDeduction::MODE_ANGSURAN, 'nullable', 'numeric', 'min:0'],
            'jumlahCicilan' => ['required_if:mode,'.RecurringDeduction::MODE_ANGSURAN, 'nullable', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['exists:employees,id'],
        ], [
            'selected.required' => 'Pilih minimal 1 pegawai.',
        ]);

        $isTarif = in_array($validated['mode'], [RecurringDeduction::MODE_TARIF_GOLONGAN, RecurringDeduction::MODE_TARIF_STATUS_PEGAWAI], true);
        $isAngsuran = $validated['mode'] === RecurringDeduction::MODE_ANGSURAN;

        // Dicek ulang di server (bukan cuma percaya checkbox klien) supaya
        // tidak ada yang didaftarkan dobel walau UI-nya sudah mencegah.
        $sudahTerdaftar = $this->sudahTerdaftarIds($validated['deductionTypeId']);
        $idsBaru = array_diff($validated['selected'], $sudahTerdaftar->all());

        foreach ($idsBaru as $employeeId) {
            RecurringDeduction::create([
                'employee_id' => $employeeId,
                'deduction_type_id' => $validated['deductionTypeId'],
                'mode' => $validated['mode'],
                'nominal' => $isTarif ? null : $validated['nominal'],
                'jumlah_cicilan' => $isAngsuran ? $validated['jumlahCicilan'] : null,
                'keterangan' => $validated['keterangan'] ?: null,
                'status' => RecurringDeduction::STATUS_AKTIF,
                'dibuat_oleh' => auth()->id(),
            ]);
        }

        $jumlahBaru = count($idsBaru);
        $jumlahDilewati = count($validated['selected']) - $jumlahBaru;
        $type = DeductionType::find($validated['deductionTypeId']);

        AuditLogger::log(
            'Tambah Massal Potongan Berulang',
            "{$jumlahBaru} pegawai didaftarkan ke {$type->nama} (".self::MODE_LABELS[$validated['mode']].')'.($jumlahDilewati ? ", {$jumlahDilewati} dilewati (sudah terdaftar)" : ''),
            ['deduction_type_id' => $type->id, 'jumlah' => $jumlahBaru]
        );

        $pesan = "{$jumlahBaru} pegawai berhasil didaftarkan.";
        if ($jumlahDilewati > 0) {
            $pesan .= " {$jumlahDilewati} dilewati karena sudah terdaftar aktif utk jenis potongan ini.";
        }

        session()->flash('status', $pesan);
        $this->redirect(route('recurring-deductions.index'), navigate: true);
    }

    /**
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function sudahTerdaftarIds(?string $deductionTypeId = null): \Illuminate\Support\Collection
    {
        $deductionTypeId ??= $this->deductionTypeId;

        if (! $deductionTypeId) {
            return collect();
        }

        return RecurringDeduction::where('deduction_type_id', $deductionTypeId)
            ->where('status', RecurringDeduction::STATUS_AKTIF)
            ->pluck('employee_id');
    }

    private function employeeQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Employee::query()
            ->where('status_aktif', true)
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('nama', 'like', "%{$this->search}%")
                ->orWhere('nip', 'like', "%{$this->search}%")
            ))
            ->when($this->filterUnitId, fn ($q) => $q->where('unit_id', $this->filterUnitId))
            ->when($this->filterGolonganId, fn ($q) => $q->where('golongan_id', $this->filterGolonganId))
            ->when($this->filterEmployeeStatusId, fn ($q) => $q->where('employee_status_id', $this->filterEmployeeStatusId));
    }

    public function with(): array
    {
        $sudahTerdaftar = $this->sudahTerdaftarIds();

        return [
            'deductionTypes' => DeductionType::where('status_aktif', true)->orderBy('nama')->get(),
            'units' => Unit::where('status_aktif', true)->orderBy('nama_unit')->get(),
            'golongans' => Golongan::where('status_aktif', true)->orderBy('nama')->get(),
            'employeeStatuses' => EmployeeStatus::where('status_aktif', true)->orderBy('nama')->get(),
            'modeLabels' => self::MODE_LABELS,
            'employees' => $this->employeeQuery()->with(['unit', 'golongan', 'employeeStatus'])->orderBy('nama')->get(),
            'sudahTerdaftarIds' => $sudahTerdaftar,
        ];
    }
}; ?>

<div class="w-full max-w-4xl space-y-6">
    <div>
        <a href="{{ route('recurring-deductions.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">&larr; Potongan Berulang</a>
        <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Tambah Massal</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Daftarkan satu jenis potongan ke banyak pegawai sekaligus — cocok untuk potongan yang berlaku ke banyak orang (mis. GOTA per Golongan, Paguyuban per Status Pegawai).</p>
    </div>

    <form wire:submit="submit" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">1. Jenis Potongan &amp; Mode</h3>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="deductionTypeId" value="Jenis Potongan" />
                    <select wire:model.live="deductionTypeId" id="deductionTypeId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">— Pilih Jenis Potongan —</option>
                        @foreach ($deductionTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('deductionTypeId')" />
                </div>

                <div>
                    <x-input-label for="mode" value="Mode" />
                    <select wire:model.live="mode" id="mode" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">— Pilih Mode —</option>
                        @foreach ($modeLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('mode')" />
                </div>

                @if (in_array($mode, ['TETAP', 'ANGSURAN']))
                    <div>
                        <x-input-label for="nominal" value="Nominal per Bulan (sama utk semua yg dicentang)" />
                        <x-text-input wire:model="nominal" id="nominal" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('nominal')" />
                    </div>
                @endif

                @if ($mode === 'ANGSURAN')
                    <div>
                        <x-input-label for="jumlahCicilan" value="Jumlah Cicilan (kali, sama utk semua)" />
                        <x-text-input wire:model="jumlahCicilan" id="jumlahCicilan" type="number" min="1" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('jumlahCicilan')" />
                    </div>
                @endif

                @if (in_array($mode, ['TARIF_GOLONGAN', 'TARIF_STATUS_PEGAWAI']))
                    <div class="sm:col-span-2">
                        <p class="rounded-xl bg-indigo-50 px-3 py-2.5 text-xs text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                            Nominal tidak diketik di sini — otomatis mengikuti tabel <a href="{{ route('recurring-deductions.tarif') }}" wire:navigate class="font-semibold underline" target="_blank">Tarif per {{ $mode === 'TARIF_GOLONGAN' ? 'Golongan' : 'Status Pegawai' }}</a> masing-masing pegawai saat diterapkan ke periode.
                        </p>
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <x-input-label for="keterangan" value="Keterangan (opsional, sama utk semua)" />
                    <x-text-input wire:model="keterangan" id="keterangan" type="text" class="mt-1 block w-full" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 p-5 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">2. Pilih Pegawai</h3>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari NIP atau nama..."
                        class="w-full max-w-xs rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                    <select wire:model.live="filterUnitId" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua Unit</option>
                        @foreach ($units as $u)
                            <option value="{{ $u->id }}">{{ $u->nama_unit }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterGolonganId" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua Golongan</option>
                        @foreach ($golongans as $g)
                            <option value="{{ $g->id }}">{{ $g->nama }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterEmployeeStatusId" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua Status Pegawai</option>
                        @foreach ($employeeStatuses as $s)
                            <option value="{{ $s->id }}">{{ $s->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Tips: filter dulu (mis. Golongan III/b) baru centang semua — supaya tidak perlu cari satu-satu di daftar panjang.
                    <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ count($selected) }} pegawai dipilih.</span>
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('selected')" />
            </div>

            <div class="max-h-[28rem] overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="w-10 px-4 py-2.5"><input type="checkbox" wire:model.live="selectAllVisible" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"></th>
                            <th class="px-4 py-2.5 font-medium">Pegawai</th>
                            <th class="px-4 py-2.5 font-medium">Unit</th>
                            <th class="px-4 py-2.5 font-medium">Golongan</th>
                            <th class="px-4 py-2.5 font-medium">Status Pegawai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($employees as $employee)
                            @php $sudahTerdaftar = $sudahTerdaftarIds->contains($employee->id); @endphp
                            <tr class="{{ $sudahTerdaftar ? 'opacity-50' : '' }}" wire:key="emp-{{ $employee->id }}">
                                <td class="px-4 py-2.5">
                                    <input
                                        type="checkbox"
                                        wire:model="selected"
                                        value="{{ $employee->id }}"
                                        @disabled($sudahTerdaftar)
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40"
                                    >
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="block font-medium text-slate-700 dark:text-slate-200">{{ $employee->nama }}</span>
                                    <span class="block font-mono text-xs text-slate-400">{{ $employee->nip }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $employee->unit?->nama_unit ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $employee->golongan?->nama ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">
                                    {{ $employee->employeeStatus?->nama ?? '—' }}
                                    @if ($sudahTerdaftar)
                                        <span class="ms-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Sudah terdaftar</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-400">Tidak ada pegawai yang cocok dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('recurring-deductions.index') }}" wire:navigate>
                <x-secondary-button type="button">Batal</x-secondary-button>
            </a>
            <x-primary-button type="submit">Daftarkan {{ count($selected) ? '('.count($selected).')' : '' }}</x-primary-button>
        </div>
    </form>
</div>
