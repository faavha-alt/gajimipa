<?php

use App\Livewire\Base\SimpleCrud;
use App\Models\DeductionType;
use Illuminate\Support\Str;

new class extends SimpleCrud
{
    public string $kode = '';

    public string $nama = '';

    public string $keterangan = '';

    protected function permission(): string { return 'deduction_types.manage'; }

    protected function model(): string { return DeductionType::class; }

    protected function label(): string { return 'Jenis potongan'; }

    protected function formFields(): array
    {
        return ['kode' => 'kode', 'nama' => 'nama', 'keterangan' => 'keterangan'];
    }

    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'status_aktif' => ['boolean'],
        ];
    }

    protected function listKey(): string { return 'types'; }

    protected function pageSize(): int { return 15; }

    /**
     * Kode dibuat otomatis dari Nama (bukan diketik manual) — mengurangi
     * inkonsistensi penamaan antar operator. Tetap stabil sekali dibuat:
     * hanya di-generate ulang saat tambah baru, tidak saat edit nama.
     */
    public function updatedNama(string $value): void
    {
        if (! $this->editingId) {
            $this->kode = $this->generateKode($value);
        }
    }

    private function generateKode(string $nama): string
    {
        $base = Str::upper(Str::slug($nama, '_')) ?: 'JENIS_POTONGAN';
        $kode = $base;
        $suffix = 2;
        while (DeductionType::where('kode', $kode)->exists()) {
            $kode = $base.'_'.$suffix;
            $suffix++;
        }
        return $kode;
    }

    public function save(): void
    {
        \Illuminate\Support\Facades\Gate::authorize($this->permission());

        $validated = $this->validate($this->rules());

        if (! $this->editingId) {
            $this->kode = $this->generateKode($validated['nama']);
        }
        $validated['kode'] = $this->kode;
        $validated['keterangan'] = $validated['keterangan'] ?: null;

        DeductionType::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
        session()->flash('status', $this->editingId ? 'Jenis potongan berhasil diperbarui.' : 'Jenis potongan berhasil ditambahkan.');
        $this->reset(array_keys($this->formFields()));
    }

    public function delete(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize($this->permission());

        $type = DeductionType::withCount('deductionRecords')->findOrFail($id);

        if ($type->deduction_records_count > 0) {
            session()->flash('error', 'Jenis potongan "'.$type->nama.'" tidak bisa dihapus karena sudah punya riwayat potongan. Nonaktifkan saja jika sudah tidak dipakai.');

            return;
        }

        $type->delete();
        session()->flash('status', 'Jenis potongan berhasil dihapus.');
    }
};

?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Master Jenis Potongan</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Jenis potongan yang dikelola Fakultas (koperasi, iuran, dll.) — dinamis, tidak di-hardcode (CLAUDE.md §4).</p>
        </div>

        @can('deduction_types.manage')
            <x-primary-button wire:click="openCreate" type="button" class="w-fit">
                + Tambah Jenis Potongan
            </x-primary-button>
        @endcan
    </div>

    <x-flash :status="session('status')" :error="session('error')" />

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 p-4 dark:border-slate-800">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari kode atau nama jenis potongan..." aria-label="Cari kode atau nama jenis potongan..."
                class="w-full max-w-sm rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Kode</th>
                        <th scope="col" class="px-5 py-3 font-medium">Nama</th>
                        <th scope="col" class="px-5 py-3 font-medium">Keterangan</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($types as $type)
                        <tr wire:key="type-{{ $type->id }}">
                            <td class="px-5 py-3 font-mono text-xs font-medium text-slate-700 dark:text-slate-300">{{ $type->kode }}</td>
                            <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $type->nama }}</td>
                            <td class="px-5 py-3 max-w-xs truncate text-slate-500 dark:text-slate-400">{{ $type->keterangan ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($type->status_aktif)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @can('deduction_types.manage')
                                    <div class="flex justify-end gap-1.5">
                                        <button wire:click="toggleActive({{ $type->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                            {{ $type->status_aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                        <button wire:click="openEdit({{ $type->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                            Edit
                                        </button>
                                        <button wire:click="delete({{ $type->id }})" wire:confirm="Hapus jenis potongan ini?" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                            Hapus
                                        </button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada jenis potongan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($types->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $types->links() }}
            </div>
        @endif
    </div>

    <x-modal-crud show="showModal" :title="$editingId ? 'Edit Jenis Potongan' : 'Tambah Jenis Potongan'" max-width="md">
        <form wire:submit="save" class="p-6">
            <div class="space-y-4">
                <div>
                    <x-input-label for="nama" value="Nama" />
                    <x-text-input wire:model.blur="nama" id="nama" type="text" class="mt-1 block w-full" placeholder="mis. Koperasi UNS - Simpanan Wajib" />
                    <x-input-error class="mt-2" :messages="$errors->get('nama')" />
                </div>

                <div>
                    <x-input-label for="kode" value="Kode (dibuat otomatis dari Nama)" />
                    <x-text-input wire:model="kode" id="kode" type="text" class="mt-1 block w-full bg-slate-50 font-mono text-xs dark:bg-slate-800/50" disabled />
                    <x-input-error class="mt-2" :messages="$errors->get('kode')" />
                </div>

                <div>
                    <x-input-label for="keterangan" value="Keterangan (opsional)" />
                    <textarea wire:model="keterangan" id="keterangan" rows="2" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"></textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('keterangan')" />
                </div>

                <label class="flex items-center gap-2">
                    <input wire:model="status_aktif" type="checkbox" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Status aktif</span>
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                <x-primary-button type="submit">Simpan</x-primary-button>
            </div>
        </form>
    </x-modal-crud>
</div>
