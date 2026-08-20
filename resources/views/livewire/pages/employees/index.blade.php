<?php

use App\Models\Bank;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\JabatanFungsional;
use App\Models\Unit;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterUnit = '';

    public string $filterStatus = '';

    public string $filterGolongan = '';

    public string $filterJabatanFungsional = '';

    public string $filterAktif = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $nip = '';

    public string $nik = '';

    public string $nama = '';

    public string $unit_id = '';

    public string $employee_status_id = '';

    public string $golongan_id = '';

    public string $jabatan_fungsional_id = '';

    public string $email = '';

    public string $no_hp = '';

    public string $id_simpeg = '';

    public string $npwp = '';

    public string $no_rekening = '';

    public string $nama_rekening = '';

    public string $bank_id = '';

    public bool $status_aktif = true;

    private const RESETTABLE_FIELDS = [
        'editingId', 'nip', 'nik', 'nama', 'unit_id', 'employee_status_id', 'golongan_id', 'jabatan_fungsional_id',
        'email', 'no_hp', 'id_simpeg', 'npwp', 'no_rekening', 'nama_rekening', 'bank_id',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUnit(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterGolongan(): void
    {
        $this->resetPage();
    }

    public function updatingFilterJabatanFungsional(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAktif(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('employees.manage');

        $this->reset(self::RESETTABLE_FIELDS);
        $this->status_aktif = true;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('employees.manage');

        $employee = Employee::select([
            'id', 'nip', 'nik', 'nama', 'unit_id', 'employee_status_id', 'golongan_id', 'jabatan_fungsional_id', 'email', 'no_hp',
            'id_simpeg', 'npwp', 'no_rekening', 'nama_rekening', 'bank_id', 'status_aktif',
        ])->findOrFail($id);

        $this->editingId = $employee->id;
        $this->nip = $employee->nip;
        $this->nik = (string) $employee->nik;
        $this->nama = $employee->nama;
        $this->unit_id = (string) $employee->unit_id;
        $this->employee_status_id = (string) $employee->employee_status_id;
        $this->golongan_id = (string) $employee->golongan_id;
        $this->jabatan_fungsional_id = (string) $employee->jabatan_fungsional_id;
        $this->email = (string) $employee->email;
        $this->no_hp = (string) $employee->no_hp;
        $this->id_simpeg = (string) $employee->id_simpeg;
        $this->npwp = (string) $employee->npwp;
        $this->no_rekening = (string) $employee->no_rekening;
        $this->nama_rekening = (string) $employee->nama_rekening;
        $this->bank_id = (string) $employee->bank_id;
        $this->status_aktif = $employee->status_aktif;
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('employees.manage');

        $validated = $this->validate([
            'nip' => ['required', 'digits_between:8,20', Illuminate\Validation\Rule::unique('employees', 'nip')->ignore($this->editingId)],
            'nik' => ['nullable', 'digits:16', Illuminate\Validation\Rule::unique('employees', 'nik')->ignore($this->editingId)],
            'nama' => ['required', 'string', 'max:150'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'employee_status_id' => ['nullable', 'exists:employee_statuses,id'],
            'golongan_id' => ['nullable', 'exists:golongans,id'],
            'jabatan_fungsional_id' => ['nullable', 'exists:jabatan_fungsionals,id'],
            'email' => ['nullable', 'email', 'max:150', Illuminate\Validation\Rule::unique('employees', 'email')->ignore($this->editingId)],
            'no_hp' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'id_simpeg' => ['nullable', 'string', 'max:30', Illuminate\Validation\Rule::unique('employees', 'id_simpeg')->ignore($this->editingId)],
            'npwp' => ['nullable', 'string', 'max:25', Illuminate\Validation\Rule::unique('employees', 'npwp')->ignore($this->editingId)],
            'no_rekening' => ['nullable', 'string', 'max:30'],
            'nama_rekening' => ['nullable', 'string', 'max:150'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'status_aktif' => ['boolean'],
        ]);

        foreach (['nik', 'unit_id', 'employee_status_id', 'golongan_id', 'jabatan_fungsional_id', 'email', 'no_hp', 'id_simpeg', 'npwp', 'no_rekening', 'nama_rekening', 'bank_id'] as $nullableField) {
            $validated[$nullableField] = $validated[$nullableField] ?: null;
        }

        Employee::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
        session()->flash('status', $this->editingId ? 'Data pegawai berhasil diperbarui.' : 'Pegawai berhasil ditambahkan.');
        $this->reset(self::RESETTABLE_FIELDS);
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('employees.manage');

        $employee = Employee::findOrFail($id);
        $employee->update(['status_aktif' => ! $employee->status_aktif]);
    }

    public function delete(int $id): void
    {
        Gate::authorize('employees.manage');

        $employee = Employee::withCount('salaryRecords')->findOrFail($id);

        if ($employee->salary_records_count > 0) {
            session()->flash('error', 'Pegawai "'.$employee->nama.'" tidak bisa dihapus karena sudah punya riwayat gaji. Nonaktifkan saja jika pegawai sudah tidak aktif.');

            return;
        }

        $employee->delete();
        session()->flash('status', 'Pegawai berhasil dihapus.');
    }

    public function with(): array
    {
        return [
            // Sengaja select kolom terbatas (tanpa npwp/no_rekening) — daftar
            // pegawai tidak pernah butuh menampilkan data finansial sensitif.
            'employees' => Employee::query()
                ->select(['id', 'nip', 'nama', 'unit_id', 'employee_status_id', 'golongan_id', 'jabatan_fungsional_id', 'status_aktif'])
                ->with(['unit:id,nama_unit', 'employeeStatus:id,nama', 'golongan:id,nama', 'jabatanFungsional:id,nama'])
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('nip', 'like', "%{$this->search}%")
                    ->orWhere('nama', 'like', "%{$this->search}%")
                ))
                ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
                ->when($this->filterStatus, fn ($q) => $q->where('employee_status_id', $this->filterStatus))
                ->when($this->filterGolongan, fn ($q) => $q->where('golongan_id', $this->filterGolongan))
                ->when($this->filterJabatanFungsional, fn ($q) => $q->where('jabatan_fungsional_id', $this->filterJabatanFungsional))
                ->when($this->filterAktif !== '', fn ($q) => $q->where('status_aktif', $this->filterAktif === '1'))
                ->orderBy('nama')
                ->paginate(10),
            'units' => Unit::where('status_aktif', true)->orderBy('nama_unit')->get(),
            'statuses' => EmployeeStatus::where('status_aktif', true)->orderBy('nama')->get(),
            'golongans' => Golongan::where('status_aktif', true)->orderBy('kode')->get(),
            'jabatanFungsionals' => JabatanFungsional::where('status_aktif', true)->orderBy('kode')->get(),
            'allUnits' => Unit::orderBy('nama_unit')->get(),
            'allStatuses' => EmployeeStatus::orderBy('nama')->get(),
            'allGolongans' => Golongan::orderBy('kode')->get(),
            'allJabatanFungsionals' => JabatanFungsional::orderBy('kode')->get(),
            'banks' => Bank::where('status_aktif', true)->orderBy('nama')->get(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Master Pegawai</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Data pokok pegawai Fakultas MIPA UNS. NIP dipakai sebagai identifier utama — nama tidak dipakai untuk pencocokan data (CLAUDE.md §11).</p>
        </div>

        @can('employees.manage')
            <div class="flex w-fit gap-2">
                <a href="{{ route('employees.import') }}" wire:navigate>
                    <x-secondary-button type="button">Import Excel</x-secondary-button>
                </a>
                <x-primary-button wire:click="openCreate" type="button">
                    + Tambah Pegawai
                </x-primary-button>
            </div>
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

    @if ($statuses->isEmpty() || $units->isEmpty())
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p>
                @if ($units->isEmpty())
                    Belum ada <a href="{{ route('units.index') }}" wire:navigate class="font-semibold underline">Master Unit</a> aktif.
                @endif
                @if ($statuses->isEmpty())
                    Belum ada <a href="{{ route('employee-statuses.index') }}" wire:navigate class="font-semibold underline">Master Status Pegawai</a> aktif.
                @endif
                Pegawai tetap bisa ditambah tanpa unit/status (isi belakangan), tapi sebaiknya dilengkapi dulu.
            </p>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari NIP atau nama..."
                class="w-full max-w-xs rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >

            <select wire:model.live="filterUnit" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Unit</option>
                @foreach ($allUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterStatus" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Status Pegawai</option>
                @foreach ($allStatuses as $status)
                    <option value="{{ $status->id }}">{{ $status->nama }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterGolongan" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Golongan</option>
                @foreach ($allGolongans as $golongan)
                    <option value="{{ $golongan->id }}">{{ $golongan->nama }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterJabatanFungsional" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Jab. Fungsional</option>
                @foreach ($allJabatanFungsionals as $jabatan)
                    <option value="{{ $jabatan->id }}">{{ $jabatan->nama }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterAktif" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3 font-medium">NIP</th>
                        <th class="px-5 py-3 font-medium">Nama</th>
                        <th class="px-5 py-3 font-medium">Unit</th>
                        <th class="px-5 py-3 font-medium">Status Pegawai</th>
                        <th class="px-5 py-3 font-medium">Golongan</th>
                        <th class="px-5 py-3 font-medium">Jab. Fungsional</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($employees as $employee)
                        <tr wire:key="employee-{{ $employee->id }}">
                            <td class="px-5 py-3 font-mono text-xs font-medium text-slate-700 dark:text-slate-300">{{ $employee->nip }}</td>
                            <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $employee->nama }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $employee->unit?->nama_unit ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $employee->employeeStatus?->nama ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $employee->golongan?->nama ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $employee->jabatanFungsional?->nama ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($employee->status_aktif)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @can('employees.manage')
                                    <div class="flex justify-end gap-1.5">
                                        <button wire:click="toggleActive({{ $employee->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                            {{ $employee->status_aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                        <button wire:click="openEdit({{ $employee->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                            Edit
                                        </button>
                                        <button wire:click="delete({{ $employee->id }})" wire:confirm="Hapus pegawai ini?" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                            Hapus
                                        </button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data pegawai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($employees->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $employees->links() }}
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
            class="relative mx-auto mb-6 w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900"
        >
            <form wire:submit="save" class="max-h-[85vh] overflow-y-auto p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $editingId ? 'Edit Pegawai' : 'Tambah Pegawai' }}
                </h2>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Identitas</p>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="nip" value="NIP" />
                        <x-text-input wire:model="nip" id="nip" type="text" inputmode="numeric" class="mt-1 block w-full" placeholder="18 digit" />
                        <x-input-error class="mt-2" :messages="$errors->get('nip')" />
                    </div>

                    <div>
                        <x-input-label for="nik" value="NIK (opsional)" />
                        <x-text-input wire:model="nik" id="nik" type="text" inputmode="numeric" class="mt-1 block w-full" placeholder="16 digit" />
                        <x-input-error class="mt-2" :messages="$errors->get('nik')" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="nama" value="Nama" />
                        <x-text-input wire:model="nama" id="nama" type="text" class="mt-1 block w-full" placeholder="Nama lengkap dengan gelar" />
                        <x-input-error class="mt-2" :messages="$errors->get('nama')" />
                    </div>
                </div>

                <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Kepegawaian</p>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="unit_id" value="Unit" />
                        <select wire:model="unit_id" id="unit_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Pilih Unit —</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('unit_id')" />
                    </div>

                    <div>
                        <x-input-label for="employee_status_id" value="Status Pegawai" />
                        <select wire:model="employee_status_id" id="employee_status_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Pilih Status —</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('employee_status_id')" />
                    </div>

                    <div>
                        <x-input-label for="golongan_id" value="Golongan (opsional)" />
                        <select wire:model="golongan_id" id="golongan_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Pilih Golongan —</option>
                            @foreach ($golongans as $golongan)
                                <option value="{{ $golongan->id }}">{{ $golongan->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('golongan_id')" />
                    </div>

                    <div>
                        <x-input-label for="jabatan_fungsional_id" value="Jab. Fungsional (opsional)" />
                        <select wire:model="jabatan_fungsional_id" id="jabatan_fungsional_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Pilih Jabatan Fungsional —</option>
                            @foreach ($jabatanFungsionals as $jabatan)
                                <option value="{{ $jabatan->id }}">{{ $jabatan->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('jabatan_fungsional_id')" />
                    </div>

                    <div>
                        <x-input-label for="id_simpeg" value="ID SIMPEG (opsional)" />
                        <x-text-input wire:model="id_simpeg" id="id_simpeg" type="text" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('id_simpeg')" />
                    </div>
                </div>

                <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Kontak</p>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" placeholder="nama@staff.uns.ac.id" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div>
                        <x-input-label for="no_hp" value="Nomor HP (opsional)" />
                        <x-text-input wire:model="no_hp" id="no_hp" type="text" class="mt-1 block w-full" placeholder="08xxxxxxxxxx" />
                        <x-input-error class="mt-2" :messages="$errors->get('no_hp')" />
                    </div>
                </div>

                <p class="mt-5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" /></svg>
                    Data Sensitif — hanya terlihat di sini
                </p>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Tidak pernah ditampilkan di daftar pegawai maupun ke role selain yang bisa mengelola Master Pegawai.</p>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="npwp" value="NPWP (opsional)" />
                        <x-text-input wire:model="npwp" id="npwp" type="text" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('npwp')" />
                    </div>

                    <div>
                        <x-input-label for="no_rekening" value="No. Rekening (opsional)" />
                        <x-text-input wire:model="no_rekening" id="no_rekening" type="text" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('no_rekening')" />
                    </div>

                    <div>
                        <x-input-label for="nama_rekening" value="Nama Pemilik Rekening (opsional)" />
                        <x-text-input wire:model="nama_rekening" id="nama_rekening" type="text" class="mt-1 block w-full" placeholder="Kosongkan jika sama dengan Nama" />
                        <x-input-error class="mt-2" :messages="$errors->get('nama_rekening')" />
                    </div>

                    <div>
                        <x-input-label for="bank_id" value="Bank (opsional)" />
                        <select wire:model="bank_id" id="bank_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Pilih Bank —</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('bank_id')" />
                    </div>
                </div>

                <label class="mt-5 flex items-center gap-2">
                    <input wire:model="status_aktif" type="checkbox" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Status aktif</span>
                </label>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                    <x-primary-button type="submit">Simpan</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
