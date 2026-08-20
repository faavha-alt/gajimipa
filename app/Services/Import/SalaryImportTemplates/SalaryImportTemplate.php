<?php

namespace App\Services\Import\SalaryImportTemplates;

use App\Models\SalaryPeriod;

/**
 * Kontrak "template" format Excel Gaji Pusat. Sistem sudah terbukti punya
 * lebih dari satu format sumber data pusat yang sah (PNS via GPP pemerintah,
 * Non-PNS via sistem penggajian Non-PNS universitas — lih. docs/excel-gaji-
 * nonpns.md §5), jadi SalaryImportService tidak boleh hard-code satu bentuk
 * kolom saja. Setiap template mendeskripsikan kolom wajib & cara membaca
 * baris; SalaryImportService memilih template lewat pencocokan header,
 * lalu logic preview/import (rumus total, all-or-nothing, dst.) tetap sama
 * untuk semua template.
 */
interface SalaryImportTemplate
{
    public function code(): string;

    public function label(): string;

    /** Header (lowercase, trim) yang wajib ada supaya template ini dianggap cocok. */
    public function requiredHeaders(): array;

    /** @return array<string,string> kode_komponen => label komponen penghasilan */
    public function penghasilanFields(): array;

    /** @return array<string,string> kode_komponen => label komponen potongan pusat */
    public function potonganPusatFields(): array;

    public function extractNip(callable $get): string;

    public function extractNama(callable $get): string;

    /**
     * @return array{0:bool,1:string} [cocok dengan periode?, label bulan/tahun yang terbaca dari file]
     */
    public function periodeCocok(callable $get, SalaryPeriod $period): array;

    /**
     * Kolom di file yang jadi "total resmi" untuk validasi silang §12 CLAUDE.md.
     *
     * @return array{column:string,label:string,basis:'kotor'|'bersih'}
     *               basis 'kotor'  = kolom itu seharusnya == total penghasilan
     *               basis 'bersih' = kolom itu seharusnya == total penghasilan − total potongan pusat
     */
    public function totalResmi(): array;

    /**
     * Kode jenis gaji (mis. kdjns pada format PNS) untuk relasi IncomeType, jika ada.
     */
    public function extractIncomeTypeCode(callable $get): ?string;

    /**
     * @return array<string,mixed> kunci: golongan|jabatan|kode_gaji_pokok|status_kawin (hanya yang relevan untuk template ini)
     */
    public function extractSnapshot(callable $get): array;
}
