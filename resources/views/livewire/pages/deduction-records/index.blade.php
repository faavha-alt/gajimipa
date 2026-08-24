<?php

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Services\Deduction\RecurringDeductionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showTerapkanModal = false;
    use WithPagination;

    #[Url]
    public string $periodId = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $employeeId = '';

    public string $deductionTypeId = '';

    public string $nominal = '';

    public string $keterangan = '';

    public function mount(): void
    {
        Gate::authorize('deduction_records.view');

        if (! $this->periodId) {
            $latest = SalaryPeriod::orderByDesc('tahun')->orderByDesc('bulan')->orderByDesc('versi')->first();
            $this->periodId = $latest ? (string) $latest->id : '';
        }
    }

    public function getPeriodProperty(): ?SalaryPeriod
    {
        return $this->periodId ? SalaryPeriod::find($this->periodId) : null;
    }

    public function updatingPeriodId(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('deduction_records.manage');

        $this->reset(['editingId', 'employeeId', 'deductionTypeId', 'nominal', 'keterangan']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('deduction_records.manage');

        $record = DeductionRecord::with('salaryRecord')->findOrFail($id);
        $this->editingId = $record->id;
        $this->employeeId = (string) $record->salaryRecord->employee_id;
        $this->deductionTypeId = (string) $record->deduction_type_id;
        $this->nominal = (string) $record->nominal;
        $this->keterangan = (string) $record->keterangan;
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('deduction_records.manage');

        if ($this->period?->status !== SalaryPeriod::STATUS_DRAFT) {
            session()->flash('error', 'Potongan hanya bisa ditambah/diubah selama periode berstatus DRAFT.');
            $this->showModal = false;

            return;
        }

        $validated = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'deductionTypeId' => ['required', 'exists:deduction_types,id'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $salaryRecord = SalaryRecord::where('salary_period_id', $this->periodId)
            ->where('employee_id', $validated['employeeId'])
            ->first();

        if (! $salaryRecord) {
            $this->addError('employeeId', 'Pegawai ini belum punya data gaji pusat di periode ini.');

            return;
        }

        DeductionRecord::updateOrCreate(
            ['id' => $this->editingId],
            [
                'salary_record_id' => $salaryRecord->id,
                'deduction_type_id' => $validated['deductionTypeId'],
                'nominal' => $validated['nominal'],
                'keterangan' => $validated['keterangan'] ?: null,
                'sumber' => DeductionRecord::SUMBER_MANUAL,
                'dibuat_oleh' => auth()->id(),
            ]
        );

        $this->showModal = false;
        session()->flash('status', $this->editingId ? 'Potongan berhasil diperbarui.' : 'Potongan manual berhasil ditambahkan.');
        $this->reset(['editingId', 'employeeId', 'deductionTypeId', 'nominal', 'keterangan']);
    }

    public function delete(int $id): void
    {
        Gate::authorize('deduction_records.manage');

        $record = DeductionRecord::findOrFail($id);

        if ($record->salaryRecord->salaryPeriod->status !== SalaryPeriod::STATUS_DRAFT) {
            session()->flash('error', 'Potongan hanya bisa dihapus selama periode berstatus DRAFT.');

            return;
        }

        $record->delete();
        session()->flash('status', 'Potongan berhasil dihapus.');
    }

    public function bukaTerapkanModal(): void
    {
        Gate::authorize('recurring_deductions.manage');
        $this->showTerapkanModal = true;
    }

    public function konfirmasiTerapkan(): void
    {
        Gate::authorize('recurring_deductions.manage');

        $service = app(RecurringDeductionService::class);

        if (! $service->bisaTerapkan($this->period)) {
            session()->flash('error', 'Potongan berulang hanya bisa diterapkan ke periode berstatus DRAFT.');
            $this->showTerapkanModal = false;

            return;
        }

        $hasil = $service->terapkan($this->period, auth()->user());

        $this->showTerapkanModal = false;
        session()->flash('status', "{$hasil['jumlah']} potongan berulang berhasil diterapkan.");
    }

    public function with(): array
    {
        $baseQuery = fn () => DeductionRecord::query()
            ->whereHas('salaryRecord', fn ($q) => $q->where('salary_period_id', $this->periodId));

        return [
            'periods' => SalaryPeriod::orderByDesc('tahun')->orderByDesc('bulan')->orderByDesc('versi')->get(),
            'records' => $baseQuery()->with(['salaryRecord.employee', 'deductionType'])->orderBy('id')->paginate(20),
            'totalNominal' => $this->periodId ? $baseQuery()->sum('nominal') : 0,
            'deductionTypes' => DeductionType::where('status_aktif', true)->orderBy('nama')->get(),
            'eligibleEmployees' => $this->periodId
                ? Employee::whereHas('salaryRecords', fn ($q) => $q->where('salary_period_id', $this->periodId))->orderBy('nama')->get()
                : collect(),
            'previewBerulang' => ($this->showTerapkanModal && $this->period)
                ? app(RecurringDeductionService::class)->preview($this->period)
                : collect(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Data Potongan</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Potongan fakultas per pegawai per periode — dari import Excel atau input manual (CLAUDE.md §13/§14).</p>
        </div>

        <div class="flex w-fit flex-wrap gap-2">
            @can('recurring_deductions.manage')
                @if ($this->period?->status === 'DRAFT')
                    <x-secondary-button wire:click="bukaTerapkanModal" type="button">Terapkan Potongan Berulang</x-secondary-button>
                @endif
            @endcan
            @can('deduction_records.manage')
                <x-secondary-button href="{{ route('deduction-records.import') }}" wire:navigate>Import Excel</x-secondary-button>
                <x-primary-button wire:click="openCreate" type="button">
                    + Tambah Manual
                </x-primary-button>
            @endcan
        </div>
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
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
            <select wire:model.live="periodId" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">— Pilih Periode —</option>
                @foreach ($periods as $p)
                    <option value="{{ $p->id }}">{{ $p->nama_periode }} (v{{ $p->versi }}, {{ $p->status }})</option>
                @endforeach
            </select>

            @if ($this->period)
                <span class="text-sm text-slate-500 dark:text-slate-400">
                    Total: Rp{{ number_format($totalNominal, 0, ',', '.') }}
                </span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">NIP</th>
                        <th scope="col" class="px-5 py-3 font-medium">Nama</th>
                        <th scope="col" class="px-5 py-3 font-medium">Jenis Potongan</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Nominal</th>
                        <th scope="col" class="px-5 py-3 font-medium">Sumber</th>
                        <th scope="col" class="px-5 py-3 font-medium">Keterangan</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($records as $record)
                        <tr wire:key="record-{{ $record->id }}">
                            <td class="px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $record->salaryRecord->employee->nip ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $record->salaryRecord->employee->nama ?? $record->salaryRecord->nama_snapshot }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $record->deductionType->nama }}</td>
                            <td class="px-5 py-3 text-right font-medium text-slate-700 dark:text-slate-200">{{ number_format($record->nominal, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                @if ($record->sumber === 'MANUAL')
                                    <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Manual</span>
                                @elseif ($record->sumber === 'BERULANG')
                                    <span class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">Berulang</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Import</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $record->keterangan ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @can('deduction_records.manage')
                                    @if ($this->period?->status === 'DRAFT')
                                        <div class="flex justify-end gap-1.5">
                                            <button wire:click="openEdit({{ $record->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                                Edit
                                            </button>
                                            <button wire:click="delete({{ $record->id }})" wire:confirm="Hapus potongan ini?" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                                Hapus
                                            </button>
                                        </div>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                @if (! $periodId)
                                    Pilih periode dulu.
                                @else
                                    Belum ada data potongan untuk periode ini.
                                @endif
                            </td>
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
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $editingId ? 'Edit Potongan' : 'Tambah Potongan Manual' }}
                </h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="employeeId" value="Pegawai" />
                        <select wire:model="employeeId" id="employeeId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Pilih Pegawai —</option>
                            @foreach ($eligibleEmployees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->nip }} — {{ $employee->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('employeeId')" />
                    </div>

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
                        <x-input-label for="nominal" value="Nominal" />
                        <x-text-input wire:model="nominal" id="nominal" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('nominal')" />
                    </div>

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
        </div>
    </div>

    <div x-data="{ show: @entangle('showTerapkanModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" x-on:click="show = false"></div>

        <div
            x-show="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            class="relative mx-auto mb-6 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Terapkan Potongan Berulang</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Baris berikut akan ditambahkan ke Data Potongan periode {{ $this->period?->nama_periode }}.</p>

                <div class="mt-4 max-h-96 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-medium">Pegawai</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Jenis</th>
                                <th scope="col" class="px-4 py-2.5 font-medium text-right">Nominal</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($previewBerulang as $row)
                                <tr class="{{ $row['bisa_diterapkan'] ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $row['nama'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $row['jenis'] }}</td>
                                    <td class="px-4 py-2.5 text-right text-slate-600 dark:text-slate-300">
                                        {{ $row['nominal'] !== null ? 'Rp'.number_format($row['nominal'], 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs">
                                        @if ($row['bisa_diterapkan'])
                                            <span class="text-slate-500 dark:text-slate-400">{{ $row['catatan'] ?? 'Siap diterapkan' }}</span>
                                        @else
                                            <span class="font-medium text-amber-600 dark:text-amber-400">Dilewati — {{ $row['alasan_dilewati'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Belum ada potongan berulang yang aktif.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                    <x-primary-button
                        wire:click="konfirmasiTerapkan"
                        wire:confirm="Terapkan potongan berulang ini ke periode? Aksi ini idempotent — klik berulang tidak membuat data ganda."
                        wire:loading.attr="disabled"
                        wire:target="konfirmasiTerapkan"
                        type="button"
                    >
                        <span wire:loading.remove wire:target="konfirmasiTerapkan">Terapkan ({{ $previewBerulang->where('bisa_diterapkan', true)->count() }})</span>
                        <span wire:loading wire:target="konfirmasiTerapkan">Menerapkan…</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>
</div>
