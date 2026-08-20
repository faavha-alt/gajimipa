<?php

use App\Models\SalaryPeriod;
use App\Services\Import\SalaryImportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

    public array $columnMap = [];

    public array $preview = [];

    public ?string $templateCode = null;

    public ?string $templateLabel = null;

    public ?int $salaryImportId = null;

    public function mount(): void
    {
        Gate::authorize('salary_imports.manage');
    }

    public function getPeriodProperty(): ?SalaryPeriod
    {
        return $this->periodId ? SalaryPeriod::find($this->periodId) : null;
    }

    public function selectPeriod(): void
    {
        $this->validate(['periodId' => ['required', 'exists:salary_periods,id']]);

        if ($this->period->status !== SalaryPeriod::STATUS_DRAFT) {
            $this->addError('periodId', 'Import gaji pusat hanya bisa dilakukan pada periode berstatus DRAFT.');

            return;
        }

        $this->step = 'upload';
    }

    public function clearAndRestart(): void
    {
        Gate::authorize('salary_imports.manage');

        app(SalaryImportService::class)->clearPeriodData($this->period, auth()->user());
        session()->flash('status', 'Data gaji pusat periode ini sudah dihapus. Silakan upload ulang.');
    }

    public function uploadFile(): void
    {
        Gate::authorize('salary_imports.manage');

        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $this->storedPath = $this->file->store('imports/salary', 'local');

        $service = app(SalaryImportService::class);
        $rows = $service->readSheet(Storage::disk('local')->path($this->storedPath));

        if (empty($rows)) {
            $this->addError('file', 'File kosong atau tidak bisa dibaca.');
            Storage::disk('local')->delete($this->storedPath);

            return;
        }

        if (count($rows) - 1 > SalaryImportService::MAX_ROWS) {
            $this->addError('file', 'Maksimal '.SalaryImportService::MAX_ROWS.' baris data per import.');
            Storage::disk('local')->delete($this->storedPath);

            return;
        }

        $structure = $service->detectStructure($rows[0]);
        if (! $structure['ok']) {
            $this->addError('file', 'Struktur file tidak dikenali sebagai format Gaji Pusat yang didukung (PNS maupun Non-PNS). Kolom wajib yang tidak ditemukan: '.implode(', ', $structure['missing']).'.');
            Storage::disk('local')->delete($this->storedPath);

            return;
        }

        $this->columnMap = $structure['columnMap'];
        $this->templateCode = $structure['template']->code();
        $this->templateLabel = $structure['template']->label();
        $this->preview = $service->buildPreview($rows, $this->columnMap, $this->period, $structure['template']);
        $this->step = 'preview';
    }

    public function confirmImport(): void
    {
        Gate::authorize('salary_imports.manage');

        $service = app(SalaryImportService::class);

        try {
            $salaryImport = $service->import(
                $this->period,
                $this->preview,
                $this->file->getClientOriginalName(),
                $this->storedPath,
                auth()->user(),
                $service->templateByCode($this->templateCode),
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->salaryImportId = $salaryImport->id;
        $this->step = 'done';
    }

    public function backToUpload(): void
    {
        if ($this->storedPath && Storage::disk('local')->exists($this->storedPath)) {
            Storage::disk('local')->delete($this->storedPath);
        }

        $this->reset(['file', 'storedPath', 'columnMap', 'preview', 'templateCode', 'templateLabel']);
        $this->step = 'upload';
    }

    public function restart(): void
    {
        if ($this->step !== 'done' && $this->storedPath && Storage::disk('local')->exists($this->storedPath)) {
            Storage::disk('local')->delete($this->storedPath);
        }

        $this->reset(['step', 'periodId', 'file', 'storedPath', 'columnMap', 'preview', 'templateCode', 'templateLabel', 'salaryImportId']);
    }

    public function with(): array
    {
        return [
            'draftPeriods' => SalaryPeriod::where('status', SalaryPeriod::STATUS_DRAFT)->orderByDesc('tahun')->orderByDesc('bulan')->get(),
            'errorCount' => collect($this->preview)->filter(fn ($row) => ! empty($row['errors']))->count(),
            'pegawaiBaruCount' => collect($this->preview)->filter(fn ($row) => $row['pegawai_baru'] ?? false)->count(),
            'periodHasData' => $this->period?->salaryRecords()->exists() ?? false,
            'jumlahDataSaatIni' => $this->period?->salaryRecords()->count() ?? 0,
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('salary-periods.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke Periode Gaji
        </a>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Import Gaji Pusat</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Mendukung format PNS (GPP Pusat) maupun Non-PNS (Universitas) — format otomatis terdeteksi dari header file. Data bersifat snapshot — tidak diedit manual setelah masuk (CLAUDE.md §3.1).</p>
    </div>

    <div class="flex items-center gap-2 text-xs font-semibold">
        @foreach (['select-period' => '1. Pilih Periode', 'upload' => '2. Upload', 'preview' => '3. Preview', 'done' => '4. Selesai'] as $key => $label)
            <span class="rounded-full px-3 py-1.5 {{ $step === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500' }}">
                {{ $label }}
            </span>
            @if ($key !== 'done')
                <span class="text-slate-300 dark:text-slate-700">→</span>
            @endif
        @endforeach
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

    {{-- STEP 1: Pilih Periode --}}
    @if ($step === 'select-period')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @if ($draftPeriods->isEmpty())
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Tidak ada periode berstatus DRAFT. <a href="{{ route('salary-periods.index') }}" wire:navigate class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Buat periode baru</a> dulu.
                </p>
            @else
                <x-input-label for="periodId" value="Periode Gaji (harus DRAFT)" />
                <select wire:model.live="periodId" id="periodId" class="mt-2 block w-full max-w-sm rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="">— Pilih Periode —</option>
                    @foreach ($draftPeriods as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_periode }} (v{{ $p->versi }})</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('periodId')" />

                @if ($periodId && $periodHasData)
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300">
                        Periode ini sudah punya {{ $jumlahDataSaatIni }} data gaji dari import sebelumnya. File baru akan <strong>ditambahkan</strong> (bukan menggantikan) — cocok kalau mengimpor PNS &amp; Non-PNS lewat 2 file terpisah. Pegawai yang sudah ada datanya akan ditolak kalau muncul lagi di file baru.
                        <div class="mt-2">
                            <x-secondary-button type="button" wire:click="clearAndRestart" wire:confirm="Yakin hapus seluruh data gaji pusat periode ini (semua batch, bukan cuma yang terakhir)? Tindakan ini tidak bisa dibatalkan.">
                                Hapus Semua Data &amp; Mulai Ulang
                            </x-secondary-button>
                        </div>
                    </div>
                @endif

                <div class="mt-6">
                    @if ($periodId)
                        <x-primary-button type="button" wire:click="selectPeriod">Lanjut</x-primary-button>
                    @else
                        <x-secondary-button type="button" disabled>Lanjut</x-secondary-button>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- STEP 2: Upload --}}
    @if ($step === 'upload')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">Periode: <strong class="text-slate-700 dark:text-slate-200">{{ $this->period?->nama_periode }}</strong></p>

            <form wire:submit="uploadFile" class="mt-4">
                <x-input-label for="file" value="File Excel/CSV Gaji Pusat (maks. 10MB)" />
                <input wire:model="file" id="file" type="file" accept=".xlsx,.xls,.csv" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-slate-300 dark:file:bg-indigo-500/10 dark:file:text-indigo-300">
                <x-input-error class="mt-2" :messages="$errors->get('file')" />

                <div wire:loading wire:target="file" class="mt-2 text-xs text-slate-400">Mengunggah &amp; memvalidasi struktur...</div>

                <div class="mt-6 flex gap-2">
                    <x-secondary-button type="button" wire:click="restart">Batal</x-secondary-button>
                    <x-primary-button type="submit">Lanjut</x-primary-button>
                </div>
            </form>
        </div>
    @endif

    {{-- STEP 3: Preview --}}
    @if ($step === 'preview')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                    Format terdeteksi: {{ $templateLabel }}
                </span>
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ count($preview) }} baris
                </span>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    Total Penghasilan: Rp{{ number_format(collect($preview)->sum('total_penghasilan'), 0, ',', '.') }}
                </span>
                <span class="rounded-full bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                    Total Bersih Pusat: Rp{{ number_format(collect($preview)->sum('bersih_file'), 0, ',', '.') }}
                </span>
                @if ($pegawaiBaruCount > 0)
                    <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                        {{ $pegawaiBaruCount }} pegawai baru akan dibuat
                    </span>
                @endif
                @if ($errorCount > 0)
                    <span class="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ $errorCount }} baris error
                    </span>
                @endif
            </div>

            @if ($pegawaiBaruCount > 0)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                    {{ $pegawaiBaruCount }} baris punya NIP yang belum ada di Master Pegawai (ditandai "Pegawai Baru" di tabel). Kalau dikonfirmasi, pegawai ini otomatis dibuat (NIP + Nama dari file) — unit &amp; status pegawai perlu dilengkapi manual belakangan di Master Pegawai. Periksa dulu tidak ada typo NIP sebelum lanjut.
                </div>
            @endif

            @if ($errorCount > 0)
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                    Masih ada baris berisi error — import tidak bisa dikonfirmasi sampai semua baris lolos (all-or-nothing). Perbaiki file sumber lalu upload ulang.
                </div>
            @endif

            <div class="mt-4 max-h-[28rem] overflow-auto rounded-xl border border-slate-100 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">Baris</th>
                            <th class="px-4 py-2 font-medium">NIP</th>
                            <th class="px-4 py-2 font-medium">Nama</th>
                            <th class="px-4 py-2 font-medium text-right">Penghasilan Kotor</th>
                            <th class="px-4 py-2 font-medium text-right">Potongan Pusat</th>
                            <th class="px-4 py-2 font-medium text-right">Bersih</th>
                            <th class="px-4 py-2 font-medium">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($preview as $row)
                            <tr class="{{ ! empty($row['errors']) ? 'bg-rose-50/50 dark:bg-rose-500/5' : (($row['pegawai_baru'] ?? false) ? 'bg-amber-50/50 dark:bg-amber-500/5' : '') }}">
                                <td class="px-4 py-2 text-slate-400">{{ $row['row_number'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row['nip'] }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">
                                    {{ $row['nama'] }}
                                    @if ($row['pegawai_baru'] ?? false)
                                        <span class="ms-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">Pegawai Baru</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($row['total_penghasilan'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($row['total_potongan'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-700 dark:text-slate-200">{{ number_format($row['bersih_file'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-xs text-rose-600 dark:text-rose-400">{{ implode(' ', $row['errors']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-between">
                <div class="flex gap-2">
                    <x-secondary-button type="button" wire:click="restart">Batal</x-secondary-button>
                    <x-secondary-button type="button" wire:click="backToUpload">← Upload Ulang</x-secondary-button>
                </div>
                @if ($errorCount > 0)
                    <x-secondary-button type="button" disabled>Konfirmasi Import</x-secondary-button>
                @else
                    <x-primary-button type="button" wire:click="confirmImport">Konfirmasi Import</x-primary-button>
                @endif
            </div>
        </div>
    @endif

    {{-- STEP 4: Done --}}
    @if ($step === 'done')
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
            <svg class="mx-auto h-10 w-10 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l1.5 1.5 4.5-4.5m5 2a8 8 0 11-16 0 8 8 0 0116 0z" /></svg>
            <p class="mt-3 text-lg font-semibold text-emerald-800 dark:text-emerald-300">Import gaji pusat selesai</p>
            <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                {{ count($preview) }} baris data gaji berhasil diimpor untuk periode {{ $this->period?->nama_periode }}.
                @if ($pegawaiBaruCount > 0)
                    {{ $pegawaiBaruCount }} pegawai baru otomatis ditambahkan ke Master Pegawai — lengkapi unit &amp; status pegawainya.
                @endif
            </p>

            <div class="mt-6 flex justify-center gap-2">
                <x-secondary-button type="button" wire:click="restart">Import Lagi</x-secondary-button>
                <a href="{{ route('salary-periods.show', $periodId) }}" wire:navigate>
                    <x-primary-button type="button">Lihat Periode</x-primary-button>
                </a>
            </div>
        </div>
    @endif
</div>
