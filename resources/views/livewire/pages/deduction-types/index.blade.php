<?php

use App\Models\DeductionType;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $kode = '';

    public string $nama = '';

    public string $keterangan = '';

    public bool $status_aktif = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('deduction_types.manage');

        $this->reset(['editingId', 'kode', 'nama', 'keterangan']);
        $this->status_aktif = true;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('deduction_types.manage');

        $type = DeductionType::findOrFail($id);
        $this->editingId = $type->id;
        $this->kode = $type->kode;
        $this->nama = $type->nama;
        $this->keterangan = (string) $type->keterangan;
        $this->status_aktif = $type->status_aktif;
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('deduction_types.manage');

        $validated = $this->validate([
            'kode' => ['required', 'string', 'max:50', 'alpha_dash', Illuminate\Validation\Rule::unique('deduction_types', 'kode')->ignore($this->editingId)],
            'nama' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'status_aktif' => ['boolean'],
        ]);

        $validated['keterangan'] = $validated['keterangan'] ?: null;

        DeductionType::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
        session()->flash('status', $this->editingId ? 'Jenis potongan berhasil diperbarui.' : 'Jenis potongan berhasil ditambahkan.');
        $this->reset(['editingId', 'kode', 'nama', 'keterangan']);
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('deduction_types.manage');

        $type = DeductionType::findOrFail($id);
        $type->update(['status_aktif' => ! $type->status_aktif]);
    }

    public function delete(int $id): void
    {
        Gate::authorize('deduction_types.manage');

        $type = DeductionType::withCount('deductionRecords')->findOrFail($id);

        if ($type->deduction_records_count > 0) {
            session()->flash('error', 'Jenis potongan "'.$type->nama.'" tidak bisa dihapus karena sudah punya riwayat potongan. Nonaktifkan saja jika sudah tidak dipakai.');

            return;
        }

        $type->delete();
        session()->flash('status', 'Jenis potongan berhasil dihapus.');
    }

    public function with(): array
    {
        return [
            'types' => DeductionType::withCount('deductionRecords')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('kode', 'like', "%{$this->search}%")
                    ->orWhere('nama', 'like', "%{$this->search}%")
                ))
                ->orderBy('nama')
                ->paginate(15),
        ];
    }
}; ?>

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
        <div class="border-b border-slate-100 p-4 dark:border-slate-800">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari kode atau nama jenis potongan..."
                class="w-full max-w-sm rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3 font-medium">Kode</th>
                        <th class="px-5 py-3 font-medium">Nama</th>
                        <th class="px-5 py-3 font-medium">Keterangan</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium text-right">Aksi</th>
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
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada jenis potongan.</td>
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
                    {{ $editingId ? 'Edit Jenis Potongan' : 'Tambah Jenis Potongan' }}
                </h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="kode" value="Kode" />
                        <x-text-input wire:model="kode" id="kode" type="text" class="mt-1 block w-full" placeholder="mis. KOPERASI_SIMPANAN_WAJIB" />
                        <x-input-error class="mt-2" :messages="$errors->get('kode')" />
                    </div>

                    <div>
                        <x-input-label for="nama" value="Nama" />
                        <x-text-input wire:model="nama" id="nama" type="text" class="mt-1 block w-full" placeholder="mis. Koperasi UNS - Simpanan Wajib" />
                        <x-input-error class="mt-2" :messages="$errors->get('nama')" />
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
        </div>
    </div>
</div>
