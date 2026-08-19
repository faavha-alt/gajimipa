<?php

use App\Services\Import\EmployeeImportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $step = 'upload';

    public $file = null;

    public ?string $storedPath = null;

    public array $headers = [];

    public array $mapping = [];

    public array $preview = [];

    public array $result = [];

    public function mount(): void
    {
        Gate::authorize('employees.manage');
    }

    public function uploadFile(): void
    {
        Gate::authorize('employees.manage');

        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $this->storedPath = $this->file->store('imports/employees', 'local');

        $rows = app(EmployeeImportService::class)->readSheet(Storage::disk('local')->path($this->storedPath));

        if (empty($rows)) {
            $this->addError('file', 'File kosong atau tidak bisa dibaca.');
            $this->cleanupFile();

            return;
        }

        if (count($rows) - 1 > EmployeeImportService::MAX_ROWS) {
            $this->addError('file', 'Maksimal '.EmployeeImportService::MAX_ROWS.' baris data per import.');
            $this->cleanupFile();

            return;
        }

        $this->headers = $rows[0];
        $this->mapping = $this->guessMapping($this->headers);
        $this->step = 'mapping';
    }

    private function guessMapping(array $headers): array
    {
        $aliases = [
            'nip' => ['nip'],
            'nama' => ['nama', 'nama pegawai', 'name'],
            'nik' => ['nik'],
            'unit' => ['unit', 'prodi', 'kode unit'],
            'status_pegawai' => ['status pegawai', 'status'],
            'email' => ['email', 'e-mail'],
            'no_hp' => ['no hp', 'nomor hp', 'no telp', 'telepon', 'hp'],
            'kode_npp_fakultas' => ['npp', 'kode npp fakultas', 'npp fakultas'],
            'id_simpeg' => ['id simpeg', 'simpeg'],
            'npwp' => ['npwp'],
            'no_rekening' => ['no rekening', 'rekening', 'no. rekening'],
            'status_aktif' => ['status aktif', 'aktif'],
        ];

        $mapping = [];
        foreach ($headers as $index => $header) {
            $normalized = Str::lower(trim((string) $header));
            $mapping[$index] = 'ignore';
            foreach ($aliases as $target => $candidates) {
                if (in_array($normalized, $candidates, true)) {
                    $mapping[$index] = $target;
                    break;
                }
            }
        }

        return $mapping;
    }

    public function confirmMapping(): void
    {
        Gate::authorize('employees.manage');

        if (! in_array('nip', $this->mapping, true) || ! in_array('nama', $this->mapping, true)) {
            session()->flash('error', 'Kolom NIP dan Nama wajib dipetakan sebelum lanjut.');

            return;
        }

        $rows = app(EmployeeImportService::class)->readSheet(Storage::disk('local')->path($this->storedPath));
        $this->preview = app(EmployeeImportService::class)->buildPreview($rows, $this->mapping);
        $this->step = 'preview';
    }

    public function backToMapping(): void
    {
        $this->step = 'mapping';
        $this->preview = [];
    }

    public function confirmImport(): void
    {
        Gate::authorize('employees.manage');

        try {
            $this->result = app(EmployeeImportService::class)->import($this->preview, auth()->user());
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->cleanupFile();
        $this->step = 'done';
    }

    public function restart(): void
    {
        $this->cleanupFile();
        $this->reset(['step', 'file', 'storedPath', 'headers', 'mapping', 'preview', 'result']);
    }

    private function cleanupFile(): void
    {
        if ($this->storedPath && Storage::disk('local')->exists($this->storedPath)) {
            Storage::disk('local')->delete($this->storedPath);
        }
    }

    public function with(): array
    {
        return [
            'targetFields' => EmployeeImportService::TARGET_FIELDS,
            'errorCount' => collect($this->preview)->filter(fn ($row) => ! empty($row['errors']))->count(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('employees.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke Master Pegawai
        </a>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Import Master Pegawai</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upload → Petakan Kolom → Periksa Preview → Konfirmasi. All-or-nothing: satu baris error menahan seluruh import.</p>
    </div>

    {{-- Step indicator --}}
    <div class="flex items-center gap-2 text-xs font-semibold">
        @foreach (['upload' => '1. Upload', 'mapping' => '2. Petakan Kolom', 'preview' => '3. Preview', 'done' => '4. Selesai'] as $key => $label)
            <span class="rounded-full px-3 py-1.5 {{ $step === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500' }}">
                {{ $label }}
            </span>
            @if ($key !== 'done')
                <span class="text-slate-300 dark:text-slate-700">→</span>
            @endif
        @endforeach
    </div>

    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- STEP 1: Upload --}}
    @if ($step === 'upload')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form wire:submit="uploadFile">
                <x-input-label for="file" value="File Excel/CSV (maks. 5MB, .xlsx/.xls/.csv)" />
                <input wire:model="file" id="file" type="file" accept=".xlsx,.xls,.csv" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-slate-300 dark:file:bg-indigo-500/10 dark:file:text-indigo-300">
                <x-input-error class="mt-2" :messages="$errors->get('file')" />

                <div wire:loading wire:target="file" class="mt-2 text-xs text-slate-400">Mengunggah...</div>

                <div class="mt-6">
                    <x-primary-button type="submit">Lanjut</x-primary-button>
                </div>
            </form>
        </div>
    @endif

    {{-- STEP 2: Mapping --}}
    @if ($step === 'mapping')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">Tentukan kolom mana di file yang sesuai dengan field sistem. Kolom yang tidak relevan bisa dibiarkan "Abaikan".</p>

            <div class="mt-4 space-y-3">
                @foreach ($headers as $index => $header)
                    <div class="flex items-center gap-3">
                        <span class="w-48 shrink-0 truncate rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ $header !== '' ? $header : '(kolom '.($index + 1).')' }}
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                        <select wire:model="mapping.{{ $index }}" class="flex-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="ignore">— Abaikan —</option>
                            @foreach ($targetFields as $field => $label)
                                <option value="{{ $field }}">{{ $label }}</option>
                            @endforeach
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

    {{-- STEP 3: Preview --}}
    @if ($step === 'preview')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ count($preview) }} baris total
                </span>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    {{ collect($preview)->where('action', 'create')->count() }} akan dibuat
                </span>
                <span class="rounded-full bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                    {{ collect($preview)->where('action', 'update')->count() }} akan diperbarui
                </span>
                @if ($errorCount > 0)
                    <span class="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ $errorCount }} baris error
                    </span>
                @endif
            </div>

            @if ($errorCount > 0)
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                    Masih ada baris berisi error — import tidak bisa dikonfirmasi sampai semua baris lolos (all-or-nothing). Perbaiki file lalu upload ulang, atau sesuaikan pemetaan kolom.
                </div>
            @endif

            <div class="mt-4 max-h-[28rem] overflow-auto rounded-xl border border-slate-100 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">Baris</th>
                            <th class="px-4 py-2 font-medium">NIP</th>
                            <th class="px-4 py-2 font-medium">Nama</th>
                            <th class="px-4 py-2 font-medium">Aksi</th>
                            <th class="px-4 py-2 font-medium">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($preview as $row)
                            <tr class="{{ ! empty($row['errors']) ? 'bg-rose-50/50 dark:bg-rose-500/5' : '' }}">
                                <td class="px-4 py-2 text-slate-400">{{ $row['row_number'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row['data']['nip'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $row['data']['nama'] ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @if (! empty($row['errors']))
                                        <span class="text-xs font-semibold text-rose-600 dark:text-rose-400">Error</span>
                                    @elseif ($row['action'] === 'update')
                                        <span class="text-xs font-semibold text-sky-600 dark:text-sky-400">Perbarui</span>
                                    @else
                                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Baru</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-rose-600 dark:text-rose-400">
                                    {{ implode(' ', $row['errors']) }}
                                </td>
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
                    <x-primary-button type="button" disabled>Konfirmasi Import</x-primary-button>
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
            <p class="mt-3 text-lg font-semibold text-emerald-800 dark:text-emerald-300">Import selesai</p>
            <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                {{ $result['created'] ?? 0 }} pegawai baru ditambahkan, {{ $result['updated'] ?? 0 }} pegawai diperbarui.
            </p>

            <div class="mt-6 flex justify-center gap-2">
                <x-secondary-button type="button" wire:click="restart">Import Lagi</x-secondary-button>
                <a href="{{ route('employees.index') }}" wire:navigate>
                    <x-primary-button type="button">Lihat Master Pegawai</x-primary-button>
                </a>
            </div>
        </div>
    @endif
</div>
