<x-app-layout>
    <x-slot name="header">{{ __('Profil') }}</x-slot>

    <div class="w-full space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <livewire:profile.update-password-form />
        </div>

        <div class="rounded-2xl border border-rose-200 bg-white p-6 shadow-sm dark:border-rose-500/20 dark:bg-slate-900">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
