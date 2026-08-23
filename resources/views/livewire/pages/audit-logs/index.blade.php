<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterAktivitas = '';

    public string $filterUserId = '';

    public string $dari = '';

    public string $sampai = '';

    public function mount(): void
    {
        Gate::authorize('audit_logs.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAktivitas(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUserId(): void
    {
        $this->resetPage();
    }

    public function updatingDari(): void
    {
        $this->resetPage();
    }

    public function updatingSampai(): void
    {
        $this->resetPage();
    }

    public function resetFilter(): void
    {
        $this->reset(['search', 'filterAktivitas', 'filterUserId', 'dari', 'sampai']);
        $this->resetPage();
    }

    public function with(): array
    {
        $isSuperAdmin = auth()->user()->hasRole('super_admin');

        $query = AuditLog::query()
            ->with('user:id,name')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('user_id', auth()->id()))
            ->when($this->search, fn ($q) => $q->where('deskripsi', 'like', "%{$this->search}%"))
            ->when($this->filterAktivitas, fn ($q) => $q->where('aktivitas', $this->filterAktivitas))
            ->when($isSuperAdmin && $this->filterUserId, fn ($q) => $q->where('user_id', $this->filterUserId))
            ->when($this->dari, fn ($q) => $q->whereDate('created_at', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('created_at', '<=', $this->sampai))
            ->latest('created_at');

        return [
            'logs' => $query->paginate(20),
            'isSuperAdmin' => $isSuperAdmin,
            'daftarAktivitas' => AuditLog::query()
                ->when(! $isSuperAdmin, fn ($q) => $q->where('user_id', auth()->id()))
                ->distinct()
                ->orderBy('aktivitas')
                ->pluck('aktivitas'),
            'daftarUser' => $isSuperAdmin ? User::orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Audit Log</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            @if ($isSuperAdmin)
                Seluruh aktivitas penting semua pengguna (CLAUDE.md §24).
            @else
                Aktivitas Anda sendiri (CLAUDE.md §24) — Super Admin dapat melihat aktivitas semua pengguna.
            @endif
        </p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
            <div>
                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Cari deskripsi</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari deskripsi..."
                    class="mt-1 w-56 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Aktivitas</label>
                <select wire:model.live="filterAktivitas" class="mt-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="">Semua Aktivitas</option>
                    @foreach ($daftarAktivitas as $aktivitas)
                        <option value="{{ $aktivitas }}">{{ $aktivitas }}</option>
                    @endforeach
                </select>
            </div>

            @if ($isSuperAdmin)
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Pengguna</label>
                    <select wire:model.live="filterUserId" class="mt-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua Pengguna</option>
                        @foreach ($daftarUser as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Dari Tanggal</label>
                <input type="date" wire:model.live="dari" class="mt-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Sampai Tanggal</label>
                <input type="date" wire:model.live="sampai" class="mt-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            </div>

            <x-secondary-button type="button" wire:click="resetFilter">Reset</x-secondary-button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                        <th class="px-4 py-3">Waktu</th>
                        @if ($isSuperAdmin)
                            <th class="px-4 py-3">Pengguna</th>
                        @endif
                        <th class="px-4 py-3">Aktivitas</th>
                        <th class="px-4 py-3">Deskripsi</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $log->created_at->timezone('Asia/Jakarta')->format('d-m-Y H:i') }}</td>
                            @if ($isSuperAdmin)
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700 dark:text-slate-300">{{ $log->user?->name ?? '-' }}</td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ $log->aktivitas }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $log->deskripsi }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-400 dark:text-slate-500">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isSuperAdmin ? 5 : 4 }}" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Belum ada aktivitas yang cocok dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 p-4 dark:border-slate-800">
            {{ $logs->links() }}
        </div>
    </div>
</div>
