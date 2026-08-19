<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <script>
            if (localStorage.getItem('darkMode') === 'true' || (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="relative flex min-h-full flex-col items-center justify-center overflow-hidden px-4 py-10">
            {{-- Ambient gradient blobs --}}
            <div class="pointer-events-none absolute -top-40 left-1/2 h-96 w-[36rem] -translate-x-1/2 rounded-full bg-gradient-to-br from-indigo-400/30 to-violet-400/30 blur-3xl dark:from-indigo-500/20 dark:to-violet-500/20"></div>

            <a href="/" wire:navigate class="relative mb-6">
                <x-application-logo :with-text="true" />
            </a>

            <div class="relative w-full sm:max-w-md">
                <div class="rounded-2xl border border-slate-200 bg-white px-6 py-7 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 sm:px-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-600">
                    Sistem Administrasi Gaji &middot; Fakultas MIPA UNS
                </p>
            </div>
        </div>
    </body>
</html>
