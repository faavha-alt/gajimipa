<?php

namespace Tests\Feature;

use App\Models\Employee;
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

class SalaryImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'kdsatker', 'kdanak', 'kdsubanak', 'bulan', 'tahun', 'nogaji', 'kdjns', 'nip', 'nmpeg',
        'kdduduk', 'kdgol', 'npwp', 'nmrek', 'nm_bank', 'rekening', 'kdbankspan', 'nmbankspan',
        'kdpos', 'kdnegara', 'kdkppn', 'tipesup', 'gjpokok', 'tjistri', 'tjanak', 'tjupns',
        'tjstruk', 'tjfungs', 'tjdaerah', 'tjpencil', 'tjlain', 'tjkompen', 'pembul', 'tjberas',
        'tjpph', 'potpfkbul', 'potpfk2', 'potpfk10', 'potpph', 'potswrum', 'potkelbtj', 'potlain',
        'pottabrum', 'bersih', 'sandi', 'kdkawin', 'kdjab', 'thngj', 'kdgapok', 'bpjs', 'bpjs2',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Import gaji pusat sengaja TIDAK menghapus file setelah sukses (§8
        // CLAUDE.md, jadi snapshot) — fake disk supaya test tidak menumpuk
        // file sungguhan permanen di storage server tiap suite dijalankan.
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

    /**
     * Baris "Suranto" dari docs/pemetaan-field-gaji.md §3 (terverifikasi):
     * Total Penghasilan Kotor 8.590.495 - Total Potongan Pusat 765.895 = Bersih 7.824.600.
     */
    private function suranto(string $nip, int $bulan, int $tahun): array
    {
        $fields = [
            'bulan' => $bulan, 'tahun' => $tahun, 'kdjns' => '1', 'nip' => $nip, 'nmpeg' => 'Prof. Suranto',
            'kdgol' => '45', 'gjpokok' => 6373200, 'tjistri' => 637320, 'tjanak' => 0, 'tjupns' => 0,
            'tjstruk' => 0, 'tjfungs' => 1350000, 'tjdaerah' => 0, 'tjpencil' => 0, 'tjlain' => 0,
            'tjkompen' => 0, 'pembul' => 81, 'tjberas' => 144840, 'tjpph' => 85054,
            'potpfkbul' => 0, 'potpfk2' => 0, 'potpfk10' => 560841, 'potpph' => 85054, 'potswrum' => 0,
            'potkelbtj' => 0, 'potlain' => 0, 'pottabrum' => 0, 'bpjs' => 120000, 'bpjs2' => 0,
            'bersih' => 7824600, 'kdkawin' => '1100', 'kdjab' => '06901', 'kdgapok' => '4E32',
        ];

        return array_map(fn ($h) => $fields[$h] ?? '', self::HEADERS);
    }

    private function makeExcelUpload(array $rows, string $filename = 'gaji_pusat.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_salary_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent($filename, file_get_contents($path));
    }

    public function test_verifikator_cannot_access_import_page(): void
    {
        $this->actingAsRole('verifikator');

        $this->get(route('salary-imports.create'))->assertForbidden();
    }

    public function test_operator_can_import_salary_data_end_to_end(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create(['nip' => '195708201985031004']);
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        $file = $this->makeExcelUpload([
            self::HEADERS,
            $this->suranto('195708201985031004', 8, 2026),
        ]);

        $component = Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->assertSet('step', 'upload')
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 'preview');

        $preview = $component->get('preview');
        $this->assertCount(1, $preview);
        $this->assertSame([], $preview[0]['errors']);
        $this->assertEquals(7824600, $preview[0]['bersih_hitung']);

        $component->call('confirmImport')->assertSet('step', 'done');

        $this->assertDatabaseHas('salary_records', [
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'bersih_pusat' => 7824600,
        ]);
        // Hanya komponen bernilai != 0 yang disimpan: gjpokok,tjistri,tjfungs,pembul,tjberas,tjpph (6) + potpfk10,potpph,bpjs (3).
        $this->assertDatabaseCount('salary_components', 9);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Import Gaji Pusat']);
        $this->assertSame('45', $employee->fresh()->golongan_saat_ini);
    }

    public function test_unknown_structure_is_rejected(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();

        $file = $this->makeExcelUpload([
            ['Kolom A', 'Kolom B'],
            ['x', 'y'],
        ]);

        Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 'upload')
            ->assertHasErrors('file');
    }

    public function test_nip_not_found_blocks_all_or_nothing(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        $file = $this->makeExcelUpload([
            self::HEADERS,
            $this->suranto('999999999999999999', 8, 2026),
        ]);

        $component = Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile');

        $preview = $component->get('preview');
        $this->assertNotEmpty($preview[0]['errors']);
        $this->assertStringContainsString('tidak ditemukan', $preview[0]['errors'][0]);

        $this->assertSame(0, \App\Models\SalaryRecord::count());
    }

    public function test_mismatched_bersih_is_flagged(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create(['nip' => '195708201985031004']);
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        $row = $this->suranto('195708201985031004', 8, 2026);
        $row[array_search('bersih', self::HEADERS)] = 1; // sengaja salah

        $file = $this->makeExcelUpload([self::HEADERS, $row]);

        $component = Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile');

        $errors = $component->get('preview')[0]['errors'];
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'Total tidak sesuai')));
    }

    public function test_period_month_mismatch_is_flagged(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create(['nip' => '195708201985031004']);
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        $file = $this->makeExcelUpload([
            self::HEADERS,
            $this->suranto('195708201985031004', 7, 2026), // Juli, bukan Agustus
        ]);

        $component = Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile');

        $errors = $component->get('preview')[0]['errors'];
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'Bulan/Tahun')));
    }

    public function test_cannot_import_into_non_draft_period(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->verifikasi()->create();

        Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->assertSet('step', 'select-period')
            ->assertHasErrors('periodId');
    }

    public function test_cannot_reimport_into_period_with_existing_data(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create(['nip' => '195708201985031004']);
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        \App\Models\SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        $this->assertTrue($period->salaryRecords()->exists());

        Volt::test('pages.salary-imports.create')
            ->set('periodId', (string) $period->id)
            ->assertSee('sudah punya data gaji')
            ->call('clearAndRestart');

        $this->assertSame(0, \App\Models\SalaryRecord::count());
    }
}
