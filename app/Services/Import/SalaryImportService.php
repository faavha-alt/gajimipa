<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\IncomeType;
use App\Models\SalaryComponent;
use App\Models\SalaryImport;
use App\Models\SalaryImportRow;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import Gaji Pusat (CLAUDE.md STEP 8, §12). Beda dengan Import Master Pegawai
 * (STEP 6): formatnya baku — export "ADK Gaji" aplikasi GPP pemerintah, 50
 * kolom dengan nama field tetap (docs/excel-gaji-pusat.md §2) — jadi tidak
 * perlu mapping kolom manual, cukup deteksi & validasi struktur header.
 *
 * Rumus yang diverifikasi (docs/pemetaan-field-gaji.md §3):
 *   Total Penghasilan Kotor − Total Potongan Pusat = bersih (kolom AQ)
 *
 * Data pusat bersifat read-only / snapshot (§3.1, §8 CLAUDE.md) — disimpan
 * apa adanya ke salary_records + salary_components, tidak pernah diedit
 * manual. Import bersifat all-or-nothing per periode (§12).
 */
class SalaryImportService
{
    public const MAX_ROWS = 1000;

    /** Kolom wajib ada di header — tanpa ini struktur file dianggap tidak dikenali. */
    private const REQUIRED_HEADERS = ['nip', 'nmpeg', 'bulan', 'tahun', 'gjpokok', 'bersih'];

    private const PENGHASILAN_FIELDS = [
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

    private const POTONGAN_PUSAT_FIELDS = [
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

    public function readSheet(string $absolutePath): array
    {
        $sheets = Excel::toCollection(null, $absolutePath);

        return ($sheets->first() ?? collect())
            ->map(fn ($row) => $row->map(fn ($cell) => is_string($cell) ? trim($cell) : $cell)->toArray())
            ->filter(fn ($row) => collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())
            ->values()
            ->toArray();
    }

    /**
     * @return array{ok:bool,missing:array<int,string>,columnMap:array<string,int>}
     */
    public function detectStructure(array $headerRow): array
    {
        $columnMap = [];
        foreach ($headerRow as $index => $header) {
            $key = mb_strtolower(trim((string) $header));
            if ($key !== '') {
                $columnMap[$key] = $index;
            }
        }

        $missing = array_values(array_filter(
            self::REQUIRED_HEADERS,
            fn ($field) => ! array_key_exists($field, $columnMap)
        ));

        return ['ok' => empty($missing), 'missing' => $missing, 'columnMap' => $columnMap];
    }

    /**
     * @return array<int,array{row_number:int,nip:mixed,nama:mixed,employee_id:?int,errors:array,penghasilan:array,potongan:array,total_penghasilan:float,total_potongan:float,bersih_hitung:float,bersih_file:mixed,snapshot:array}>
     */
    public function buildPreview(array $rows, array $columnMap, SalaryPeriod $period): array
    {
        $dataRows = array_slice($rows, 1);
        $preview = [];

        $nipCounts = collect($dataRows)
            ->map(fn ($row) => (string) ($row[$columnMap['nip']] ?? ''))
            ->filter(fn ($v) => $v !== '')
            ->countBy();

        foreach ($dataRows as $i => $row) {
            $rowNumber = $i + 2;
            $errors = [];

            $get = fn (string $field) => array_key_exists($field, $columnMap) ? ($row[$columnMap[$field]] ?? null) : null;

            $nip = trim((string) $get('nip'));
            $nama = trim((string) $get('nmpeg'));
            $bulan = $get('bulan');
            $tahun = $get('tahun');

            if ($nip === '') {
                $errors[] = 'NIP kosong.';
            } elseif (($nipCounts[$nip] ?? 0) > 1) {
                $errors[] = "NIP '{$nip}' duplikat di dalam file ini.";
            }

            $employee = $nip !== '' ? Employee::where('nip', $nip)->first() : null;
            if ($nip !== '' && ! $employee) {
                $errors[] = "NIP '{$nip}' tidak ditemukan di Master Pegawai.";
            } elseif ($employee && ! $employee->status_aktif) {
                $errors[] = "Pegawai '{$employee->nama}' berstatus tidak aktif.";
            }

            if ((int) $bulan !== (int) $period->bulan || (int) $tahun !== (int) $period->tahun) {
                $errors[] = "Bulan/Tahun file ({$bulan}/{$tahun}) tidak sesuai periode yang dipilih ({$period->bulan}/{$period->tahun}).";
            }

            $penghasilan = [];
            foreach (self::PENGHASILAN_FIELDS as $field => $label) {
                [$value, $error] = $this->parseNominal($get($field), $label);
                $penghasilan[$field] = $value;
                if ($error) {
                    $errors[] = $error;
                }
            }

            $potongan = [];
            foreach (self::POTONGAN_PUSAT_FIELDS as $field => $label) {
                [$value, $error] = $this->parseNominal($get($field), $label);
                $potongan[$field] = $value;
                if ($error) {
                    $errors[] = $error;
                }
            }

            [$bersihFile, $bersihError] = $this->parseNominal($get('bersih'), 'Bersih (AQ)');
            if ($bersihError) {
                $errors[] = $bersihError;
            }

            $totalPenghasilan = array_sum($penghasilan);
            $totalPotongan = array_sum($potongan);
            $bersihHitung = $totalPenghasilan - $totalPotongan;

            if (! $bersihError && round($bersihHitung, 2) !== round((float) $bersihFile, 2)) {
                $errors[] = "Total tidak sesuai — hitung ulang Rp".number_format($bersihHitung, 0, ',', '.')." tapi file bilang Rp".number_format((float) $bersihFile, 0, ',', '.').".";
            }

            $preview[] = [
                'row_number' => $rowNumber,
                'nip' => $nip,
                'nama' => $nama,
                'employee_id' => $employee?->id,
                'errors' => $errors,
                'penghasilan' => $penghasilan,
                'potongan' => $potongan,
                'total_penghasilan' => $totalPenghasilan,
                'total_potongan' => $totalPotongan,
                'bersih_hitung' => $bersihHitung,
                'bersih_file' => $bersihFile,
                'snapshot' => [
                    'kdjns' => $get('kdjns'),
                    'kdgol' => $get('kdgol'),
                    'kdjab' => $get('kdjab'),
                    'kdgapok' => $get('kdgapok'),
                    'kdkawin' => $get('kdkawin'),
                ],
                'data_mentah' => array_combine(array_keys($columnMap), array_map(fn ($idx) => $row[$idx] ?? null, array_values($columnMap))),
            ];
        }

        return $preview;
    }

    /**
     * @return array{0:float,1:?string}
     */
    private function parseNominal(mixed $raw, string $label): array
    {
        if ($raw === null || $raw === '') {
            return [0.0, null];
        }

        if (! is_numeric($raw)) {
            return [0.0, "Nominal tidak valid pada kolom {$label}: '{$raw}'."];
        }

        $value = (float) $raw;
        if ($value < 0) {
            return [$value, "Nominal negatif pada kolom {$label}."];
        }

        return [$value, null];
    }

    /**
     * @param  array<int,array>  $preview
     */
    public function import(SalaryPeriod $period, array $preview, string $namaFile, string $pathFile, User $user): SalaryImport
    {
        if (collect($preview)->contains(fn ($row) => ! empty($row['errors']))) {
            throw new \RuntimeException('Masih ada baris berisi error — perbaiki dulu sebelum import bisa dikonfirmasi.');
        }

        if ($period->salaryRecords()->exists()) {
            throw new \RuntimeException('Periode ini sudah memiliki data gaji. Hapus data lama sebelum import ulang.');
        }

        return DB::transaction(function () use ($period, $preview, $namaFile, $pathFile, $user) {
            $salaryImport = SalaryImport::create([
                'salary_period_id' => $period->id,
                'nama_file' => $namaFile,
                'path_file' => $pathFile,
                'diupload_oleh' => $user->id,
                'status' => 'SELESAI',
                'jumlah_baris' => count($preview),
                'jumlah_error' => 0,
            ]);

            foreach ($preview as $row) {
                $employee = Employee::find($row['employee_id']);

                $incomeType = null;
                if (filled($row['snapshot']['kdjns'] ?? null)) {
                    $incomeType = IncomeType::firstOrCreate(
                        ['kode' => (string) $row['snapshot']['kdjns']],
                        ['nama' => 'Jenis Gaji '.$row['snapshot']['kdjns'], 'status_aktif' => true]
                    );
                }

                $salaryImportRow = SalaryImportRow::create([
                    'salary_import_id' => $salaryImport->id,
                    'nomor_baris' => $row['row_number'],
                    'data_mentah' => $row['data_mentah'],
                    'employee_id' => $employee->id,
                    'status' => 'OK',
                ]);

                $salaryRecord = SalaryRecord::create([
                    'salary_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'salary_import_id' => $salaryImport->id,
                    'income_type_id' => $incomeType?->id,
                    'nip_snapshot' => $row['nip'],
                    'nama_snapshot' => $row['nama'],
                    'unit_snapshot' => $employee->unit?->nama_unit,
                    'golongan_snapshot' => $row['snapshot']['kdgol'],
                    'jabatan_snapshot' => $row['snapshot']['kdjab'],
                    'kode_gaji_pokok_snapshot' => $row['snapshot']['kdgapok'],
                    'status_kawin_snapshot' => $row['snapshot']['kdkawin'],
                    'total_penghasilan_kotor' => $row['total_penghasilan'],
                    'total_potongan_pusat' => $row['total_potongan'],
                    'bersih_pusat' => $row['bersih_file'],
                    'total_potongan_fakultas' => 0,
                    'gaji_bersih_final' => $row['bersih_file'],
                ]);

                foreach (self::PENGHASILAN_FIELDS as $field => $label) {
                    if ($row['penghasilan'][$field] != 0) {
                        SalaryComponent::create([
                            'salary_record_id' => $salaryRecord->id,
                            'kategori' => SalaryComponent::KATEGORI_PENGHASILAN,
                            'kode_komponen' => $field,
                            'nama_komponen' => $label,
                            'nominal' => $row['penghasilan'][$field],
                        ]);
                    }
                }

                foreach (self::POTONGAN_PUSAT_FIELDS as $field => $label) {
                    if ($row['potongan'][$field] != 0) {
                        SalaryComponent::create([
                            'salary_record_id' => $salaryRecord->id,
                            'kategori' => SalaryComponent::KATEGORI_POTONGAN_PUSAT,
                            'kode_komponen' => $field,
                            'nama_komponen' => $label,
                            'nominal' => $row['potongan'][$field],
                        ]);
                    }
                }

                $employee->update(array_filter([
                    'golongan_saat_ini' => $row['snapshot']['kdgol'],
                    'jabatan_saat_ini' => $row['snapshot']['kdjab'],
                    'kode_gaji_pokok_saat_ini' => $row['snapshot']['kdgapok'],
                    'status_kawin_saat_ini' => $row['snapshot']['kdkawin'],
                ], fn ($v) => filled($v)));
            }

            AuditLogger::log('Import Gaji Pusat', "Import gaji pusat untuk periode {$period->nama_periode}: ".count($preview)." baris berhasil.", [
                'salary_period_id' => $period->id,
                'salary_import_id' => $salaryImport->id,
                'jumlah_baris' => count($preview),
            ]);

            return $salaryImport;
        });
    }

    /**
     * Hapus seluruh data gaji pusat periode ini (salary_records, salary_components
     * ikut lewat cascade, salary_imports + rows) supaya bisa import ulang. Hanya
     * masuk akal selama periode DRAFT (data FINAL tidak boleh dihapus, §17).
     */
    public function clearPeriodData(SalaryPeriod $period, User $user): void
    {
        if ($period->status !== SalaryPeriod::STATUS_DRAFT) {
            throw new \RuntimeException('Data gaji hanya bisa dihapus selama periode berstatus DRAFT.');
        }

        $filesToDelete = $period->salaryImports()->pluck('path_file')->all();

        DB::transaction(function () use ($period) {
            $period->salaryRecords()->delete();
            $period->salaryImports()->delete();
        });

        foreach ($filesToDelete as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        AuditLogger::log('Hapus Data Import Gaji Pusat', "Menghapus seluruh data gaji pusat periode {$period->nama_periode} untuk import ulang.", [
            'salary_period_id' => $period->id,
        ]);
    }
}
