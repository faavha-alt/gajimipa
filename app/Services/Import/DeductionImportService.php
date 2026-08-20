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
 * Identifier pegawai di file fakultas bukan NIP, melainkan NPP
 * (employees.kode_npp_fakultas) — lihat docs/excel-potongan.md §3/§5.
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
     * @param  array<int,string>  $mapping  Index kolom (0-based) => 'npp' | 'ignore' | "type:{deduction_type_id}"
     * @return array<int,array{row_number:int,npp:mixed,nama_tampil:mixed,employee_id:?int,salary_record_id:?int,errors:array,nominal_per_jenis:array,total:float}>
     */
    public function buildPreview(array $rows, array $mapping, SalaryPeriod $period): array
    {
        $dataRows = array_slice($rows, 1);
        $preview = [];

        $nppColIndex = array_search('npp', $mapping, true);
        $typeColumns = collect($mapping)->filter(fn ($v) => str_starts_with((string) $v, 'type:'));

        $deductionTypes = DeductionType::whereIn('id', $typeColumns->map(fn ($v) => (int) Str::after($v, 'type:')))->get()->keyBy('id');

        $nppCounts = collect($dataRows)
            ->map(fn ($row) => $nppColIndex !== false ? trim((string) ($row[$nppColIndex] ?? '')) : '')
            ->filter(fn ($v) => $v !== '')
            ->countBy();

        foreach ($dataRows as $i => $row) {
            $rowNumber = $i + 2;
            $errors = [];

            $npp = $nppColIndex !== false ? trim((string) ($row[$nppColIndex] ?? '')) : '';

            if ($npp === '') {
                $errors[] = 'NPP kosong.';
            } elseif (($nppCounts[$npp] ?? 0) > 1) {
                $errors[] = "NPP '{$npp}' duplikat di dalam file ini.";
            }

            $employee = $npp !== '' ? Employee::where('kode_npp_fakultas', $npp)->first() : null;
            if ($npp !== '' && ! $employee) {
                $errors[] = "NPP '{$npp}' tidak ditemukan di Master Pegawai (kolom Kode NPP Fakultas).";
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
                'npp' => $npp,
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
