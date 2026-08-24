<?php

use App\Livewire\Base\SimpleCrud;
use App\Models\Golongan;

new class extends SimpleCrud
{
    protected function permission(): string { return 'golongans.manage'; }

    protected function model(): string { return Golongan::class; }

    protected function label(): string { return 'Golongan'; }

    protected function formFields(): array
    {
        return ['kode' => 'kode', 'nama' => 'nama'];
    }

    protected function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:20', 'alpha_dash', Illuminate\Validation\Rule::unique('golongans', 'kode')->ignore($this->editingId)],
            'nama' => ['required', 'string', 'max:100'],
            'status_aktif' => ['boolean'],
        ];
    }

    protected function orderByColumn(): string { return 'kode'; }

    protected function listKey(): string { return 'golongans'; }

    protected function deleteGuard(): ?array { return ['relation' => 'employees', 'label' => 'pegawai']; }
};

?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Master Golongan</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Referensi kode &rarr; label golongan/pangkat pegawai — dikelola dinamis, tidak di-hardcode di kode (CLAUDE.md §11). Kode diisi otomatis dari kode mentah (kdgol) saat Import Gaji Pusat PNS; nama bisa diedit jadi label yang sebenarnya (mis. "III/a").</p>
        </div>

        @can('golongans.manage')
            <x-primary-button wire:click="openCreate" type="button" class="w-fit">
                + Tambah Golongan
            </x-primary-button>
        @endcan
    </div>

    <x-flash :status="session('status')" :error="session('error')" />

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 p-4 dark:border-slate-800">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari kode atau nama golongan..." aria-label="Cari kode atau nama golongan..."
                class="w-full max-w-sm rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Kode</th>
                        <th scope="col" class="px-5 py-3 font-medium">Nama Golongan</th>
                        <th scope="col" class="px-5 py-3 font-medium">Jumlah Pegawai</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($golongans as $golongan)
                        <tr wire:key="golongan-{{ $golongan->id }}">
                            <td class="px-5 py-3 font-mono text-xs font-medium text-slate-700 dark:text-slate-300">{{ $golongan->kode }}</td>
                            <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $golongan->nama }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $golongan->employees_count }}</td>
                            <td class="px-5 py-3">
                                @if ($golongan->status_aktif)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @can('golongans.manage')
                                    <div class="flex justify-end gap-1.5">
                                        <button wire:click="toggleActive({{ $golongan->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                            {{ $golongan->status_aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                        <button wire:click="openEdit({{ $golongan->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                            Edit
                                        </button>
                                        <button wire:click="delete({{ $golongan->id }})" wire:confirm="Hapus golongan ini?" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                            Hapus
                                        </button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data golongan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($golongans->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $golongans->links() }}
            </div>
        @endif
    </div>

    <x-modal-crud show="showModal" :title="$editingId ? 'Edit Golongan' : 'Tambah Golongan'" max-width="md">
        <form wire:submit="save" class="p-6">
            <div class="space-y-4">
                <div>
                    <x-input-label for="kode" value="Kode" />
                    <x-text-input wire:model="kode" id="kode" type="text" class="mt-1 block w-full" placeholder="mis. 45" />
                    <x-input-error class="mt-2" :messages="$errors->get('kode')" />
                </div>

                <div>
                    <x-input-label for="nama" value="Nama Golongan" />
                    <x-text-input wire:model="nama" id="nama" type="text" class="mt-1 block w-full" placeholder="mis. III/a" />
                    <x-input-error class="mt-2" :messages="$errors->get('nama')" />
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
