<?php

use App\Models\SalaryPeriod;
use App\Services\Salary\SalaryPeriodService;
use App\Services\Salary\SalaryPeriodValidationService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public SalaryPeriod $period;

    public bool $showKembalikanModal = false;

    public string $alasanKembali = '';

    public bool $showRevisiModal = false;

    public string $alasanRevisi = '';

    public function mount(SalaryPeriod $period): void
    {
        $this->period = $period;
    }

    private function refreshPeriod(): void
    {
        $this->period = $this->period->fresh();
    }

    public function submitVerifikasi(): void
    {
        Gate::authorize('periods.submit');

        try {
            app(SalaryPeriodService::class)->ajukanVerifikasi($this->period, auth()->user());
            session()->flash('status', 'Periode diajukan untuk verifikasi.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->refreshPeriod();
    }

    public function openKembalikan(): void
    {
        Gate::authorize('periods.verify');
        $this->alasanKembali = '';
        $this->showKembalikanModal = true;
    }

    public function kembalikanKeDraft(): void
    {
        Gate::authorize('periods.verify');

        $this->validate(['alasanKembali' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            app(SalaryPeriodService::class)->kembalikanKeDraft($this->period, auth()->user(), $this->alasanKembali);
            session()->flash('status', 'Periode dikembalikan ke DRAFT.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->showKembalikanModal = false;
        $this->refreshPeriod();
    }

    public function finalisasi(): void
    {
        Gate::authorize('periods.verify');

        try {
            app(SalaryPeriodService::class)->finalisasi($this->period, auth()->user());
            session()->flash('status', 'Periode berhasil difinalisasi.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->refreshPeriod();
    }

    public function arsipkan(): void
    {
        Gate::authorize('periods.archive');

        try {
            app(SalaryPeriodService::class)->arsipkan($this->period);
            session()->flash('status', 'Periode berhasil diarsipkan.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->refreshPeriod();
    }

    public function openRevisi(): void
    {
        Gate::authorize('periods.revise');
        $this->alasanRevisi = '';
        $this->showRevisiModal = true;
    }

    public function ajukanRevisi()
    {
        Gate::authorize('periods.revise');

        $this->validate(['alasanRevisi' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $versiBaru = app(SalaryPeriodService::class)->ajukanRevisi($this->period, auth()->user(), $this->alasanRevisi);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->showRevisiModal = false;
            $this->refreshPeriod();

            return;
        }

        session()->flash('status', 'Revisi diajukan — versi baru dibuat.');
        $this->redirect(route('salary-periods.show', $versiBaru), navigate: true);
    }

    public function hapusPeriode()
    {
        Gate::authorize('periods.delete');

        $namaPeriode = $this->period->nama_periode;
        $versi = $this->period->versi;

        try {
            app(SalaryPeriodService::class)->hapusPeriode($this->period, auth()->user());
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->refreshPeriod();

            return;
        }

        session()->flash('status', "Periode {$namaPeriode} (v{$versi}) berhasil dihapus total.");
        $this->redirect(route('salary-periods.index'), navigate: true);
    }

    public function with(): array
    {
        $salaryRecords = $this->period->salaryRecords();
        $checklist = app(SalaryPeriodValidationService::class)->checklist($this->period);

        return [
            'revisi' => $this->period->revisi()->orderByDesc('versi')->get(),
            'jumlahPegawaiGaji' => $salaryRecords->count(),
            'totalPenghasilan' => $salaryRecords->sum('total_penghasilan_kotor'),
            'totalPotonganPusat' => $salaryRecords->sum('total_potongan_pusat'),
            'totalBersihPusat' => $salaryRecords->sum('bersih_pusat'),
            'totalPotonganFakultas' => $salaryRecords->sum('total_potongan_fakultas'),
            'totalGajiBersihFinal' => $salaryRecords->sum('gaji_bersih_final'),
            'latestImport' => $this->period->salaryImports()->latest()->with('uploader')->first(),
            'checklist' => $checklist,
            'siapFinalisasi' => collect($checklist)->every(fn ($c) => $c['ok']),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('salary-periods.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke daftar periode
        </a>
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

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $period->nama_periode }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Versi {{ $period->versi }}</p>
            </div>
            <x-period-status-badge :status="$period->status" class="text-sm" />
        </div>

        @if ($period->locked_by_user_id)
            <div class="mt-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" /></svg>
                Sedang diproses oleh <strong>{{ $period->lockedBy?->name }}</strong> sejak {{ $period->locked_at?->translatedFormat('d M Y H:i') }} WIB.
            </div>
        @endif

        @if ($period->periode_asal_id)
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                Versi ini adalah hasil revisi dari
                <a href="{{ route('salary-periods.show', $period->periode_asal_id) }}" wire:navigate class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">periode versi {{ $period->versi - 1 }}</a>.
                @if ($period->alasan_revisi)
                    <span class="block mt-1 italic">Alasan: {{ $period->alasan_revisi }}</span>
                @endif
            </div>
        @endif

        @if ($revisi->isNotEmpty())
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                Sudah direvisi menjadi
                <a href="{{ route('salary-periods.show', $revisi->first()) }}" wire:navigate class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">versi {{ $revisi->first()->versi }}</a>
                — versi ini ditandai digantikan (superseded), tetap tersimpan sebagai arsip.
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-2">
            @can('salary_imports.manage')
                @if ($period->status === 'DRAFT')
                    <x-secondary-button href="{{ route('salary-imports.create') }}" wire:navigate>Import Gaji Pusat</x-secondary-button>
                @endif
            @endcan

            @can('salary_processing.manage')
                @if ($period->status === 'DRAFT' && $jumlahPegawaiGaji > 0)
                    <x-secondary-button href="{{ route('salary-processing.create', ['periodId' => $period->id]) }}" wire:navigate>Proses Gaji</x-secondary-button>
                @endif
            @endcan

            @can('payslips.manage')
                @if (in_array($period->status, ['FINAL', 'ARSIP']))
                    <x-secondary-button href="{{ route('payslips.index', $period) }}" wire:navigate>Slip Gaji</x-secondary-button>
                @endif
            @endcan

            @can('deduction_receipts.manage')
                @if (in_array($period->status, ['FINAL', 'ARSIP']))
                    <x-secondary-button href="{{ route('deduction-receipts.index', $period) }}" wire:navigate>Bukti Potongan</x-secondary-button>
                @endif
            @endcan

            @can('submission_records.view')
                @if (in_array($period->status, ['FINAL', 'ARSIP']))
                    <x-secondary-button href="{{ route('rekap-setoran.index', $period) }}" wire:navigate>Rekap Setoran</x-secondary-button>
                @endif
            @endcan

            @can('laporan.view')
                @if (in_array($period->status, ['FINAL', 'ARSIP']))
                    <x-secondary-button href="{{ route('laporan.bulanan', $period) }}" wire:navigate>Laporan Bulanan</x-secondary-button>
                @endif
            @endcan

            @can('periods.submit')
                @if ($period->status === 'DRAFT')
                    <x-primary-button
                        wire:click="submitVerifikasi"
                        wire:confirm="Ajukan periode ini untuk verifikasi? Data akan dikunci untuk diedit oleh user lain."
                        wire:loading.attr="disabled"
                        wire:target="submitVerifikasi"
                        type="button"
                    >
                        <span wire:loading.remove wire:target="submitVerifikasi">Ajukan Verifikasi</span>
                        <span wire:loading wire:target="submitVerifikasi">Mengajukan…</span>
                    </x-primary-button>
                @endif
            @endcan

            @can('periods.verify')
                @if ($period->status === 'VERIFIKASI')
                    @if ($siapFinalisasi)
                        <x-primary-button
                            wire:click="finalisasi"
                            wire:confirm="Finalisasi periode ini? Data akan terkunci dan tidak dapat diedit lagi secara langsung."
                            wire:loading.attr="disabled"
                            wire:target="finalisasi"
                            type="button"
                        >
                            <span wire:loading.remove wire:target="finalisasi">Finalisasi</span>
                            <span wire:loading wire:target="finalisasi">Memfinalisasi…</span>
                        </x-primary-button>
                    @else
                        <x-secondary-button type="button" disabled title="Lihat checklist di bawah — masih ada yang belum lolos">Finalisasi</x-secondary-button>
                    @endif
                    <x-secondary-button wire:click="openKembalikan" type="button">Kembalikan ke Draft</x-secondary-button>
                @endif
            @endcan

            @can('periods.archive')
                @if ($period->status === 'FINAL')
                    <x-secondary-button wire:click="arsipkan" type="button" wire:confirm="Arsipkan periode ini?">Arsipkan</x-secondary-button>
                @endif
            @endcan

            @can('periods.revise')
                @if ($period->status === 'FINAL' && ! $period->status_supersede)
                    <x-secondary-button wire:click="openRevisi" type="button">Ajukan Revisi</x-secondary-button>
                @endif
            @endcan

            @can('periods.delete')
                @if ($period->status === 'DRAFT')
                    <x-secondary-button
                        wire:click="hapusPeriode"
                        type="button"
                        wire:confirm="Hapus total periode {{ $period->nama_periode }} (v{{ $period->versi }})? Seluruh data gaji, potongan, dan hasil import di periode ini akan ikut terhapus permanen. Tindakan ini tidak bisa dibatalkan."
                        class="!border-rose-200 !text-rose-600 hover:!bg-rose-50 dark:!border-rose-500/20 dark:!text-rose-400 dark:hover:!bg-rose-500/10"
                    >
                        Hapus Periode
                    </x-secondary-button>
                @endif
            @endcan

            @php
                $canActNow = match ($period->status) {
                    'DRAFT' => auth()->user()->can('periods.submit'),
                    'VERIFIKASI' => auth()->user()->can('periods.verify'),
                    'FINAL' => auth()->user()->can('periods.archive') || (auth()->user()->can('periods.revise') && ! $period->status_supersede),
                    default => false,
                };
            @endphp
            @unless ($canActNow)
                <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada aksi yang bisa dilakukan untuk peran Anda pada status ini.</p>
            @endunless
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Data Gaji Pusat</p>

        @if ($jumlahPegawaiGaji === 0)
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Belum ada data gaji pusat untuk periode ini.</p>
        @else
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Jumlah Pegawai</p>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ $jumlahPegawaiGaji }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Penghasilan Kotor</p>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Rp {{ number_format($totalPenghasilan, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Potongan Pusat</p>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Rp {{ number_format($totalPotonganPusat, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Bersih Pusat</p>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Rp {{ number_format($totalBersihPusat, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Potongan Fakultas</p>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Rp {{ number_format($totalPotonganFakultas, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Gaji Bersih Final</p>
                    <p class="mt-1 text-lg font-bold text-indigo-600 dark:text-indigo-400">Rp {{ number_format($totalGajiBersihFinal, 0, ',', '.') }}</p>
                </div>
            </div>

            @if ($totalPotonganFakultas == 0)
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Belum ada potongan fakultas yang diperhitungkan — Gaji Bersih Final masih sama dengan Bersih Pusat sampai <a href="{{ route('salary-processing.create', ['periodId' => $period->id]) }}" wire:navigate class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Proses Gaji</a> dijalankan.</p>
            @endif

            @if ($latestImport)
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                    Diimpor dari <span class="font-medium text-slate-500 dark:text-slate-400">{{ $latestImport->nama_file }}</span>
                    oleh {{ $latestImport->uploader?->name }} pada {{ $latestImport->created_at->translatedFormat('d M Y H:i') }} WIB.
                </p>
            @endif

            <div class="mt-4">
                <a href="{{ route('salary-records.index', $period) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                    Lihat rincian per pegawai (tunjangan &amp; potongan per item) →
                </a>
            </div>
        @endif
    </div>

    @if ($jumlahPegawaiGaji > 0 && in_array($period->status, ['DRAFT', 'VERIFIKASI']))
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Checklist Sebelum Finalisasi (§16)</p>
                @if ($siapFinalisasi)
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Siap difinalisasi</span>
                @else
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Belum siap</span>
                @endif
            </div>

            <ul class="mt-4 space-y-2.5">
                @foreach ($checklist as $check)
                    <li class="flex items-start gap-2.5 text-sm">
                        @if ($check['ok'])
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l1.5 1.5 4.5-4.5m5 2a8 8 0 11-16 0 8 8 0 0116 0z" /></svg>
                        @else
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                        @endif
                        <span class="{{ $check['ok'] ? 'text-slate-600 dark:text-slate-300' : 'font-medium text-amber-700 dark:text-amber-300' }}">
                            {{ $check['label'] }}
                            @if ($check['detail'])
                                <span class="block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $check['detail'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-modal-crud show="showKembalikanModal" title="Kembalikan ke Draft" max-width="md">
        <form wire:submit="kembalikanKeDraft" class="p-6">
            <p class="text-sm text-slate-500 dark:text-slate-400">Alasan wajib diisi — akan tercatat di audit log.</p>

            <div class="mt-4">
                <x-input-label for="alasanKembali" value="Alasan" />
                <textarea wire:model="alasanKembali" id="alasanKembali" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="mis. Ada NIP yang belum sesuai di baris 12"></textarea>
                <x-input-error class="mt-2" :messages="$errors->get('alasanKembali')" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                <x-primary-button type="submit">Kembalikan</x-primary-button>
            </div>
        </form>
    </x-modal-crud>

    <x-modal-crud show="showRevisiModal" title="Ajukan Revisi" max-width="md">
        <form wire:submit="ajukanRevisi" class="p-6">
            <p class="text-sm text-slate-500 dark:text-slate-400">Data FINAL tidak diubah langsung — sistem akan membuat versi baru (§17 CLAUDE.md). Alasan wajib diisi.</p>

            <div class="mt-4">
                <x-input-label for="alasanRevisi" value="Alasan" />
                <textarea wire:model="alasanRevisi" id="alasanRevisi" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="mis. Ada koreksi tunjangan setelah slip terbit"></textarea>
                <x-input-error class="mt-2" :messages="$errors->get('alasanRevisi')" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="show = false">Batal</x-secondary-button>
                <x-primary-button type="submit">Ajukan Revisi</x-primary-button>
            </div>
        </form>
    </x-modal-crud>
</div>
