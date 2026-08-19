<?php

use App\Models\SalaryPeriod;
use App\Services\Salary\SalaryProcessingService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $periodId = '';

    public function mount(): void
    {
        Gate::authorize('salary_processing.manage');

        if (! $this->periodId) {
            $latest = SalaryPeriod::where('status', SalaryPeriod::STATUS_DRAFT)
                ->orderByDesc('tahun')->orderByDesc('bulan')->first();
            $this->periodId = $latest ? (string) $latest->id : '';
        }
    }

    public function getPeriodProperty(): ?SalaryPeriod
    {
        return $this->periodId ? SalaryPeriod::find($this->periodId) : null;
    }

    public function proses(): void
    {
        Gate::authorize('salary_processing.manage');

        try {
            $result = app(SalaryProcessingService::class)->proses($this->period, auth()->user());
            session()->flash('status', "Proses gaji selesai — {$result['updated']} pegawai dihitung ulang.");
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with(): array
    {
        $preview = $this->period ? app(SalaryProcessingService::class)->preview($this->period) : [];

        return [
            'draftPeriods' => SalaryPeriod::where('status', SalaryPeriod::STATUS_DRAFT)->orderByDesc('tahun')->orderByDesc('bulan')->get(),
            'preview' => $preview,
            'jumlahBerubah' => collect($preview)->where('berubah', true)->count(),
            'totalBersihBaru' => collect($preview)->sum('gaji_bersih_final_baru'),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('salary-periods.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke Periode Gaji
        </a>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Proses Gaji</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menggabungkan Bersih dari Pusat dengan Total Potongan Fakultas menjadi Gaji Bersih Final (CLAUDE.md §15). Bisa dijalankan berkali-kali selama periode masih DRAFT.</p>
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
        @if ($draftPeriods->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada periode berstatus DRAFT.</p>
        @else
            <x-input-label for="periodId" value="Periode Gaji (DRAFT)" />
            <select wire:model.live="periodId" id="periodId" class="mt-2 block w-full max-w-sm rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">— Pilih Periode —</option>
                @foreach ($draftPeriods as $p)
                    <option value="{{ $p->id }}">{{ $p->nama_periode }} (v{{ $p->versi }})</option>
                @endforeach
            </select>
        @endif
    </div>

    @if ($this->period)
        @if (empty($preview))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                Periode ini belum punya data gaji pusat. <a href="{{ route('salary-imports.create') }}" wire:navigate class="font-semibold underline">Import Gaji Pusat</a> dulu.
            </div>
        @else
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Tabel di bawah menghitung ulang Potongan Fakultas &amp; Gaji Bersih Final <strong>saat ini juga</strong> dari Data Potongan terbaru, dibandingkan dengan angka yang <strong>terakhir tersimpan</strong> di database (dari proses sebelumnya, atau masih placeholder kalau belum pernah diproses). Ini baru pratinjau — klik "Proses Gaji" untuk menyimpan hasil hitungan ini.
                </p>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ count($preview) }} pegawai
                        </span>
                        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                            Total Gaji Bersih Final (hasil hitung saat ini): Rp{{ number_format($totalBersihBaru, 0, ',', '.') }}
                        </span>
                        @if ($jumlahBerubah > 0)
                            <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                {{ $jumlahBerubah }} pegawai perlu diproses ulang (data potongan sudah berubah sejak proses terakhir)
                            </span>
                        @else
                            <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                Sudah sinkron — tidak ada yang perlu diproses ulang
                            </span>
                        @endif
                    </div>

                    @can('salary_processing.manage')
                        @if ($this->period->status === 'DRAFT')
                            <x-primary-button type="button" wire:click="proses" wire:confirm="Proses gaji untuk {{ count($preview) }} pegawai di periode ini?">
                                Proses Gaji
                            </x-primary-button>
                        @endif
                    @endcan
                </div>

                <div class="mt-4 max-h-[28rem] overflow-auto rounded-xl border border-slate-100 dark:border-slate-800">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-2 font-medium">NIP</th>
                                <th class="px-4 py-2 font-medium">Nama</th>
                                <th class="px-4 py-2 font-medium text-right">Bersih Pusat</th>
                                <th class="px-4 py-2 font-medium text-right">Potongan Fakultas</th>
                                <th class="px-4 py-2 font-medium text-right">Gaji Bersih Final</th>
                                <th class="px-4 py-2 font-medium">Perlu Diproses Ulang?</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($preview as $row)
                                <tr class="{{ $row['berubah'] ? 'bg-amber-50/50 dark:bg-amber-500/5' : '' }}">
                                    <td class="px-4 py-2 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row['nip'] }}</td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $row['nama'] }}</td>
                                    <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($row['bersih_pusat'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($row['total_potongan_fakultas_baru'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-700 dark:text-slate-200">{{ number_format($row['gaji_bersih_final_baru'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-2">
                                        @if ($row['berubah'])
                                            <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">Ya — beda dari data tersimpan</span>
                                        @else
                                            <span class="text-xs text-slate-400">Tidak — sudah sesuai</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
