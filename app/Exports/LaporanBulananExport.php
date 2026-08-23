<?php

namespace App\Exports;

use App\Models\SalaryPeriod;
use App\Services\Report\LaporanService;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LaporanBulananExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly SalaryPeriod $period) {}

    public function collection(): Enumerable
    {
        return app(LaporanService::class)->bulanan($this->period)['pegawai']->map(fn ($r) => [
            $r->nip_snapshot,
            $r->employee?->nama ?? $r->nama_snapshot,
            $r->unit_snapshot ?: '-',
            (float) $r->total_penghasilan_kotor,
            (float) $r->total_potongan_pusat,
            (float) $r->bersih_pusat,
            (float) $r->total_potongan_fakultas,
            (float) $r->gaji_bersih_final,
        ]);
    }

    public function headings(): array
    {
        return ['NIP', 'Nama', 'Unit', 'Penghasilan Kotor', 'Potongan Pusat', 'Bersih Pusat', 'Potongan Fakultas', 'Gaji Bersih Final'];
    }
}
