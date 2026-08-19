<x-app-layout>
    <x-slot name="header">{{ __('Dashboard') }}</x-slot>

    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Preview notice — remove once dashboard is wired to real data (STEP 6+) --}}
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p>Tampilan pratinjau — angka dan status di bawah ini masih data contoh statis, belum terhubung ke database. Akan aktif bertahap mulai STEP 6.</p>
        </div>

        {{-- Periode aktif banner --}}
        <div class="flex flex-col gap-4 rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 p-6 text-white shadow-lg shadow-indigo-600/20 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-200">Periode Aktif</p>
                <p class="mt-1 text-2xl font-bold">Agustus 2026</p>
                <p class="mt-1 text-sm text-indigo-200">7 pegawai &middot; Fakultas MIPA UNS</p>
            </div>
            <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm font-semibold ring-1 ring-inset ring-white/20">
                <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                DRAFT
            </span>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $kpis = [
                    ['label' => 'Jumlah Pegawai', 'value' => '7', 'icon' => 'users', 'tint' => 'text-sky-600 bg-sky-50 dark:bg-sky-500/10 dark:text-sky-400'],
                    ['label' => 'Total Penghasilan', 'value' => 'Rp 55.180.700', 'icon' => 'chart', 'tint' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400'],
                    ['label' => 'Total Potongan', 'value' => 'Rp 3.972.000', 'icon' => 'minus-circle', 'tint' => 'text-rose-600 bg-rose-50 dark:bg-rose-500/10 dark:text-rose-400'],
                    ['label' => 'Total Gaji Bersih', 'value' => 'Rp 51.208.700', 'icon' => 'calculator', 'tint' => 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-400'],
                ];
            @endphp

            @foreach ($kpis as $kpi)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $kpi['label'] }}</p>
                        <span class="grid h-9 w-9 place-items-center rounded-xl {{ $kpi['tint'] }}">
                            <x-nav-icon :name="$kpi['icon']" class="h-[18px] w-[18px]" />
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $kpi['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Status row --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Status Periode Berjalan</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @php
                    $statuses = [
                        ['label' => 'Status Periode', 'value' => 'Draft', 'tone' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20'],
                        ['label' => 'Status Import', 'value' => 'Belum Import', 'tone' => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
                        ['label' => 'Status Verifikasi', 'value' => 'Menunggu', 'tone' => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
                        ['label' => 'Status Email', 'value' => 'Belum Dikirim', 'tone' => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
                    ];
                @endphp
                @foreach ($statuses as $status)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset {{ $status['tone'] }}">
                        {{ $status['label'] }}: {{ $status['value'] }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Charts + histori --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Penghasilan &amp; Potongan per Bulan</p>
                </div>
                <div class="mt-6 flex h-48 items-end gap-3">
                    @foreach ([['b' => 'Mar', 'v' => 55], ['b' => 'Apr', 'v' => 62], ['b' => 'Mei', 'v' => 58], ['b' => 'Jun', 'v' => 70], ['b' => 'Jul', 'v' => 66], ['b' => 'Agu', 'v' => 78]] as $bar)
                        <div class="flex flex-1 flex-col items-center gap-2">
                            <div class="flex h-40 w-full items-end overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                                <div class="w-full rounded-lg bg-gradient-to-t from-indigo-600 to-violet-500" style="height: {{ $bar['v'] }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-slate-400">{{ $bar['b'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Rekap Jenis Potongan</p>
                <div class="mt-4 space-y-3">
                    @foreach ([['n' => 'Koperasi UNS', 'p' => 42], ['n' => 'BPJS', 'p' => 28], ['n' => 'Iuran Kesejahteraan', 'p' => 15], ['n' => 'Lainnya', 'p' => 15]] as $row)
                        <div>
                            <div class="mb-1 flex justify-between text-xs font-medium text-slate-500 dark:text-slate-400">
                                <span>{{ $row['n'] }}</span>
                                <span>{{ $row['p'] }}%</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $row['p'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Histori periode --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Histori Periode</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Periode</th>
                            <th class="px-5 py-3 font-medium">Gaji Bersih</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ([
                            ['periode' => 'Juli 2026', 'nominal' => 'Rp 50.940.200', 'status' => 'Arsip', 'tone' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
                            ['periode' => 'Juni 2026', 'nominal' => 'Rp 48.610.500', 'status' => 'Arsip', 'tone' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
                            ['periode' => 'Mei 2026', 'nominal' => 'Rp 49.775.900', 'status' => 'Arsip', 'tone' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
                        ] as $row)
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $row['periode'] }}</td>
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $row['nominal'] }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['tone'] }}">{{ $row['status'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
