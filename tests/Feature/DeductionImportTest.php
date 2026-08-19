<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
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

class DeductionImportTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeExcelUpload(array $rows, string $filename = 'potongan.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_deduction_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent($filename, file_get_contents($path));
    }

    private function employeeWithSalaryRecord(SalaryPeriod $period, string $npp): Employee
    {
        $employee = Employee::factory()->create(['kode_npp_fakultas' => $npp]);

        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        return $employee;
    }

    public function test_operator_can_import_deductions_end_to_end(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $employee = $this->employeeWithSalaryRecord($period, '001');
        $koperasi = DeductionType::factory()->create(['nama' => 'Koperasi UNS - Simpanan Wajib']);
        $kesejahteraan = DeductionType::factory()->create(['nama' => 'Iuran Kesejahteraan']);

        // Data nyata baris "Sutarno" dari docs/excel-potongan.md.
        $file = $this->makeExcelUpload([
            ['NPP', 'NAMA', 'S.Wajib', 'Kesejahteraan'],
            ['001', 'Prof. Drs. Sutarno, MSc, PhD', 85000, 9000],
        ]);

        $component = Volt::test('pages.deduction-records.import')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->assertSet('step', 'upload')
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 'mapping')
            ->set('mapping.2', 'type:'.$koperasi->id)
            ->set('mapping.3', 'type:'.$kesejahteraan->id)
            ->call('confirmMapping')
            ->assertSet('step', 'preview');

        $preview = $component->get('preview');
        $this->assertSame([], $preview[0]['errors']);
        $this->assertEquals(94000.0, $preview[0]['total']);

        $component->call('confirmImport')->assertSet('step', 'done');

        $this->assertDatabaseHas('deduction_records', ['deduction_type_id' => $koperasi->id, 'nominal' => 85000]);
        $this->assertDatabaseHas('deduction_records', ['deduction_type_id' => $kesejahteraan->id, 'nominal' => 9000]);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Import Potongan Fakultas']);
    }

    public function test_npp_not_found_blocks_all_or_nothing(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $type = DeductionType::factory()->create();

        $file = $this->makeExcelUpload([
            ['NPP', 'S.Wajib'],
            ['999', 85000],
        ]);

        $component = Volt::test('pages.deduction-records.import')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile')
            ->set('mapping.1', 'type:'.$type->id)
            ->call('confirmMapping');

        $preview = $component->get('preview');
        $this->assertNotEmpty($preview[0]['errors']);
        $this->assertStringContainsString('tidak ditemukan', $preview[0]['errors'][0]);
    }

    public function test_text_note_in_nominal_cell_is_treated_as_no_deduction_not_error(): void
    {
        // Kasus pegawai pensiun (docs/excel-potongan.md §7): sel nominal
        // berisi teks bebas, bukan error, cukup dianggap tidak ada potongan.
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $this->employeeWithSalaryRecord($period, '005');
        $type = DeductionType::factory()->create();

        $file = $this->makeExcelUpload([
            ['NPP', 'S.Wajib'],
            ['005', 'PENSIUN DI BULAN JUNI 2026'],
        ]);

        $component = Volt::test('pages.deduction-records.import')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile')
            ->set('mapping.1', 'type:'.$type->id)
            ->call('confirmMapping');

        $preview = $component->get('preview');
        $this->assertSame([], $preview[0]['errors']);
        $this->assertEquals(0, $preview[0]['total']);
    }

    public function test_reimport_replaces_import_sourced_but_keeps_manual_entries(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $employee = $this->employeeWithSalaryRecord($period, '001');
        $type = DeductionType::factory()->create();
        $salaryRecord = SalaryRecord::where('employee_id', $employee->id)->first();

        // Entri manual yang harus tetap ada setelah re-import.
        $manual = DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => 5000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => $operator->id,
        ]);

        $file = $this->makeExcelUpload([
            ['NPP', 'S.Wajib'],
            ['001', 85000],
        ]);

        Volt::test('pages.deduction-records.import')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->set('file', $file)
            ->call('uploadFile')
            ->set('mapping.1', 'type:'.$type->id)
            ->call('confirmMapping')
            ->call('confirmImport');

        $this->assertDatabaseHas('deduction_records', ['id' => $manual->id, 'nominal' => 5000, 'sumber' => 'MANUAL']);
        $this->assertDatabaseHas('deduction_records', ['deduction_type_id' => $type->id, 'nominal' => 85000, 'sumber' => 'IMPORT']);
        $this->assertSame(2, DeductionRecord::count());
    }

    public function test_cannot_import_deductions_without_salary_data(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create(); // tidak ada salary_records

        Volt::test('pages.deduction-records.import')
            ->set('periodId', (string) $period->id)
            ->call('selectPeriod')
            ->assertSet('step', 'select-period')
            ->assertHasErrors('periodId');
    }
}
