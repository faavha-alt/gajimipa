<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $groups = [
        [
            'label' => 'Utama',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home'],
            ],
        ],
        [
            'label' => 'Periode & Proses',
            'items' => [
                ['label' => 'Periode Gaji', 'route' => 'salary-periods.index', 'permission' => 'periods.view', 'icon' => 'calendar'],
                ['label' => 'Import Gaji Pusat', 'route' => 'salary-imports.create', 'permission' => 'salary_imports.manage', 'icon' => 'upload'],
                ['label' => 'Data Potongan', 'route' => 'deduction-records.index', 'permission' => 'deduction_records.view', 'icon' => 'minus-circle'],
                ['label' => 'Potongan Berulang', 'route' => 'recurring-deductions.index', 'permission' => 'recurring_deductions.view', 'icon' => 'archive'],
                ['label' => 'Proses Gaji', 'route' => 'salary-processing.create', 'permission' => 'salary_processing.manage', 'icon' => 'calculator'],
            ],
        ],
        [
            'label' => 'Dokumen',
            'items' => [
                ['label' => 'Slip Gaji', 'route' => 'payslips.mine', 'icon' => 'document'],
                ['label' => 'Bukti Potongan', 'route' => 'deduction-receipts.mine', 'icon' => 'receipt'],
            ],
        ],
        [
            'label' => 'Laporan',
            'items' => [
                ['label' => 'Laporan', 'route' => 'laporan.index', 'permission' => 'laporan.view', 'icon' => 'chart'],
            ],
        ],
        [
            'label' => 'Master Data',
            'items' => [
                ['label' => 'Master Pegawai', 'route' => 'employees.index', 'permission' => 'employees.view', 'icon' => 'users'],
                ['label' => 'Master Unit', 'route' => 'units.index', 'permission' => 'units.view', 'icon' => 'building'],
                ['label' => 'Master Status Pegawai', 'route' => 'employee-statuses.index', 'permission' => 'employee_statuses.view', 'icon' => 'tag'],
                ['label' => 'Master Golongan', 'route' => 'golongans.index', 'permission' => 'golongans.view', 'icon' => 'tag'],
                ['label' => 'Master Jab. Fungsional', 'route' => 'jabatan-fungsionals.index', 'permission' => 'jabatan_fungsionals.view', 'icon' => 'tag'],
                ['label' => 'Master Bank', 'route' => 'banks.index', 'permission' => 'banks.view', 'icon' => 'tag'],
                ['label' => 'Master Jenis Potongan', 'route' => 'deduction-types.index', 'permission' => 'deduction_types.view', 'icon' => 'tag'],
            ],
        ],
        [
            'label' => 'Sistem',
            'items' => [
                ['label' => 'User & Hak Akses', 'route' => 'users.index', 'permission' => 'users.manage', 'icon' => 'shield'],
                ['label' => 'Audit Log', 'route' => 'audit-logs.index', 'permission' => 'audit_logs.view', 'icon' => 'clock'],
                ['label' => 'Pengaturan', 'route' => 'settings.index', 'permission' => 'settings.manage', 'icon' => 'cog'],
            ],
        ],
    ];
@endphp

<div>
{{-- Mobile backdrop --}}
<div
    x-show="sidebarOpen"
    x-cloak
    x-transition:enter="transition-opacity ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"
></div>

{{-- Sidebar panel, docked left on desktop, off-canvas from the left on mobile --}}
<aside
    x-cloak
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    :inert="isMobile && ! sidebarOpen"
    :aria-hidden="isMobile && ! sidebarOpen"
    class="fixed inset-y-0 left-0 z-50 flex w-72 transform flex-col border-r border-slate-200 bg-white text-slate-600 transition-transform duration-300 ease-in-out dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 lg:left-0 lg:z-30 lg:w-72 lg:translate-x-0 xl:w-80"
>
    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 px-5 dark:border-slate-800">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-application-logo :with-text="true" />
        </a>

        <button @click="sidebarOpen = false" type="button" class="rounded-lg p-1.5 text-slate-500 dark:text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/5 dark:hover:text-white lg:hidden">
            <span class="sr-only">Tutup menu</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Nav groups --}}
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        @foreach ($groups as $group)
            <div>
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $group['label'] }}</p>

                <div class="mt-2 space-y-0.5">
                    @foreach ($group['items'] as $item)
                        @continue(isset($item['permission']) && ! auth()->user()->can($item['permission']))
                        @if (isset($item['route']))
                            @php $active = request()->routeIs($item['route']) || request()->routeIs(\Illuminate\Support\Str::before($item['route'], '.').'.*'); @endphp
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                class="group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition
                                    {{ $active
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-white/10 dark:text-white'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white' }}"
                            >
                                <x-nav-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 group-hover:text-slate-600 dark:text-slate-400 dark:group-hover:text-slate-300' }}" />
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @else
                            <span class="group flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 dark:text-slate-400/70">
                                <x-nav-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0 text-slate-300 dark:text-slate-600" />
                                <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-400">Segera</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    {{-- User card / footer --}}
    <div class="shrink-0 border-t border-slate-200 p-3 dark:border-slate-800" x-data="{ menuOpen: false }" @click.outside="menuOpen = false">
        <button @click="menuOpen = ! menuOpen" type="button" class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left hover:bg-slate-100 dark:hover:bg-white/5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-bold text-white">
                {{ Str::of(auth()->user()->name)->explode(' ')->map(fn ($w) => Str::substr($w, 0, 1))->take(2)->join('') }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>
                <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</span>
            </span>
            <svg class="h-4 w-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 10.5 15.75 15" />
            </svg>
        </button>

        <div
            x-show="menuOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="mt-1 space-y-0.5 rounded-xl bg-slate-50 p-1.5 dark:bg-white/5"
        >
            <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                Profil Saya
            </a>
            <button wire:click="logout" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-300">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
                Keluar
            </button>
        </div>
    </div>
</aside>
</div>
