<?php

namespace App\Livewire\Base;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Basis bersama untuk halaman Master Data CRUD sederhana (Unit, Status Pegawai,
 * Golongan, Jabatan Fungsional, Bank, Jenis Potongan).
 *
 * Subclass cukup mendeklarasikan properti form (wire:model) + konfigurasi
 * (permission/model/label/formFields/rules/dll). Seluruh perilaku CRUD
 * (openCreate/openEdit/save/toggleActive/delete/search/paginate) ditangani di sini.
 */
#[Layout('layouts.app')]
abstract class SimpleCrud extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public bool $status_aktif = true;

    /** Nama permission aksi tulis, mis. 'units.manage'. */
    abstract protected function permission(): string;

    /** FQCN model, mis. App\Models\Unit::class. */
    abstract protected function model(): string;

    /** Label tunggal untuk pesan sukses/hapus, mis. 'Unit'. */
    abstract protected function label(): string;

    /** Mapping properti form (wire:model) => kolom model, mis. ['kode_unit' => 'kode_unit']. */
    abstract protected function formFields(): array;

    /** Aturan validasi; boleh memakai $this->editingId untuk unique ignore. */
    abstract protected function rules(): array;

    /** Kolom yang dicari saat search. */
    protected function searchColumns(): array
    {
        return ['kode', 'nama'];
    }

    /** Kolom orderBy. */
    protected function orderByColumn(): string
    {
        return 'nama';
    }

    /** Key hasil list di with(), mis. 'units' — dipakai sebagai variabel di view. */
    protected function listKey(): string
    {
        return 'items';
    }

    /** Kolom untuk menampilkan nama di pesan guard hapus. */
    protected function displayColumn(): string
    {
        return 'nama';
    }

    /** Guard hapus: ['relation' => 'employees', 'label' => 'pegawai'] atau null. */
    protected function deleteGuard(): ?array
    {
        return null;
    }

    protected function pageSize(): int
    {
        return 10;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize($this->permission());

        $this->reset(array_keys($this->formFields()));
        $this->editingId = null;
        $this->status_aktif = true;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        Gate::authorize($this->permission());

        $item = ($this->model())::findOrFail($id);

        $this->editingId = $item->id;
        foreach ($this->formFields() as $prop => $column) {
            $this->{$prop} = $item->{$column} ?? '';
        }
        $this->status_aktif = (bool) $item->status_aktif;
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize($this->permission());

        $validated = $this->validate($this->rules());

        ($this->model())::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
        session()->flash('status', $this->editingId
            ? $this->label().' berhasil diperbarui.'
            : $this->label().' berhasil ditambahkan.');
        $this->reset(array_keys($this->formFields()));
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize($this->permission());

        $item = ($this->model())::findOrFail($id);
        $item->update(['status_aktif' => ! $item->status_aktif]);
    }

    public function delete(int $id): void
    {
        Gate::authorize($this->permission());

        $guard = $this->deleteGuard();

        if ($guard) {
            $item = ($this->model())::withCount($guard['relation'])->findOrFail($id);
            $count = $item->{$guard['relation'].'_count'};

            if ($count > 0) {
                session()->flash('error', $this->label().' "'.$item->{$this->displayColumn()}.'" tidak bisa dihapus karena masih dipakai '.$count.' '.$guard['label'].'. Nonaktifkan saja jika sudah tidak dipakai.');

                return;
            }
        } else {
            $item = ($this->model())::findOrFail($id);
        }

        $item->delete();
        session()->flash('status', $this->label().' berhasil dihapus.');
    }

    public function with(): array
    {
        $model = $this->model();
        $query = $model::query();

        if ($guard = $this->deleteGuard()) {
            $query->withCount($guard['relation']);
        }

        $query
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    foreach ($this->searchColumns() as $i => $column) {
                        if ($i === 0) {
                            $q->where($column, 'like', "%{$this->search}%");
                        } else {
                            $q->orWhere($column, 'like', "%{$this->search}%");
                        }
                    }
                });
            })
            ->orderBy($this->orderByColumn())
            ->paginate($this->pageSize());

        return [$this->listKey() => $query];
    }
}
