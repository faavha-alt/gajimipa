<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\Golongan;
use App\Models\IncomeType;
use App\Models\JabatanFungsional;
use App\Models\SalaryComponent;
use App\Models\SalaryImport;
use App\Models\SalaryImportRow;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\Import\SalaryImportTemplates\NonPnsSalaryImportTemplate;
use App\Services\Import\SalaryImportTemplates\PnsSalaryImportTemplate;
use App\Services\Import\SalaryImportTemplates\SalaryImportTemplate;
use App\Support\AuditLogger;
use App\Support\SafeExcelReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Import Gaji Pusat (CLAUDE.md STEP 8, §12). Sistem sudah terbukti punya
 * lebih dari satu format sumber data pusat yang sah (PNS via GPP pemerintah,
 * Non-PNS via sistem penggajian Non-PNS universitas — docs/excel-gaji-
 * nonpns.md §5) — jadi format tidak hard-code satu bentuk kolom, melainkan
 * lewat SalaryImportTemplate yang dipilih otomatis berdasarkan header file
 * (lih. detectStructure()). Keduanya tetap "Data Pusat" (§3.1): read-only /
 * snapshot, disimpan apa adanya ke salary_records + salary_components, tidak
 * pernah diedit manual. Import bersifat all-or-nothing per periode (§12).
 */
class SalaryImportService
{
    public const MAX_ROWS = 1000;

    /**
     * @return array<int,SalaryImportTemplate>
     */
    public function templates(): array
    {
        return [
            new PnsSalaryImportTemplate,
            new NonPnsSalaryImportTemplate,
        ];
    }

    public function templateByCode(string $code): SalaryImportTemplate
    {
        foreach ($this->templates() as $template) {
            if ($template->code() === $code) {
                return $template;
            }
        }

        throw new \InvalidArgumentException("Template import tidak dikenali: {$code}");
    }

    public function readSheet(string $absolutePath): array
    {
        return SafeExcelReader::readFirstSheet($absolutePath);
    }

    /**
     * Cocokkan header file terhadap template yang dikenal sistem. Template
     * pertama yang seluruh header wajibnya ada dianggap cocok.
     *
     * @return array{ok:bool,template:?SalaryImportTemplate,missing:array<int,string>,columnMap:array<string,int>}
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

        $closestMissing = [];
        foreach ($this->templates() as $template) {
            $missing = array_values(array_filter(
                $template->requiredHeaders(),
                fn ($field) => ! array_key_exists($field, $columnMap)
            ));

            if (empty($missing)) {
                return ['ok' => true, 'template' => $template, 'missing' => [], 'columnMap' => $columnMap];
            }

            if (empty($closestMissing) || count($missing) < count($closestMissing)) {
                $closestMissing = $missing;
            }
        }

        return ['ok' => false, 'template' => null, 'missing' => $closestMissing, 'columnMap' => $columnMap];
    }

    /**
     * @return array<int,array{row_number:int,nip:mixed,nama:mixed,employee_id:?int,pegawai_baru:bool,errors:array,penghasilan:array,potongan:array,total_penghasilan:float,total_potongan:float,bersih_hitung:float,bersih_file:mixed,snapshot:array}>
     */
    public function buildPreview(array $rows, array $columnMap, SalaryPeriod $period, SalaryImportTemplate $template): array
    {
        $dataRows = array_slice($rows, 1);
        $preview = [];

        $get0 = fn ($row, string $field) => array_key_exists($field, $columnMap) ? ($row[$columnMap[$field]] ?? null) : null;

        $nipCounts = collect($dataRows)
            ->map(fn ($row) => $template->extractNip(fn ($f) => $get0($row, $f)))
            ->filter(fn ($v) => $v !== '')
            ->countBy();

        foreach ($dataRows as $i => $row) {
            $rowNumber = $i + 2;
            $errors = [];

            $get = fn (string $field) => $get0($row, $field);

            $nip = $template->extractNip($get);
            $nama = $template->extractNama($get);

            if ($nip === '') {
                $errors[] = 'NIP kosong.';
            } elseif (($nipCounts[$nip] ?? 0) > 1) {
                $errors[] = "NIP '{$nip}' duplikat di dalam file ini.";
            }

            // NIP tidak ditemukan TIDAK lagi memblokir batch (keputusan produk,
            // 2026-08-20): pegawai baru dibuat otomatis saat konfirmasi import
            // (NIP+nama saja, field lain dilengkapi manual belakangan) — baris
            // ini tetap ditandai jelas di layar Preview (bukan diam-diam),
            // supaya typo NIP masih kelihatan sebelum operator konfirmasi.
            $employee = $nip !== '' ? Employee::where('nip', $nip)->first() : null;
            $pegawaiBaru = $nip !== '' && ! $employee;

            if ($employee && ! $employee->status_aktif) {
                $errors[] = "Pegawai '{$employee->nama}' berstatus tidak aktif.";
            } elseif ($employee && $period->salaryRecords()->where('employee_id', $employee->id)->exists()) {
                $errors[] = "Pegawai '{$employee->nama}' sudah punya data gaji untuk periode ini (dari import sebelumnya). Hapus data lama dulu kalau mau menimpa.";
            }

            [$periodeCocok, $periodeLabel] = $template->periodeCocok($get, $period);
            if (! $periodeCocok) {
                $errors[] = "Bulan/Tahun file ({$periodeLabel}) tidak sesuai periode yang dipilih ({$period->bulan}/{$period->tahun}).";
            }

            $penghasilan = [];
            foreach ($template->penghasilanFields() as $field => $label) {
                [$value, $error] = $this->parseNominal($get($field), $label);
                $penghasilan[$field] = $value;
                if ($error) {
                    $errors[] = $error;
                }
            }

            $potongan = [];
            foreach ($template->potonganPusatFields() as $field => $label) {
                [$value, $error] = $this->parseNominal($get($field), $label);
                $potongan[$field] = $value;
                if ($error) {
                    $errors[] = $error;
                }
            }

            $totalResmi = $template->totalResmi();
            [$totalFileValue, $totalError] = $this->parseNominal($get($totalResmi['column']), $totalResmi['label']);
            if ($totalError) {
                $errors[] = $totalError;
            }

            $totalPenghasilan = array_sum($penghasilan);
            $totalPotongan = array_sum($potongan);
            $bersihHitung = $totalPenghasilan - $totalPotongan;
            $expected = $totalResmi['basis'] === 'kotor' ? $totalPenghasilan : $bersihHitung;

            if (! $totalError && round($expected, 2) !== round((float) $totalFileValue, 2)) {
                $errors[] = "Total tidak sesuai — hitung ulang Rp".number_format($expected, 0, ',', '.')." tapi file bilang Rp".number_format((float) $totalFileValue, 0, ',', '.')." (kolom {$totalResmi['label']}).";
            }

            $bersihFile = $totalResmi['basis'] === 'bersih' ? $totalFileValue : $bersihHitung;

            $preview[] = [
                'row_number' => $rowNumber,
                'nip' => $nip,
                'nama' => $nama,
                'employee_id' => $employee?->id,
                'pegawai_baru' => $pegawaiBaru,
                'errors' => $errors,
                'penghasilan' => $penghasilan,
                'potongan' => $potongan,
                'total_penghasilan' => $totalPenghasilan,
                'total_potongan' => $totalPotongan,
                'bersih_hitung' => $bersihHitung,
                'bersih_file' => $bersihFile,
                'income_type_code' => $template->extractIncomeTypeCode($get),
                'snapshot' => $template->extractSnapshot($get),
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
    public function import(SalaryPeriod $period, array $preview, string $namaFile, string $pathFile, User $user, SalaryImportTemplate $template): SalaryImport
    {
        if (collect($preview)->contains(fn ($row) => ! empty($row['errors']))) {
            throw new \RuntimeException('Masih ada baris berisi error — perbaiki dulu sebelum import bisa dikonfirmasi.');
        }

        // Import bersifat kumulatif per periode — 1 periode wajar berisi lebih dari
        // 1 batch (mis. PNS diimpor duluan, Non-PNS menyusul, format berbeda tapi
        // ke periode yang sama). Yang tidak boleh adalah pegawai yang SAMA muncul
        // di lebih dari 1 batch (sudah dicegah di buildPreview() per baris) — cek
        // ulang di sini sebagai lapis pertahanan terakhir sebelum commit, jaga-jaga
        // ada import lain yang masuk di antara preview dibuat & dikonfirmasi.
        $employeeIds = collect($preview)->pluck('employee_id')->filter()->all();
        $sudahAda = $period->salaryRecords()->whereIn('employee_id', $employeeIds)->pluck('employee_id');
        if ($sudahAda->isNotEmpty()) {
            throw new \RuntimeException('Sebagian pegawai di file ini sudah punya data gaji untuk periode ini (mungkin baru saja diimpor lewat proses lain). Upload ulang file untuk melihat baris mana yang bentrok.');
        }

        return DB::transaction(function () use ($period, $preview, $namaFile, $pathFile, $user, $template) {
            $salaryImport = SalaryImport::create([
                'salary_period_id' => $period->id,
                'nama_file' => $namaFile,
                'path_file' => $pathFile,
                'format' => $template->code(),
                'diupload_oleh' => $user->id,
                'status' => 'SELESAI',
                'jumlah_baris' => count($preview),
                'jumlah_error' => 0,
            ]);

            $pegawaiBaruDibuat = [];

            foreach ($preview as $row) {
                if ($row['pegawai_baru'] ?? false) {
                    $employee = Employee::create([
                        'nip' => $row['nip'],
                        'nama' => $row['nama'],
                        'status_aktif' => true,
                    ]);
                    $pegawaiBaruDibuat[] = "{$row['nip']} ({$row['nama']})";
                } else {
                    $employee = Employee::find($row['employee_id']);
                }

                $incomeType = null;
                if (filled($row['income_type_code'])) {
                    $incomeType = IncomeType::firstOrCreate(
                        ['kode' => (string) $row['income_type_code']],
                        ['nama' => 'Jenis Gaji '.$row['income_type_code'], 'status_aktif' => true]
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
                    'golongan_snapshot' => $row['snapshot']['golongan'] ?? null,
                    'jabatan_snapshot' => $row['snapshot']['jabatan'] ?? null,
                    'kode_gaji_pokok_snapshot' => $row['snapshot']['kode_gaji_pokok'] ?? null,
                    'status_kawin_snapshot' => $row['snapshot']['status_kawin'] ?? null,
                    'total_penghasilan_kotor' => $row['total_penghasilan'],
                    'total_potongan_pusat' => $row['total_potongan'],
                    'bersih_pusat' => $row['bersih_file'],
                    'total_potongan_fakultas' => 0,
                    'gaji_bersih_final' => $row['bersih_file'],
                ]);

                foreach ($template->penghasilanFields() as $field => $label) {
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

                foreach ($template->potonganPusatFields() as $field => $label) {
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

                $golongan = null;
                if (filled($row['snapshot']['golongan'] ?? null)) {
                    $golongan = Golongan::firstOrCreate(
                        ['kode' => (string) $row['snapshot']['golongan']],
                        ['nama' => (string) $row['snapshot']['golongan'], 'status_aktif' => true]
                    );
                }

                $jabatanFungsional = null;
                if (filled($row['snapshot']['jabatan'] ?? null)) {
                    $jabatanFungsional = JabatanFungsional::firstOrCreate(
                        ['kode' => (string) $row['snapshot']['jabatan']],
                        ['nama' => (string) $row['snapshot']['jabatan'], 'status_aktif' => true]
                    );
                }

                $employee->update(array_filter([
                    'golongan_id' => $golongan?->id,
                    'jabatan_fungsional_id' => $jabatanFungsional?->id,
                    'kode_gaji_pokok_saat_ini' => $row['snapshot']['kode_gaji_pokok'] ?? null,
                    'status_kawin_saat_ini' => $row['snapshot']['status_kawin'] ?? null,
                ], fn ($v) => filled($v)));
            }

            AuditLogger::log('Import Gaji Pusat', "Import gaji pusat ({$template->label()}) untuk periode {$period->nama_periode}: ".count($preview)." baris berhasil.", [
                'salary_period_id' => $period->id,
                'salary_import_id' => $salaryImport->id,
                'format' => $template->code(),
                'jumlah_baris' => count($preview),
            ]);

            if (! empty($pegawaiBaruDibuat)) {
                AuditLogger::log('Buat Pegawai Otomatis (Import Gaji)', count($pegawaiBaruDibuat).' pegawai baru dibuat otomatis karena NIP belum ada di Master Pegawai saat Import Gaji Pusat periode '.$period->nama_periode.'. NIP+nama diambil dari file, field lain masih kosong dan perlu dilengkapi manual.', [
                    'salary_period_id' => $period->id,
                    'salary_import_id' => $salaryImport->id,
                    'pegawai' => $pegawaiBaruDibuat,
                ]);
            }

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
