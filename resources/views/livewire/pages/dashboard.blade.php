<?php

use App\Models\SalaryPeriod;
use App\Services\Report\DashboardService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        $user = auth()->user();

        // Pegawai cuma boleh lihat datanya sendiri (§10/§23) — jalur data
        // yang benar-benar terpisah dari ringkasan fakultas, bukan cuma
        // disembunyikan di tampilan.
        if ($user->hasRole('pegawai')) {
            return [
                'isPegawai' => true,
                'employeeLinked' => (bool) $user->employee_id,
            ] + (
                $user->employee_id
                    ? app(DashboardService::class)->ringkasanPegawai($user->employee_id)
                    : []
            );
        }

        return ['isPegawai' => false] + app(DashboardService::class)->ringkasan();
    }
}; ?>

@if ($isPegawai)
    <div class="w-full space-y-6">
        @if (! $employeeLinked)
            <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-10 text-center dark:border-amber-500/30 dark:bg-amber-500/10">
                <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Akun ini belum ditautkan ke data Master Pegawai — hubungi Operator.</p>
            </div>
        @else
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Dashboard Saya</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ringkasan gaji Anda. Untuk dokumen lengkap, buka Slip Gaji atau Bukti Potongan di menu Dokumen.</p>
            </div>

            @if ($terbaru)
                <div class="flex flex-col gap-4 rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 p-6 text-white shadow-lg shadow-indigo-600/20 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-100">Periode Terbaru</p>
                        <p class="mt-1 text-2xl font-bold">{{ $terbaru->salaryPeriod->nama_periode }}</p>
                        <p class="mt-1 text-sm text-indigo-100">Gaji Bersih: Rp {{ number_format($terbaru->gaji_bersih_final, 0, ',', '.') }}</p>
                    </div>
                    <x-period-status-badge :status="$terbaru->salaryPeriod->status" class="w-fit" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Penghasilan</p>
                        <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Rp {{ number_format($terbaru->total_penghasilan_kotor, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Potongan</p>
                        <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Rp {{ number_format($terbaru->total_potongan_pusat + $terbaru->total_potongan_fakultas, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Gaji Bersih</p>
                        <p class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Rp {{ number_format($terbaru->gaji_bersih_final, 0, ',', '.') }}</p>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada data gaji untuk Anda.</p>
                </div>
            @endif

            @if ($histori->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Histori Gaji Saya</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-medium">Periode</th>
                                    <th scope="col" class="px-5 py-3 font-medium">Gaji Bersih</th>
                                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($histori as $r)
                                    <tr>
                                        <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $r->salaryPeriod->nama_periode }}</td>
                                        <td class="px-5 py-3 text-slate-500 dark:text-slate-400">Rp {{ number_format($r->gaji_bersih_final, 0, ',', '.') }}</td>
                                        <td class="px-5 py-3"><x-period-status-badge :status="$r->salaryPeriod->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>
@else
    <div class="w-full space-y-6">
        @if (! $periodeAktif)
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Belum ada Periode Gaji. Buat periode pertama untuk mulai mengisi Dashboard.</p>
                @can('periods.create')
                    <x-primary-button href="{{ route('salary-periods.index') }}" wire:navigate class="mt-4 inline-flex">
                        Buka Periode Gaji
                    </x-primary-button>
                @endcan
            </div>
        @else
            {{-- Periode aktif banner --}}
            <a href="{{ route('salary-periods.show', $periodeAktif) }}" wire:navigate
                class="flex flex-col gap-4 rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 p-6 text-white shadow-lg shadow-indigo-600/20 transition hover:shadow-xl sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-100">Periode Aktif</p>
                    <p class="mt-1 text-2xl font-bold">{{ $periodeAktif->nama_periode }}</p>
                    <p class="mt-1 text-sm text-indigo-100">{{ $totals['jumlah_pegawai'] }} pegawai &middot; Fakultas MIPA UNS</p>
                </div>
                <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/20">
                    <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                    {{ match ($periodeAktif->status) {
                        SalaryPeriod::STATUS_DRAFT => 'Draft',
                        SalaryPeriod::STATUS_VERIFIKASI => 'Verifikasi',
                        SalaryPeriod::STATUS_FINAL => 'Final',
                        SalaryPeriod::STATUS_ARSIP => 'Arsip',
                        default => $periodeAktif->status,
                    } }}
                </span>
            </a>

            {{-- KPI cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $kpis = [
                        ['label' => 'Jumlah Pegawai', 'value' => number_format($totals['jumlah_pegawai'], 0, ',', '.'), 'icon' => 'users', 'tint' => 'text-sky-600 bg-sky-50 dark:bg-sky-500/10 dark:text-sky-400'],
                        ['label' => 'Total Penghasilan', 'value' => 'Rp '.number_format($totals['total_penghasilan_kotor'], 0, ',', '.'), 'icon' => 'chart', 'tint' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400'],
                        ['label' => 'Total Potongan', 'value' => 'Rp '.number_format($totals['total_potongan_pusat'] + $totals['total_potongan_fakultas'], 0, ',', '.'), 'icon' => 'minus-circle', 'tint' => 'text-rose-600 bg-rose-50 dark:bg-rose-500/10 dark:text-rose-400'],
                        ['label' => 'Total Gaji Bersih', 'value' => 'Rp '.number_format($totals['total_gaji_bersih'], 0, ',', '.'), 'icon' => 'calculator', 'tint' => 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-400'],
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
                        $statusVerifikasi = match ($periodeAktif->status) {
                            SalaryPeriod::STATUS_DRAFT => 'Menunggu',
                            SalaryPeriod::STATUS_VERIFIKASI => 'Sedang Diperiksa',
                            default => 'Selesai',
                        };
                        $statusLabel = match ($periodeAktif->status) {
                            SalaryPeriod::STATUS_DRAFT => 'Draft',
                            SalaryPeriod::STATUS_VERIFIKASI => 'Verifikasi',
                            SalaryPeriod::STATUS_FINAL => 'Final',
                            SalaryPeriod::STATUS_ARSIP => 'Arsip',
                            default => $periodeAktif->status,
                        };
                        $netral = 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700';
                        $hijau = 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20';
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset {{ $netral }}">
                        Status Periode: {{ $statusLabel }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset {{ $sudahImport ? $hijau : $netral }}">
                        Status Import: {{ $sudahImport ? 'Sudah Import' : 'Belum Import' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset {{ $netral }}">
                        Status Verifikasi: {{ $statusVerifikasi }}
                    </span>
                </div>
            </div>

            {{-- Charts --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Penghasilan &amp; Potongan per Periode</p>
                        <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-indigo-500"></span> Penghasilan</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rose-400"></span> Potongan</span>
                        </div>
                    </div>

                    @if ($trenBulanan->isEmpty())
                        <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data periode.</p>
                    @else
                        @php
                            $maxNilai = max(1, $trenBulanan->max('penghasilan'));
                            $ringkasanGrafik = collect($trenBulanan)
                                ->map(fn ($b) => $b['label'].': Penghasilan Rp '.number_format($b['penghasilan'], 0, ',', '.').', Potongan Rp '.number_format($b['potongan'], 0, ',', '.'))
                                ->implode('; ');
                        @endphp
                        <div class="mt-6 flex h-48 items-end gap-3" role="img" aria-label="Grafik penghasilan dan potongan per periode">
                            @foreach ($trenBulanan as $bar)
                                <div class="flex flex-1 flex-col items-center gap-2">
                                    <div class="flex h-40 w-full items-end justify-center gap-1 overflow-hidden rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                                        <div class="w-1/2 rounded bg-gradient-to-t from-indigo-600 to-violet-500" style="height: {{ max(2, round($bar['penghasilan'] / $maxNilai * 100)) }}%" title="Penghasilan: Rp {{ number_format($bar['penghasilan'], 0, ',', '.') }}"></div>
                                        <div class="w-1/2 rounded bg-gradient-to-t from-rose-500 to-rose-300" style="height: {{ max(2, round($bar['potongan'] / $maxNilai * 100)) }}%" title="Potongan: Rp {{ number_format($bar['potongan'], 0, ',', '.') }}"></div>
                                    </div>
                                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $bar['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="sr-only">{{ $ringkasanGrafik }}</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Rekap Jenis Potongan</p>
                    <div class="mt-4 space-y-3">
                        @php $totalPotonganFakultas = max(1, $perJenisPotongan->sum('total_nominal')); @endphp
                        @forelse ($perJenisPotongan as $row)
                            <div>
                                <div class="mb-1 flex justify-between text-xs font-medium text-slate-500 dark:text-slate-400">
                                    <span class="truncate pr-2">{{ $row['nama'] }}</span>
                                    <span class="shrink-0">{{ round($row['total_nominal'] / $totalPotonganFakultas * 100) }}% &middot; Rp {{ number_format($row['total_nominal'], 0, ',', '.') }}</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="img" aria-label="{{ $row['nama'] }}: Rp {{ number_format($row['total_nominal'], 0, ',', '.') }}">
                                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ round($row['total_nominal'] / $totalPotonganFakultas * 100) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada data potongan fakultas periode ini.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Rekap unit + histori periode --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Rekap per Unit</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-medium">Unit</th>
                                    <th scope="col" class="px-5 py-3 font-medium text-right">Pegawai</th>
                                    <th scope="col" class="px-5 py-3 font-medium text-right">Gaji Bersih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($perUnit as $unit => $row)
                                    <tr>
                                        <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $unit }}</td>
                                        <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">{{ $row['jumlah_pegawai'] }}</td>
                                        <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">Rp {{ number_format($row['total_gaji_bersih'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Histori Periode</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-medium">Periode</th>
                                    <th scope="col" class="px-5 py-3 font-medium text-right">Gaji Bersih</th>
                                    <th scope="col" class="px-5 py-3 font-medium text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($historiPeriode as $row)
                                    <tr>
                                        <td class="px-5 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $row['nama_periode'] }}</td>
                                        <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">Rp {{ number_format($row['gaji_bersih'], 0, ',', '.') }}</td>
                                        <td class="px-5 py-3">
                                            <x-period-status-badge :status="$row['status']" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-6 text-center text-slate-500 dark:text-slate-400">Belum ada periode lain.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
