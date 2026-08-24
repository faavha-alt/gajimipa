<?php

use App\Models\DeductionType;
use App\Models\SalaryPeriod;
use App\Services\Import\DeductionImportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $step = 'select-period';

    public string $periodId = '';

    public $file = null;

    public ?string $storedPath = null;

    public array $headers = [];

    public array $mapping = [];

    public array $preview = [];

    public ?int $deductionImportId = null;

    public function mount(): void
    {
        Gate::authorize('deduction_records.manage');
    }

    public function getPeriodProperty(): ?SalaryPeriod
    {
        return $this->periodId ? SalaryPeriod::find($this->periodId) : null;
    }

    public function selectPeriod(): void
    {
        $this->validate(['periodId' => ['required', 'exists:salary_periods,id']]);

        if ($this->period->status !== SalaryPeriod::STATUS_DRAFT) {
            $this->addError('periodId', 'Import potongan hanya bisa dilakukan pada periode berstatus DRAFT.');

            return;
        }

        if (! $this->period->salaryRecords()->exists()) {
            $this->addError('periodId', 'Periode ini belum punya data gaji pusat. Import Gaji Pusat dulu sebelum input potongan.');

            return;
        }

        $this->step = 'upload';
    }

    public function uploadFile(): void
    {
        Gate::authorize('deduction_records.manage');

        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $this->storedPath = $this->file->store('imports/deductions', 'local');

        $service = app(DeductionImportService::class);
        $rows = $service->readSheet(Storage::disk('local')->path($this->storedPath));

        if (empty($rows)) {
            $this->addError('file', 'File kosong atau tidak bisa dibaca.');
            Storage::disk('local')->delete($this->storedPath);

            return;
        }

        if (count($rows) - 1 > DeductionImportService::MAX_ROWS) {
            $this->addError('file', 'Maksimal '.DeductionImportService::MAX_ROWS.' baris data per import.');
            Storage::disk('local')->delete($this->storedPath);

            return;
        }

        $this->headers = $rows[0];
        // Tebak pemetaan awal (termasuk kolom Jenis Potongan, dicocokkan ke
        // Master Jenis Potongan yang sudah ada) — operator tetap cek & koreksi
        // di langkah berikutnya, ini cuma isian awal supaya tidak perlu pilih
        // manual satu-satu dari 15+ kolom.
        $this->mapping = app(DeductionImportService::class)->guessMapping($this->headers, DeductionType::where('status_aktif', true)->get());
        $this->step = 'mapping';
    }

    public function confirmMapping(): void
    {
        Gate::authorize('deduction_records.manage');

        if (! in_array('nip', $this->mapping, true)) {
            session()->flash('error', 'Kolom NIP wajib dipetakan sebelum lanjut.');

            return;
        }

        $rows = app(DeductionImportService::class)->readSheet(Storage::disk('local')->path($this->storedPath));
        $this->preview = app(DeductionImportService::class)->buildPreview($rows, $this->mapping, $this->period);
        $this->step = 'preview';
    }

    public function backToMapping(): void
    {
        $this->step = 'mapping';
        $this->preview = [];
    }

    public function confirmImport(): void
    {
        Gate::authorize('deduction_records.manage');

        $deductionTypeIds = collect($this->mapping)
            ->filter(fn ($v) => str_starts_with((string) $v, 'type:'))
            ->map(fn ($v) => (int) Str::after($v, 'type:'))
            ->values()
            ->all();

        try {
            $deductionImport = app(DeductionImportService::class)->import(
                $this->period,
                $this->preview,
                $deductionTypeIds,
                $this->file->getClientOriginalName(),
                $this->storedPath,
                auth()->user(),
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->deductionImportId = $deductionImport->id;
        $this->step = 'done';
    }

    public function backToUpload(): void
    {
        if ($this->storedPath && Storage::disk('local')->exists($this->storedPath)) {
            Storage::disk('local')->delete($this->storedPath);
        }

        $this->reset(['file', 'storedPath', 'headers', 'mapping', 'preview']);
        $this->step = 'upload';
    }

    public function restart(): void
    {
        if ($this->step !== 'done' && $this->storedPath && Storage::disk('local')->exists($this->storedPath)) {
            Storage::disk('local')->delete($this->storedPath);
        }

        $this->reset(['step', 'periodId', 'file', 'storedPath', 'headers', 'mapping', 'preview', 'deductionImportId']);
    }

    public function with(): array
    {
        return [
            'deductionTypes' => DeductionType::where('status_aktif', true)->orderBy('nama')->get(),
            'errorCount' => collect($this->preview)->filter(fn ($row) => ! empty($row['errors']))->count(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('deduction-records.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke Data Potongan
        </a>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Import Potongan Fakultas</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Satu file bisa berisi banyak jenis potongan sekaligus (format wide) — petakan tiap kolom ke Jenis Potongan yang sesuai.</p>
    </div>

    <div class="flex items-center gap-2 text-xs font-semibold">
        @foreach (['select-period' => '1. Pilih Periode', 'upload' => '2. Upload', 'mapping' => '3. Petakan Kolom', 'preview' => '4. Preview', 'done' => '5. Selesai'] as $key => $label)
            <span {{ $step === $key ? 'aria-current="step"' : '' }} class="rounded-full px-3 py-1.5 {{ $step === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                {{ $label }}
            </span>
            @if ($key !== 'done')
                <span class="text-slate-300 dark:text-slate-700">→</span>
            @endif
        @endforeach
    </div>

    <x-flash :error="session('error')" />

    {{-- STEP 1: Pilih Periode --}}
    @if ($step === 'select-period')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @if ($deductionTypes->isEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                    Belum ada <a href="{{ route('deduction-types.index') }}" wire:navigate class="font-semibold underline">Master Jenis Potongan</a> aktif. Tambahkan dulu supaya kolom di file bisa dipetakan.
                </div>
            @else
                <x-input-label for="periodId" value="Periode Gaji (harus DRAFT, sudah punya data gaji pusat)" />
                <select wire:model.live="periodId" id="periodId" class="mt-2 block w-full max-w-sm rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="">— Pilih Periode —</option>
                    @foreach (\App\Models\SalaryPeriod::where('status', 'DRAFT')->orderByDesc('tahun')->orderByDesc('bulan')->get() as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_periode }} (v{{ $p->versi }})</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('periodId')" />

                <div class="mt-6">
                    <x-primary-button type="button" wire:click="selectPeriod">Lanjut</x-primary-button>
                </div>
            @endif
        </div>
    @endif

    {{-- STEP 2: Upload --}}
    @if ($step === 'upload')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">Periode: <strong class="text-slate-700 dark:text-slate-200">{{ $this->period?->nama_periode }}</strong></p>

            <form wire:submit="uploadFile" class="mt-4">
                <x-input-label for="file" value="File Excel/CSV Potongan Fakultas (maks. 10MB)" />
                <input wire:model="file" id="file" type="file" accept=".xlsx,.xls,.csv" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-slate-300 dark:file:bg-indigo-500/10 dark:file:text-indigo-300">
                <x-input-error class="mt-2" :messages="$errors->get('file')" />

                <div wire:loading wire:target="file" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Mengunggah...</div>

                <div class="mt-6 flex gap-2">
                    <x-secondary-button type="button" wire:click="restart">Batal</x-secondary-button>
                    <x-primary-button type="submit">Lanjut</x-primary-button>
                </div>
            </form>
        </div>
    @endif

    {{-- STEP 3: Mapping --}}
    @if ($step === 'mapping')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">Tentukan kolom NIP (wajib, satu kolom), lalu petakan tiap kolom nominal ke Jenis Potongan yang sesuai. Kolom lain (Nama, Jumlah, Gaji Kotor, dst.) bisa diabaikan. Pemetaan di bawah sudah ditebak otomatis dari nama kolom — cek ulang sebelum lanjut.</p>

            <div class="mt-4 space-y-3">
                @foreach ($headers as $index => $header)
                    <div class="flex items-center gap-3">
                        <span class="w-48 shrink-0 truncate rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ $header !== '' ? $header : '(kolom '.($index + 1).')' }}
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                        <select wire:model="mapping.{{ $index }}" class="flex-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="ignore">— Abaikan —</option>
                            <option value="nip">NIP (identifier pegawai)</option>
                            <option value="nama">Nama (bantuan tampilan saja)</option>
                            <optgroup label="Jenis Potongan">
                                @foreach ($deductionTypes as $type)
                                    <option value="type:{{ $type->id }}">{{ $type->nama }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-between">
                <x-secondary-button type="button" wire:click="restart">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="confirmMapping">Lihat Preview</x-primary-button>
            </div>
        </div>
    @endif

    {{-- STEP 4: Preview --}}
    @if ($step === 'preview')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ count($preview) }} baris pegawai
                </span>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    Total Potongan: Rp {{ number_format(collect($preview)->sum('total'), 0, ',', '.') }}
                </span>
                @if ($errorCount > 0)
                    <span class="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ $errorCount }} baris error
                    </span>
                @endif
            </div>

            @if ($errorCount > 0)
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                    Masih ada baris berisi error — import tidak bisa dikonfirmasi sampai semua baris lolos (all-or-nothing).
                </div>
            @endif

            <div class="mt-4 max-h-[28rem] overflow-auto rounded-xl border border-slate-100 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-medium">Baris</th>
                            <th scope="col" class="px-4 py-2 font-medium">NIP</th>
                            <th scope="col" class="px-4 py-2 font-medium">Nama</th>
                            <th scope="col" class="px-4 py-2 font-medium text-right">Total Potongan</th>
                            <th scope="col" class="px-4 py-2 font-medium">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($preview as $row)
                            <tr class="{{ ! empty($row['errors']) ? 'bg-rose-50/50 dark:bg-rose-500/5' : '' }}">
                                <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ $row['row_number'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row['nip'] }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $row['nama_tampil'] }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-700 dark:text-slate-200">{{ number_format($row['total'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-xs text-rose-600 dark:text-rose-400">{{ implode(' ', $row['errors']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-between">
                <div class="flex gap-2">
                    <x-secondary-button type="button" wire:click="restart">Batal</x-secondary-button>
                    <x-secondary-button type="button" wire:click="backToMapping">← Ubah Pemetaan</x-secondary-button>
                </div>
                @if ($errorCount > 0)
                    <x-secondary-button type="button" disabled>Konfirmasi Import</x-secondary-button>
                @else
                    <x-primary-button
                        type="button"
                        wire:click="confirmImport"
                        wire:confirm="Impor data potongan ini ke periode? Data yang sudah diimpor tidak dapat dikembalikan."
                        wire:loading.attr="disabled"
                        wire:target="confirmImport"
                    >
                        <span wire:loading.remove wire:target="confirmImport">Konfirmasi Import</span>
                        <span wire:loading wire:target="confirmImport">Mengimpor…</span>
                    </x-primary-button>
                @endif
            </div>
        </div>
    @endif

    {{-- STEP 5: Done --}}
    @if ($step === 'done')
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
            <svg class="mx-auto h-10 w-10 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l1.5 1.5 4.5-4.5m5 2a8 8 0 11-16 0 8 8 0 0116 0z" /></svg>
            <p class="mt-3 text-lg font-semibold text-emerald-800 dark:text-emerald-300">Import potongan selesai</p>
            <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                Data potongan untuk {{ count($preview) }} pegawai berhasil diimpor ke periode {{ $this->period?->nama_periode }}.
            </p>

            <div class="mt-6 flex justify-center gap-2">
                <x-secondary-button type="button" wire:click="restart">Import Lagi</x-secondary-button>
                <x-primary-button href="{{ route('deduction-records.index', ['periodId' => $periodId]) }}" wire:navigate>Lihat Data Potongan</x-primary-button>
            </div>
        </div>
    @endif
</div>
