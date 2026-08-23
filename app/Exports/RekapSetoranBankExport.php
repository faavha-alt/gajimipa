<?php

namespace App\Exports;

use App\Models\SalaryPeriod;
use App\Services\Report\RekapSetoranService;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RekapSetoranBankExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly SalaryPeriod $period) {}

    public function collection(): Enumerable
    {
        $perBank = app(RekapSetoranService::class)->perBank($this->period);

        $flat = collect();
        foreach ($perBank as $namaBank => $baris) {
            foreach ($baris as $row) {
                $flat->push([
                    $namaBank,
                    $row['nip'],
                    $row['nama'],
                    $row['nama_rekening'] ?? '-',
                    $row['no_rekening'] ?? '-',
                    $row['total'],
                ]);
            }
        }

        return $flat;
    }

    public function headings(): array
    {
        return ['Bank', 'NIP', 'Nama', 'Nama Rekening', 'No. Rekening', 'Total Potongan (Rp)'];
    }
}
