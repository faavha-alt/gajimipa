<?php

namespace App\Exports;

use App\Services\Report\LaporanService;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LaporanTahunanExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly int $tahun) {}

    public function collection(): Enumerable
    {
        return app(LaporanService::class)->tahunan($this->tahun)['perBulan']->map(fn ($row) => [
            $row['nama_bulan'],
            $row['periode']->versi,
            $row['jumlah_pegawai'],
            $row['total_penghasilan_kotor'],
            $row['total_potongan_pusat'],
            $row['total_potongan_fakultas'],
            $row['total_gaji_bersih'],
        ]);
    }

    public function headings(): array
    {
        return ['Bulan', 'Versi', 'Jumlah Pegawai', 'Penghasilan Kotor', 'Potongan Pusat', 'Potongan Fakultas', 'Gaji Bersih Final'];
    }
}
