<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <script>
            if (localStorage.getItem('darkMode') === 'true' || (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="relative flex min-h-full flex-col overflow-hidden">
            {{-- Ambient gradient blobs, sama dgn layouts.guest --}}
            <div class="pointer-events-none absolute -top-40 left-1/2 h-96 w-[36rem] -translate-x-1/2 rounded-full bg-gradient-to-br from-indigo-400/30 to-violet-400/30 blur-3xl dark:from-indigo-500/20 dark:to-violet-500/20"></div>

            <header class="relative flex items-center justify-between px-6 py-6 sm:px-10">
                <x-application-logo :with-text="true" />

                @auth
                    <x-primary-button href="{{ route('dashboard') }}" wire:navigate>Buka Dashboard</x-primary-button>
                @else
                    <x-primary-button href="{{ route('login') }}" wire:navigate>Masuk</x-primary-button>
                @endauth
            </header>

            <main class="relative flex flex-1 items-center justify-center px-6 py-12">
                <div class="w-full max-w-xl text-center">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20">
                        Sistem Internal Fakultas MIPA UNS
                    </span>

                    <h1 class="mt-5 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                        Sistem Administrasi Gaji
                    </h1>
                    <p class="mt-4 text-base leading-relaxed text-slate-500 dark:text-slate-400">
                        Pencatatan, pengolahan, dan pelaporan data gaji pegawai Fakultas MIPA UNS — menggabungkan data penghasilan dari pusat dengan data potongan fakultas. Bukan sistem transfer atau pembayaran gaji.
                    </p>

                    <div class="mt-8">
                        @auth
                            <x-primary-button href="{{ route('dashboard') }}" wire:navigate class="inline-flex px-6 py-3 text-sm">Buka Dashboard</x-primary-button>
                        @else
                            <x-primary-button href="{{ route('login') }}" wire:navigate class="inline-flex px-6 py-3 text-sm">Masuk ke Sistem</x-primary-button>
                        @endauth
                    </div>

                    <p class="mt-6 text-xs text-slate-500 dark:text-slate-400">
                        Akun dikelola oleh Operator/Super Admin. Hubungi Operator Gaji Fakultas MIPA jika Anda belum memiliki akun.
                    </p>
                </div>
            </main>

            <footer class="relative px-6 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
                Sistem Administrasi Gaji &middot; Fakultas MIPA UNS
            </footer>
        </div>
    </body>
</html>
