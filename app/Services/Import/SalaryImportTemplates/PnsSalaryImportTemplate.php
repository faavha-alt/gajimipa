<?php

namespace App\Services\Import\SalaryImportTemplates;

use App\Models\SalaryImport;
use App\Models\SalaryPeriod;

/**
 * Format export "ADK Gaji" aplikasi GPP pemerintah untuk pegawai PNS.
 * Lih. docs/excel-gaji-pusat.md untuk analisis lengkap kolom & rumus.
 */
class PnsSalaryImportTemplate implements SalaryImportTemplate
{
    public function code(): string
    {
        return SalaryImport::FORMAT_PNS;
    }

    public function label(): string
    {
        return 'PNS (GPP Pusat)';
    }

    public function requiredHeaders(): array
    {
        return ['nip', 'nmpeg', 'bulan', 'tahun', 'gjpokok', 'bersih'];
    }

    public function penghasilanFields(): array
    {
        return [
            'gjpokok' => 'Gaji Pokok',
            'tjistri' => 'Tunjangan Istri/Suami',
            'tjanak' => 'Tunjangan Anak',
            'tjupns' => 'Tunjangan Umum PNS',
            'tjstruk' => 'Tunjangan Struktural',
            'tjfungs' => 'Tunjangan Fungsional',
            'tjdaerah' => 'Tunjangan Daerah',
            'tjpencil' => 'Tunjangan Pengabdian',
            'tjlain' => 'Tunjangan Lainnya',
            'tjkompen' => 'Tunjangan Kompensasi',
            'pembul' => 'Pembulatan',
            'tjberas' => 'Tunjangan Beras',
            'tjpph' => 'Tunjangan PPh',
        ];
    }

    public function potonganPusatFields(): array
    {
        return [
            'potpfkbul' => 'Potongan PFK Bulanan',
            'potpfk2' => 'Potongan PFK 2%',
            'potpfk10' => 'Potongan PFK 10%',
            'potpph' => 'Potongan PPh',
            'potswrum' => 'Potongan Sewa Rumah',
            'potkelbtj' => 'Potongan Kelebihan Tunjangan',
            'potlain' => 'Potongan Lainnya',
            'pottabrum' => 'Potongan Tabungan Perumahan',
            'bpjs' => 'Potongan BPJS',
            'bpjs2' => 'Potongan BPJS 2',
        ];
    }

    public function extractNip(callable $get): string
    {
        return trim((string) $get('nip'));
    }

    public function extractNama(callable $get): string
    {
        return trim((string) $get('nmpeg'));
    }

    public function periodeCocok(callable $get, SalaryPeriod $period): array
    {
        $bulan = $get('bulan');
        $tahun = $get('tahun');
        $cocok = (int) $bulan === (int) $period->bulan && (int) $tahun === (int) $period->tahun;

        return [$cocok, "{$bulan}/{$tahun}"];
    }

    public function totalResmi(): array
    {
        return ['column' => 'bersih', 'label' => 'Bersih (AQ)', 'basis' => 'bersih'];
    }

    public function extractIncomeTypeCode(callable $get): ?string
    {
        $kode = $get('kdjns');

        return filled($kode) ? (string) $kode : null;
    }

    public function extractSnapshot(callable $get): array
    {
        return array_filter([
            'golongan' => $get('kdgol'),
            'jabatan' => $get('kdjab'),
            'kode_gaji_pokok' => $get('kdgapok'),
            'status_kawin' => $get('kdkawin'),
        ], fn ($v) => filled($v));
    }
}
