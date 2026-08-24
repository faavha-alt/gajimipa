<?php

use App\Models\SalaryPeriod;
use App\Models\SubmissionRecord;
use App\Services\Report\RekapSetoranService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public SalaryPeriod $period;

    public function mount(SalaryPeriod $period): void
    {
        Gate::authorize('submission_records.view');
        $this->period = $period;
    }

    public function generate(): void
    {
        Gate::authorize('submission_records.manage');

        try {
            app(RekapSetoranService::class)->generate($this->period, auth()->user());
            session()->flash('status', 'Rekap setoran per jenis potongan berhasil dibuat/diperbarui.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with(): array
    {
        $service = app(RekapSetoranService::class);

        return [
            'bisaGenerate' => $service->bisaGenerate($this->period),
            'perJenis' => $service->perJenisPotongan($this->period),
            'perBank' => $service->perBank($this->period),
            'lastGenerated' => SubmissionRecord::where('salary_period_id', $this->period->id)
                ->with('creator')
                ->latest()
                ->first(),
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <div>
        <a href="{{ route('salary-periods.show', $period) }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali ke {{ $period->nama_periode }}
        </a>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Rekap Setoran Potongan — {{ $period->nama_periode }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Bukan transfer/pembayaran — rekap ini dipakai untuk proses setoran/tarik tunai di luar aplikasi (§20 CLAUDE.md).</p>
        </div>

        @can('submission_records.manage')
            @if ($bisaGenerate)
                <x-primary-button type="button" wire:click="generate" wire:confirm="Generate/perbarui rekap setoran per jenis potongan untuk periode ini?">Generate Rekap</x-primary-button>
            @endif
        @endcan
    </div>

    @if (! $bisaGenerate)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            Periode ini belum FINAL — rekap setoran baru bisa dibuat setelah periode difinalisasi (§17 CLAUDE.md).
        </div>
    @endif

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

    @if ($lastGenerated)
        <p class="text-xs text-slate-500 dark:text-slate-400">Terakhir digenerate oleh {{ $lastGenerated->creator?->name }} pada {{ $lastGenerated->created_at->translatedFormat('d M Y H:i') }} WIB.</p>
    @endif

    {{-- Rekap per Jenis Potongan --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Per Jenis Potongan</h3>
            <div class="flex gap-2">
                <a href="{{ route('rekap-setoran.jenis-pdf', $period) }}" target="_blank" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">PDF</a>
                <a href="{{ route('rekap-setoran.jenis-excel', $period) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Excel</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Jenis Potongan</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Jumlah Pegawai</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($perJenis as $row)
                        <tr>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">{{ $row['nama'] }}</td>
                            <td class="px-5 py-3 text-right text-slate-500 dark:text-slate-400">{{ $row['jumlah_pegawai'] }}</td>
                            <td class="px-5 py-3 text-right font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($row['total_nominal'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data potongan di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($perJenis->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-800/60">
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200">Total Keseluruhan</td>
                            <td class="px-5 py-3 text-right text-slate-700 dark:text-slate-200">{{ $perJenis->sum('jumlah_pegawai') }}</td>
                            <td class="px-5 py-3 text-right text-slate-900 dark:text-white">Rp {{ number_format($perJenis->sum('total_nominal'), 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Rekap per Bank --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            <div>
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Per Bank</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Untuk dikirim ke bank — proses tarik tunai potongan fakultas di luar aplikasi.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('rekap-setoran.bank-pdf', $period) }}" target="_blank" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">PDF</a>
                <a href="{{ route('rekap-setoran.bank-excel', $period) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Excel</a>
            </div>
        </div>

        @forelse ($perBank as $namaBank => $baris)
            <div class="border-b border-slate-100 last:border-b-0 dark:border-slate-800">
                <div class="flex items-center justify-between bg-slate-50/60 px-5 py-2 dark:bg-slate-800/30">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $namaBank }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $baris->count() }} pegawai &middot; Rp {{ number_format($baris->sum('total'), 0, ',', '.') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($baris as $row)
                                <tr>
                                    <td class="w-40 px-5 py-2 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $row['nip'] }}</td>
                                    <td class="px-5 py-2 text-slate-700 dark:text-slate-200">{{ $row['nama'] }}</td>
                                    <td class="px-5 py-2 text-slate-500 dark:text-slate-400">{{ $row['no_rekening'] ?? '— belum ada no. rekening' }}</td>
                                    <td class="px-5 py-2 text-right font-medium text-slate-700 dark:text-slate-200">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data potongan di periode ini.</p>
        @endforelse
    </div>
</div>
