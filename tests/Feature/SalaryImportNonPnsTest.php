<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryImport;
use App\Models\SalaryPeriod;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Format Non-PNS (sistem penggajian Non-PNS universitas) — lih.
 * docs/excel-gaji-nonpns.md. Fixture diambil dari baris nyata contoh file
 * DasaracuanNonPNS.xls (pegawai "Silvina Rosita Yulianti"), diverifikasi:
 * GAJI_KOTOR 2.801.920 = GAJI_POKOK 2.729.500 + TUNJ_BERAS 72.420.
 */
class SalaryImportNonPnsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'NO', 'TAHUNBULAN', 'IDPEGAWAI', 'NAMA', 'NIP', 'UNIT', 'STATUS', 'JENIS', 'FUNGSIONAL',
        'ISTRI/SUAMI_TERTANGGUNG', 'JUMLAH_ANAK_TERTANGGUNG', 'TOTAL_KELUARGA', 'GAJI_POKOK',
        'TUNJ_ISTRI', 'TUNJ_FUNGSIONAL', 'TUNJ_ANAK', 'TUNJ_BERAS', 'GAJI_KOTOR', 'BANK',
        'NO_REKENING', 'NPWP', 'CATATAN', 'POT_PPH21', 'POT_IWP', 'POT_BPJS',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    private function silvina(string $nip, int $tahunBulan): array
    {
        $fields = [
            'NO' => 1, 'TAHUNBULAN' => $tahunBulan, 'IDPEGAWAI' => 7863, 'NAMA' => 'Silvina Rosita Yulianti',
            'NIP' => $nip, 'UNIT' => 'FMIPA', 'STATUS' => 'AK', 'JENIS' => 'Tenaga Pendidik',
            'FUNGSIONAL' => 'Tenaga Pengajar', 'ISTRI/SUAMI_TERTANGGUNG' => 'TIDAK',
            'JUMLAH_ANAK_TERTANGGUNG' => 0, 'TOTAL_KELUARGA' => 1, 'GAJI_POKOK' => 2729500,
            'TUNJ_ISTRI' => 0, 'TUNJ_FUNGSIONAL' => 0, 'TUNJ_ANAK' => 0, 'TUNJ_BERAS' => 72420,
            'GAJI_KOTOR' => 2801920, 'BANK' => 'BTN', 'NO_REKENING' => '1234567890', 'NPWP' => '000000000000000',
            'CATATAN' => '', 'POT_PPH21' => 0, 'POT_IWP' => 0, 'POT_BPJS' => 0,
        ];

        return array_map(fn ($h) => $fields[$h] ?? '', self::HEADERS);
    }

    private function makeExcelUpload(array $rows, string $filename = 'gaji_nonpns.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_salary_import_nonpns_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent($filename, file_get_contents($path));
    }

    public function test_operator_can_import_nonpns_format_end_to_end(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create(['nip' => '2000071720250701']);
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        $file = $this->makeExcelUpload([
            self::HEADERS,
            $this->silvina('2000071720250701', 202608),
        ]);

        $component = Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->assertSet('step', 'upload')
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 'preview')
            ->assertSet('templateCode', 'NON_PNS');

        $preview = $component->get('preview');
        $this->assertCount(1, $preview);
        $this->assertSame([], $preview[0]['errors']);
        $this->assertEquals(2801920, $preview[0]['bersih_hitung']);

        $component->call('confirmImport')->assertSet('step', 'done');

        $this->assertDatabaseHas('salary_records', [
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'bersih_pusat' => 2801920,
            'jabatan_snapshot' => 'Tenaga Pengajar',
        ]);
        // Komponen bernilai != 0: gaji_pokok, tunj_beras (2). Tidak ada potongan (semua 0).
        $this->assertDatabaseCount('salary_components', 2);
        $this->assertSame(SalaryImport::FORMAT_NON_PNS, SalaryImport::first()->format);
        $this->assertSame('Tenaga Pengajar', $employee->fresh()->jabatanFungsional?->kode);
    }

    public function test_mismatched_gaji_kotor_is_flagged(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create(['nip' => '2000071720250701']);
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        $row = $this->silvina('2000071720250701', 202608);
        $row[array_search('GAJI_KOTOR', self::HEADERS)] = 1; // sengaja salah

        $file = $this->makeExcelUpload([self::HEADERS, $row]);

        $component = Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile');

        $errors = $component->get('preview')[0]['errors'];
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'Total tidak sesuai')));
    }

    public function test_tahunbulan_mismatch_is_flagged(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create(['nip' => '2000071720250701']);
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        $file = $this->makeExcelUpload([
            self::HEADERS,
            $this->silvina('2000071720250701', 202607), // Juli, bukan Agustus
        ]);

        $component = Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile');

        $errors = $component->get('preview')[0]['errors'];
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'Bulan/Tahun')));
    }
}
