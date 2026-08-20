<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\JabatanFungsional;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SafeExcelReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Import massal Master Pegawai dari Excel (CLAUDE.md STEP 6 "Import jika
 * diperlukan"). Beda dengan Import Gaji Pusat (STEP 8) yang formatnya sudah
 * baku 50 kolom pemerintah — file Master Pegawai bisa berasal dari sumber
 * mana pun (SIMPEG, HRIS internal, dst.) makanya butuh mapping kolom manual
 * per upload, bukan mapping tetap.
 *
 * Alur konsisten dengan prinsip §12/§13: Upload → Mapping → Validasi →
 * Preview → Konfirmasi → Import, all-or-nothing (satu baris error menahan
 * seluruh import sampai diperbaiki).
 */
class EmployeeImportService
{
    public const TARGET_FIELDS = [
        'nip' => 'NIP (wajib)',
        'nama' => 'Nama (wajib)',
        'nik' => 'NIK',
        'unit' => 'Unit (kode/nama)',
        'status_pegawai' => 'Status Pegawai (kode/nama)',
        'golongan' => 'Golongan (kode/nama)',
        'jabatan_fungsional' => 'Jab. Fungsional (kode/nama)',
        'email' => 'Email',
        'no_hp' => 'Nomor HP',
        'kode_npp_fakultas' => 'Kode NPP Fakultas',
        'id_simpeg' => 'ID SIMPEG',
        'npwp' => 'NPWP',
        'no_rekening' => 'No. Rekening',
        'status_aktif' => 'Status Aktif',
    ];

    public const MAX_ROWS = 1000;

    /**
     * Membaca seluruh baris sheet pertama sebagai array of array (baris 1 =
     * header). Baris yang seluruh selnya kosong diabaikan.
     */
    public function readSheet(string $absolutePath): array
    {
        return SafeExcelReader::readFirstSheet($absolutePath);
    }

    /**
     * @param  array<int,array<int,mixed>>  $rows  Termasuk baris header di index 0.
     * @param  array<int,string>  $mapping  Index kolom (0-based) => target field (lihat TARGET_FIELDS) atau 'ignore'.
     * @return array<int,array{row_number:int,data:array,errors:array,action:string}>
     */
    public function buildPreview(array $rows, array $mapping): array
    {
        $dataRows = array_slice($rows, 1);
        $preview = [];

        $extracted = [];
        foreach ($dataRows as $i => $row) {
            $fields = [];
            foreach ($mapping as $colIndex => $target) {
                if ($target === 'ignore' || $target === '') {
                    continue;
                }
                $value = $row[$colIndex] ?? null;
                $fields[$target] = is_string($value) ? trim($value) : $value;
            }
            $extracted[$i] = $fields;
        }

        // Deteksi duplikat dalam file itu sendiri (NIP/NIK/email/kode_npp_fakultas/npwp).
        $duplicateFields = ['nip', 'nik', 'email', 'kode_npp_fakultas', 'npwp'];
        $valueCounts = [];
        foreach ($duplicateFields as $field) {
            $valueCounts[$field] = collect($extracted)
                ->pluck($field)
                ->filter(fn ($v) => filled($v))
                ->countBy(fn ($v) => Str::lower((string) $v));
        }

        foreach ($extracted as $i => $fields) {
            $rowNumber = $i + 2; // +1 untuk index->1-based, +1 lagi karena baris 1 = header
            $errors = [];

            foreach ($duplicateFields as $field) {
                $value = $fields[$field] ?? null;
                if (filled($value) && ($valueCounts[$field][Str::lower((string) $value)] ?? 0) > 1) {
                    $errors[] = self::TARGET_FIELDS[$field]." '{$value}' duplikat di dalam file ini.";
                }
            }

            $existing = filled($fields['nip'] ?? null) ? Employee::where('nip', $fields['nip'])->first() : null;

            $resolvedUnitId = null;
            if (filled($fields['unit'] ?? null)) {
                $unit = Unit::whereRaw('LOWER(kode_unit) = ?', [Str::lower($fields['unit'])])
                    ->orWhereRaw('LOWER(nama_unit) = ?', [Str::lower($fields['unit'])])
                    ->first();
                $resolvedUnitId = $unit?->id;
                if (! $unit) {
                    $errors[] = "Unit '{$fields['unit']}' tidak ditemukan di Master Unit.";
                }
            }

            $resolvedStatusId = null;
            if (filled($fields['status_pegawai'] ?? null)) {
                $status = EmployeeStatus::whereRaw('LOWER(kode) = ?', [Str::lower($fields['status_pegawai'])])
                    ->orWhereRaw('LOWER(nama) = ?', [Str::lower($fields['status_pegawai'])])
                    ->first();
                $resolvedStatusId = $status?->id;
                if (! $status) {
                    $errors[] = "Status Pegawai '{$fields['status_pegawai']}' tidak ditemukan di Master Status Pegawai.";
                }
            }

            $resolvedGolonganId = null;
            if (filled($fields['golongan'] ?? null)) {
                $golongan = Golongan::whereRaw('LOWER(kode) = ?', [Str::lower($fields['golongan'])])
                    ->orWhereRaw('LOWER(nama) = ?', [Str::lower($fields['golongan'])])
                    ->first();
                $resolvedGolonganId = $golongan?->id;
                if (! $golongan) {
                    $errors[] = "Golongan '{$fields['golongan']}' tidak ditemukan di Master Golongan.";
                }
            }

            $resolvedJabatanFungsionalId = null;
            if (filled($fields['jabatan_fungsional'] ?? null)) {
                $jabatan = JabatanFungsional::whereRaw('LOWER(kode) = ?', [Str::lower($fields['jabatan_fungsional'])])
                    ->orWhereRaw('LOWER(nama) = ?', [Str::lower($fields['jabatan_fungsional'])])
                    ->first();
                $resolvedJabatanFungsionalId = $jabatan?->id;
                if (! $jabatan) {
                    $errors[] = "Jab. Fungsional '{$fields['jabatan_fungsional']}' tidak ditemukan di Master Jabatan Fungsional.";
                }
            }

            $payload = [
                'nip' => $fields['nip'] ?? null,
                'nik' => $fields['nik'] ?? null,
                'nama' => $fields['nama'] ?? null,
                'unit_id' => $resolvedUnitId,
                'employee_status_id' => $resolvedStatusId,
                'golongan_id' => $resolvedGolonganId,
                'jabatan_fungsional_id' => $resolvedJabatanFungsionalId,
                'email' => $fields['email'] ?? null,
                'no_hp' => $fields['no_hp'] ?? null,
                'kode_npp_fakultas' => $fields['kode_npp_fakultas'] ?? null,
                'id_simpeg' => $fields['id_simpeg'] ?? null,
                'npwp' => $fields['npwp'] ?? null,
                'no_rekening' => $fields['no_rekening'] ?? null,
                'status_aktif' => $this->parseBoolean($fields['status_aktif'] ?? null),
            ];

            $validator = Validator::make($payload, [
                'nip' => ['required', 'digits_between:8,20', \Illuminate\Validation\Rule::unique('employees', 'nip')->ignore($existing?->id)],
                'nik' => ['nullable', 'digits:16', \Illuminate\Validation\Rule::unique('employees', 'nik')->ignore($existing?->id)],
                'nama' => ['required', 'string', 'max:150'],
                'email' => ['nullable', 'email', 'max:150', \Illuminate\Validation\Rule::unique('employees', 'email')->ignore($existing?->id)],
                'no_hp' => ['nullable', 'string', 'max:20'],
                'kode_npp_fakultas' => ['nullable', 'string', 'max:20', \Illuminate\Validation\Rule::unique('employees', 'kode_npp_fakultas')->ignore($existing?->id)],
                'id_simpeg' => ['nullable', 'string', 'max:30', \Illuminate\Validation\Rule::unique('employees', 'id_simpeg')->ignore($existing?->id)],
                'npwp' => ['nullable', 'string', 'max:25', \Illuminate\Validation\Rule::unique('employees', 'npwp')->ignore($existing?->id)],
                'no_rekening' => ['nullable', 'string', 'max:30'],
            ]);

            if ($validator->fails()) {
                $errors = array_merge($errors, $validator->errors()->all());
            }

            $preview[] = [
                'row_number' => $rowNumber,
                'data' => $payload,
                'errors' => $errors,
                'action' => $existing ? 'update' : 'create',
            ];
        }

        return $preview;
    }

    /**
     * Menjalankan import all-or-nothing: kalau ada satu saja baris berisi
     * error, tidak ada yang diimport (§12/§13).
     *
     * @param  array<int,array{data:array,errors:array,action:string}>  $preview
     * @return array{created:int,updated:int}
     */
    public function import(array $preview, User $user): array
    {
        if (collect($preview)->contains(fn ($row) => ! empty($row['errors']))) {
            throw new \RuntimeException('Masih ada baris berisi error — perbaiki dulu sebelum import bisa dikonfirmasi.');
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($preview, &$created, &$updated) {
            foreach ($preview as $row) {
                $data = array_filter($row['data'], fn ($v) => $v !== null);
                // status_aktif tetap harus tersimpan meski false (bukan "kosong").
                $data['status_aktif'] = $row['data']['status_aktif'] ?? true;

                Employee::updateOrCreate(['nip' => $row['data']['nip']], $data);

                $row['action'] === 'update' ? $updated++ : $created++;
            }
        });

        AuditLogger::log('Import Master Pegawai', "Import selesai: {$created} pegawai baru, {$updated} pegawai diperbarui.", [
            'created' => $created,
            'updated' => $updated,
        ]);

        return ['created' => $created, 'updated' => $updated];
    }

    private function parseBoolean(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array(Str::lower((string) $value), ['1', 'ya', 'y', 'aktif', 'true', 'yes'], true);
    }
}
