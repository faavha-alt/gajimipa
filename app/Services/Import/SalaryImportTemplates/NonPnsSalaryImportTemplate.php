<?php

namespace App\Services\Import\SalaryImportTemplates;

use App\Models\SalaryImport;
use App\Models\SalaryPeriod;

/**
 * Format export sistem penggajian Non-PNS universitas. Berbeda total dari
 * format PNS (docs/excel-gaji-nonpns.md): periode digabung di satu kolom
 * TAHUNBULAN, komponen penghasilan/potongan jauh lebih sedikit, dan tidak
 * ada kolom "bersih setelah potongan pusat" — hanya GAJI_KOTOR (kotor).
 */
class NonPnsSalaryImportTemplate implements SalaryImportTemplate
{
    public function code(): string
    {
        return SalaryImport::FORMAT_NON_PNS;
    }

    public function label(): string
    {
        return 'Non-PNS (Universitas)';
    }

    public function requiredHeaders(): array
    {
        return ['nip', 'nama', 'tahunbulan', 'gaji_pokok', 'gaji_kotor'];
    }

    public function penghasilanFields(): array
    {
        return [
            'gaji_pokok' => 'Gaji Pokok',
            'tunj_istri' => 'Tunjangan Istri/Suami',
            'tunj_fungsional' => 'Tunjangan Fungsional',
            'tunj_anak' => 'Tunjangan Anak',
            'tunj_beras' => 'Tunjangan Beras',
        ];
    }

    public function potonganPusatFields(): array
    {
        return [
            'pot_pph21' => 'Potongan PPh 21',
            'pot_iwp' => 'Potongan Iuran Wajib Pegawai',
            'pot_bpjs' => 'Potongan BPJS',
        ];
    }

    public function extractNip(callable $get): string
    {
        return trim((string) $get('nip'));
    }

    public function extractNama(callable $get): string
    {
        return trim((string) $get('nama'));
    }

    public function periodeCocok(callable $get, SalaryPeriod $period): array
    {
        $tahunBulan = trim((string) $get('tahunbulan'));
        $tahun = (int) substr($tahunBulan, 0, 4);
        $bulan = (int) substr($tahunBulan, 4, 2);
        $cocok = $bulan === (int) $period->bulan && $tahun === (int) $period->tahun;

        return [$cocok, "{$bulan}/{$tahun}"];
    }

    public function totalResmi(): array
    {
        return ['column' => 'gaji_kotor', 'label' => 'Gaji Kotor', 'basis' => 'kotor'];
    }

    public function extractIncomeTypeCode(callable $get): ?string
    {
        return null;
    }

    public function extractSnapshot(callable $get): array
    {
        return array_filter([
            'jabatan' => $get('fungsional'),
        ], fn ($v) => filled($v));
    }
}
