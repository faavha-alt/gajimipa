<?php

namespace App\Services\Import;

use App\Models\DeductionImport;
use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SafeExcelReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import Potongan Fakultas (CLAUDE.md STEP 10, §13).
 *
 * Beda dari asumsi awal §13 CLAUDE.md ("1 file = 1 jenis potongan"): temuan
 * nyata di docs/excel-potongan.md menunjukkan file fakultas berformat WIDE —
 * satu baris per pegawai, banyak kolom sekaligus = banyak jenis potongan
 * berbeda. Jadi mapping-nya "banyak kolom → banyak Jenis Potongan", bukan
 * "1 kolom nominal → 1 jenis potongan yang dipilih di awal".
 *
 * Identifier pegawai pakai NIP, sama seperti Import Gaji Pusat — bukan NPP
 * (kode_npp_fakultas) seperti keputusan awal C1 di docs/keputusan-desain.md.
 * Keputusan itu ternyata keliru: file yang sebenarnya dipakai fakultas
 * memang punya NIP, dan kode_npp_fakultas tidak pernah terpakai — kolomnya
 * sudah dihapus total (migration 2026_08_20_145744).
 *
 * Potongan hanya bisa dikaitkan ke pegawai yang SUDAH punya salary_record di
 * periode ini (dari Import Gaji Pusat, STEP 8) — deduction_records terikat ke
 * salary_record_id, bukan employee+periode langsung.
 */
class DeductionImportService
{
    public const MAX_ROWS = 1000;

    public function readSheet(string $absolutePath): array
    {
        return SafeExcelReader::readFirstSheet($absolutePath);
    }

    /**
     * Tebak pemetaan kolom → target ('nip' | 'nama' | 'type:{id}' | 'ignore'),
     * dipakai sebagai isian awal langkah Petakan Kolom supaya operator tidak
     * perlu pilih manual satu-satu utk tiap kolom Jenis Potongan — tetap bisa
     * diubah/dikoreksi sebelum lanjut.
     *
     * Skor kecocokan = total PANJANG HURUF kata bermakna dari kode/nama Jenis
     * Potongan yang muncul sebagai substring di header (setelah SPASI
     * dibuang, bukan cuma dinormalisasi) — bukan exact word-match, dan
     * BUKAN cuma jumlah kata. Dua alasan sengaja begini (ditemukan lewat uji
     * coba nyata terhadap header asli docs/excel-potongan.md, bukan cuma
     * fixture buatan):
     *  1. Kata yang di kode/nama tergabung jadi satu ("Dharmawanita") harus
     *     tetap cocok dengan header yang menuliskannya terpisah ("Iuran
     *     Dharma Wanita") — makanya dicari via substring pada header yang
     *     spasinya sudah dibuang, bukan pencocokan kata-demi-kata.
     *  2. Skor berdasar PANJANG (bukan jumlah) kata yang cocok, supaya kata
     *     umum yang muncul di banyak Jenis Potongan sekaligus (mis. "iuran",
     *     "fmipa", "mipa" — 15 Jenis Potongan asli fakultas banyak yang
     *     mengandung salah satu ini) tidak menang cuma karena kebetulan
     *     nyambung, dibanding kata yang jauh lebih spesifik (mis.
     *     "kesejahteraan"). Tanpa ini, header "Iuran Kesejahteraan" salah
     *     kepetakan ke Jenis Potongan lain yang cuma sama-sama punya kata
     *     "iuran", padahal ada Jenis Potongan "Kesejahteraan" yang jelas
     *     lebih cocok.
     * Kolom yang tidak cukup mirip dibiarkan '— Abaikan —'.
     *
     * @param  \Illuminate\Support\Collection<int,DeductionType>  $deductionTypes
     * @return array<int,string>
     */
    public function guessMapping(array $headers, $deductionTypes): array
    {
        $typeWords = $deductionTypes->mapWithKeys(fn (DeductionType $type) => [
            $type->id => array_unique(array_merge(
                self::normalizedWords($type->kode),
                self::normalizedWords($type->nama),
            )),
        ]);

        $mapping = [];
        foreach ($headers as $index => $header) {
            $normalized = Str::lower(trim((string) $header));

            if (in_array($normalized, ['nip'], true)) {
                $mapping[$index] = 'nip';

                continue;
            }

            if (in_array($normalized, ['nama', 'nama pegawai'], true)) {
                $mapping[$index] = 'nama';

                continue;
            }

            $squashedHeader = self::squash($header);
            if ($squashedHeader === '') {
                $mapping[$index] = 'ignore';

                continue;
            }

            $bestTypeId = null;
            $bestScore = 0;
            foreach ($typeWords as $typeId => $words) {
                $matched = array_filter($words, fn ($word) => str_contains($squashedHeader, $word));
                $score = array_sum(array_map('mb_strlen', $matched));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestTypeId = $typeId;
                }
            }

            $mapping[$index] = $bestScore > 0 ? "type:{$bestTypeId}" : 'ignore';
        }

        return $mapping;
    }

    /**
     * @return array<int,string> kata bermakna (huruf/angka saja, huruf kecil, panjang > 2)
     */
    private static function normalizedWords(string $text): array
    {
        $normalized = Str::lower(preg_replace('/[^a-z0-9]+/i', ' ', $text) ?? '');

        return array_values(array_filter(explode(' ', $normalized), fn ($w) => mb_strlen($w) > 2));
    }

    /**
     * Huruf/angka saja, huruf kecil, TANPA spasi sama sekali — dipakai sbg
     * "haystack" pencarian substring supaya beda cara pemisahan kata (kata
     * gabung vs terpisah) tidak menggagalkan pencocokan.
     */
    private static function squash(string $text): string
    {
        return Str::lower(preg_replace('/[^a-z0-9]+/i', '', $text) ?? '');
    }

    /**
     * @param  array<int,string>  $mapping  Index kolom (0-based) => 'nip' | 'ignore' | "type:{deduction_type_id}"
     * @return array<int,array{row_number:int,nip:mixed,nama_tampil:mixed,employee_id:?int,salary_record_id:?int,errors:array,nominal_per_jenis:array,total:float}>
     */
    public function buildPreview(array $rows, array $mapping, SalaryPeriod $period): array
    {
        $dataRows = array_slice($rows, 1);
        $preview = [];

        $nipColIndex = array_search('nip', $mapping, true);
        $typeColumns = collect($mapping)->filter(fn ($v) => str_starts_with((string) $v, 'type:'));

        $deductionTypes = DeductionType::whereIn('id', $typeColumns->map(fn ($v) => (int) Str::after($v, 'type:')))->get()->keyBy('id');

        $nipCounts = collect($dataRows)
            ->map(fn ($row) => $nipColIndex !== false ? trim((string) ($row[$nipColIndex] ?? '')) : '')
            ->filter(fn ($v) => $v !== '')
            ->countBy();

        foreach ($dataRows as $i => $row) {
            $rowNumber = $i + 2;
            $errors = [];

            $nip = $nipColIndex !== false ? trim((string) ($row[$nipColIndex] ?? '')) : '';

            if ($nip === '') {
                $errors[] = 'NIP kosong.';
            } elseif (($nipCounts[$nip] ?? 0) > 1) {
                $errors[] = "NIP '{$nip}' duplikat di dalam file ini.";
            }

            $employee = $nip !== '' ? Employee::where('nip', $nip)->first() : null;
            if ($nip !== '' && ! $employee) {
                $errors[] = "NIP '{$nip}' tidak ditemukan di Master Pegawai.";
            }

            $salaryRecord = null;
            if ($employee) {
                $salaryRecord = SalaryRecord::where('salary_period_id', $period->id)->where('employee_id', $employee->id)->first();
                if (! $salaryRecord) {
                    $errors[] = "Pegawai '{$employee->nama}' belum punya data gaji pusat di periode ini — import Gaji Pusat dulu.";
                }
            }

            $nominalPerJenis = [];
            foreach ($typeColumns as $colIndex => $mapValue) {
                $typeId = (int) Str::after($mapValue, 'type:');
                $raw = $row[$colIndex] ?? null;

                if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                    // Sel non-numerik (kosong, atau catatan bebas spt "PENSIUN
                    // BULAN JUNI") dianggap tidak ada potongan, BUKAN error —
                    // sesuai temuan docs/excel-potongan.md §7 (kasus pensiun).
                    continue;
                }

                $value = (float) $raw;
                if ($value < 0) {
                    $errors[] = "Nominal negatif pada kolom '{$deductionTypes[$typeId]->nama}'.";

                    continue;
                }

                if ($value > 0) {
                    $nominalPerJenis[$typeId] = $value;
                }
            }

            $preview[] = [
                'row_number' => $rowNumber,
                'nip' => $nip,
                'nama_tampil' => $employee?->nama ?? ($row[array_search('nama', $mapping, true)] ?? '—'),
                'employee_id' => $employee?->id,
                'salary_record_id' => $salaryRecord?->id,
                'errors' => $errors,
                'nominal_per_jenis' => $nominalPerJenis,
                'total' => array_sum($nominalPerJenis),
            ];
        }

        return $preview;
    }

    /**
     * All-or-nothing. Mengganti seluruh deduction_records ber-sumber IMPORT
     * untuk periode ini (entri MANUAL tidak disentuh — koreksi manual tidak
     * boleh hilang cuma karena file sumber diupload ulang).
     */
    public function import(SalaryPeriod $period, array $preview, array $deductionTypeIds, string $namaFile, string $pathFile, User $user): DeductionImport
    {
        if (collect($preview)->contains(fn ($row) => ! empty($row['errors']))) {
            throw new \RuntimeException('Masih ada baris berisi error — perbaiki dulu sebelum import bisa dikonfirmasi.');
        }

        return DB::transaction(function () use ($period, $preview, $deductionTypeIds, $namaFile, $pathFile, $user) {
            DeductionRecord::whereIn('salary_record_id', $period->salaryRecords()->pluck('id'))
                ->where('sumber', DeductionRecord::SUMBER_IMPORT)
                ->whereIn('deduction_type_id', $deductionTypeIds)
                ->delete();

            $deductionImport = DeductionImport::create([
                'salary_period_id' => $period->id,
                'nama_file' => $namaFile,
                'path_file' => $pathFile,
                'diupload_oleh' => $user->id,
                'status' => 'SELESAI',
                'jumlah_baris' => count($preview),
                'jumlah_error' => 0,
            ]);

            $jumlahRecord = 0;
            foreach ($preview as $row) {
                foreach ($row['nominal_per_jenis'] as $typeId => $nominal) {
                    DeductionRecord::create([
                        'salary_record_id' => $row['salary_record_id'],
                        'deduction_type_id' => $typeId,
                        'deduction_import_id' => $deductionImport->id,
                        'nominal' => $nominal,
                        'sumber' => DeductionRecord::SUMBER_IMPORT,
                        'dibuat_oleh' => $user->id,
                    ]);
                    $jumlahRecord++;
                }
            }

            AuditLogger::log('Import Potongan Fakultas', "Import potongan periode {$period->nama_periode}: ".count($preview)." baris pegawai, {$jumlahRecord} entri potongan.", [
                'salary_period_id' => $period->id,
                'deduction_import_id' => $deductionImport->id,
                'jumlah_record' => $jumlahRecord,
            ]);

            return $deductionImport;
        });
    }
}
