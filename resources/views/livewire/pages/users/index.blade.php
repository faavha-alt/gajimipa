<?php

use App\Models\Employee;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    private const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'operator_gaji' => 'Operator Gaji',
        'verifikator' => 'Verifikator',
        'pimpinan' => 'Pimpinan',
        'pegawai' => 'Pegawai',
    ];

    public string $search = '';

    public string $filterRole = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = '';

    public string $employee_id = '';

    public bool $status_aktif = true;

    private const RESETTABLE_FIELDS = ['editingId', 'name', 'email', 'password', 'role', 'employee_id'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('users.manage');

        $this->reset(self::RESETTABLE_FIELDS);
        $this->status_aktif = true;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('users.manage');

        $user = User::with('roles')->findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? '';
        $this->employee_id = (string) $user->employee_id;
        $this->status_aktif = $user->status_aktif;
        $this->showModal = true;
    }

    public function generatePassword(): void
    {
        Gate::authorize('users.manage');

        $this->password = Str::password(12);
    }

    public function save(): void
    {
        Gate::authorize('users.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Illuminate\Validation\Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Illuminate\Validation\Rule::in(array_keys(self::ROLE_LABELS))],
            'employee_id' => [
                $this->role === 'pegawai' ? 'required' : 'nullable',
                'exists:employees,id',
                Illuminate\Validation\Rule::unique('users', 'employee_id')->ignore($this->editingId),
            ],
            'status_aktif' => ['boolean'],
        ], [
            'employee_id.required' => 'Akun dengan role Pegawai wajib ditautkan ke data Master Pegawai.',
            'employee_id.unique' => 'Pegawai ini sudah ditautkan ke akun lain.',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'employee_id' => $validated['employee_id'] ?: null,
            'status_aktif' => $validated['status_aktif'],
        ];
        if (filled($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user = User::updateOrCreate(['id' => $this->editingId], $data);
        $user->syncRoles([$validated['role']]);

        AuditLogger::log(
            $this->editingId ? 'Ubah User' : 'Buat User',
            ($this->editingId ? 'Mengubah' : 'Membuat').' akun '.$user->email.' (role: '.self::ROLE_LABELS[$validated['role']].').',
            ['user_id' => $user->id, 'role' => $validated['role']]
        );

        $this->showModal = false;
        session()->flash('status', $this->editingId ? 'Akun berhasil diperbarui.' : 'Akun berhasil ditambahkan.');
        $this->reset(self::RESETTABLE_FIELDS);
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('users.manage');

        if ($id === auth()->id()) {
            session()->flash('error', 'Tidak bisa menonaktifkan akun sendiri.');

            return;
        }

        $user = User::findOrFail($id);
        $user->update(['status_aktif' => ! $user->status_aktif]);

        AuditLogger::log(
            $user->status_aktif ? 'Aktifkan User' : 'Nonaktifkan User',
            ($user->status_aktif ? 'Mengaktifkan' : 'Menonaktifkan')." akun {$user->email}.",
            ['user_id' => $user->id]
        );
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->with(['roles', 'employee:id,nama'])
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                ))
                ->when($this->filterRole, fn ($q) => $q->whereHas('roles', fn ($q) => $q->where('name', $this->filterRole)))
                ->orderBy('name')
                ->paginate(15),
            'employees' => Employee::select(['id', 'nip', 'nama'])->orderBy('nama')->get(),
            'roleLabels' => self::ROLE_LABELS,
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">User &amp; Hak Akses</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola akun login &amp; role (CLAUDE.md §23) — hanya Super Admin. Akun nonaktif langsung ter-logout otomatis.</p>
        </div>

        <x-primary-button wire:click="openCreate" type="button" class="w-fit">
            + Tambah User
        </x-primary-button>
    </div>

    <x-flash :status="session('status')" :error="session('error')" />

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama atau email..." aria-label="Cari nama atau email..."
                class="w-full max-w-xs rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
            <select wire:model.live="filterRole" aria-label="Filter Role" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Semua Role</option>
                @foreach ($roleLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Nama</th>
                        <th scope="col" class="px-5 py-3 font-medium">Email</th>
                        <th scope="col" class="px-5 py-3 font-medium">Role</th>
                        <th scope="col" class="px-5 py-3 font-medium">Pegawai Terkait</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <span class="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Anda</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $user->email }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $roleLabels[$user->roles->first()?->name] ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $user->employee?->nama ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($user->status_aktif)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1.5">
                                    @if ($user->id !== auth()->id())
                                        <button wire:click="toggleActive({{ $user->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 focus:bg-slate-100 focus:ring-2 focus:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                                            {{ $user->status_aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    @endif
                                    <button wire:click="openEdit({{ $user->id }})" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 dark:text-indigo-400 dark:hover:bg-indigo-500/10 dark:focus:bg-indigo-500/10">
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data user.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <x-modal-crud show="showModal" :title="$editingId ? 'Edit User' : 'Tambah User'" max-width="lg">
        <form wire:submit="save" class="max-h-[85vh] overflow-y-auto p-6">
            <div class="space-y-4">
                    <div>
                        <x-input-label for="name" value="Nama" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div>
                        <x-input-label for="password" :value="$editingId ? 'Password Baru (kosongkan jika tidak diubah)' : 'Password'" />
                        <div class="mt-1 flex gap-2" x-data="{ showPassword: false }">
                            <x-text-input wire:model="password" id="password" type="password" x-bind:type="showPassword ? 'text' : 'password'" class="block w-full font-mono text-sm" placeholder="min. 8 karakter" autocomplete="new-password" />
                            <x-secondary-button type="button" @click="showPassword = ! showPassword" class="shrink-0">
                                <span x-text="showPassword ? 'Sembunyikan' : 'Lihat'">Lihat</span>
                            </x-secondary-button>
                            <x-secondary-button type="button" wire:click="generatePassword" class="shrink-0">Acak</x-secondary-button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Belum ada email otomatis (§17 belum aktif) — sampaikan password ini ke pengguna secara manual.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('password')" />
                    </div>

                    <div>
                        <x-input-label for="role" value="Role" />
                        <select wire:model="role" id="role" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Pilih Role —</option>
                            @foreach ($roleLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />
                    </div>

                    <div>
                        <x-input-label for="employee_id" :value="'Tautkan ke Pegawai'.($role === 'pegawai' ? ' (wajib)' : ' (opsional)')" />
                        <select wire:model="employee_id" id="employee_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">— Tidak Ditautkan —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->nip }} — {{ $employee->nama }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Dipakai utk fitur "Slip Gaji Saya" &amp; "Bukti Potongan Saya". Role Pegawai wajib ditautkan.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
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
