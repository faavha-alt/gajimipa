<?php

namespace App\Exports;

use App\Models\SalaryPeriod;
use App\Services\Report\RekapSetoranService;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RekapSetoranJenisExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly SalaryPeriod $period) {}

    public function collection(): Enumerable
    {
        return app(RekapSetoranService::class)->perJenisPotongan($this->period);
    }

    public function headings(): array
    {
        return ['Jenis Potongan', 'Jumlah Pegawai', 'Total Nominal (Rp)'];
    }

    public function map($row): array
    {
        return [$row['nama'], $row['jumlah_pegawai'], $row['total_nominal']];
    }
}
