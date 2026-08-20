<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\JabatanFungsional;
use App\Models\Unit;
use App\Models\User;
use App\Services\Import\EmployeeImportService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Import menulis file ke disk 'local' — fake supaya test tidak
        // menumpuk file sungguhan di storage server tiap kali suite dijalankan.
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

    private function makeExcelUpload(array $rows, string $filename = 'employees.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent($filename, file_get_contents($path));
    }

    public function test_verifikator_cannot_access_import_page(): void
    {
        $this->actingAsRole('verifikator');

        $this->get(route('employees.import'))->assertForbidden();
    }

    public function test_operator_can_import_new_employees_end_to_end(): void
    {
        $this->actingAsRole('operator_gaji');
        Unit::factory()->create(['kode_unit' => 'PRODI-MAT', 'nama_unit' => 'Prodi Matematika']);
        EmployeeStatus::factory()->create(['kode' => 'PNS', 'nama' => 'Pegawai Negeri Sipil']);

        $file = $this->makeExcelUpload([
            ['NIP', 'Nama', 'Unit', 'Status Pegawai', 'Email'],
            ['195708201985031004', 'Prof. Suranto', 'PRODI-MAT', 'PNS', 'suranto@staff.uns.ac.id'],
            ['196101121988112001', 'Dra. Diari Indriati', 'Prodi Matematika', 'Pegawai Negeri Sipil', 'diari@staff.uns.ac.id'],
        ]);

        $component = Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 'mapping');

        $this->assertSame('nip', $component->get('mapping')[0]);
        $this->assertSame('nama', $component->get('mapping')[1]);
        $this->assertSame('unit', $component->get('mapping')[2]);
        $this->assertSame('status_pegawai', $component->get('mapping')[3]);
        $this->assertSame('email', $component->get('mapping')[4]);

        $component->call('confirmMapping')->assertSet('step', 'preview');

        $preview = $component->get('preview');
        $this->assertCount(2, $preview);
        $this->assertSame([], $preview[0]['errors']);
        $this->assertSame('create', $preview[0]['action']);

        $component->call('confirmImport')->assertSet('step', 'done');

        $this->assertDatabaseHas('employees', ['nip' => '195708201985031004', 'nama' => 'Prof. Suranto']);
        $this->assertDatabaseHas('employees', ['nip' => '196101121988112001', 'nama' => 'Dra. Diari Indriati']);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Import Master Pegawai']);
    }

    public function test_reimporting_existing_nip_updates_instead_of_duplicating(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create(['nip' => '195708201985031004', 'nama' => 'Nama Lama']);

        $file = $this->makeExcelUpload([
            ['NIP', 'Nama'],
            ['195708201985031004', 'Nama Baru Setelah Update'],
        ]);

        Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->call('confirmMapping')
            ->call('confirmImport');

        $this->assertSame(1, Employee::count());
        $this->assertDatabaseHas('employees', ['nip' => '195708201985031004', 'nama' => 'Nama Baru Setelah Update']);
    }

    public function test_unknown_unit_blocks_import_all_or_nothing(): void
    {
        $this->actingAsRole('operator_gaji');

        $file = $this->makeExcelUpload([
            ['NIP', 'Nama', 'Unit'],
            ['111111111111111111', 'Pegawai Satu', 'Prodi Tidak Ada'],
            ['222222222222222222', 'Pegawai Dua', ''],
        ]);

        $component = Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->call('confirmMapping');

        $preview = $component->get('preview');
        $this->assertNotEmpty($preview[0]['errors']);
        $this->assertEmpty($preview[1]['errors']);

        // UI mencegah klik saat ada error, tapi service-nya sendiri juga wajib menolak all-or-nothing.
        $this->expectException(\RuntimeException::class);
        app(EmployeeImportService::class)->import($preview, auth()->user());

        $this->assertSame(0, Employee::count());
    }

    public function test_duplicate_nip_within_file_is_flagged(): void
    {
        $this->actingAsRole('operator_gaji');

        $file = $this->makeExcelUpload([
            ['NIP', 'Nama'],
            ['111111111111111111', 'Pegawai A'],
            ['111111111111111111', 'Pegawai B (NIP sama)'],
        ]);

        $component = Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->call('confirmMapping');

        $preview = $component->get('preview');
        $this->assertNotEmpty($preview[0]['errors']);
        $this->assertNotEmpty($preview[1]['errors']);
    }

    public function test_golongan_column_is_resolved_by_kode_or_nama(): void
    {
        $this->actingAsRole('operator_gaji');
        $golongan = Golongan::factory()->create(['kode' => '45', 'nama' => 'III/a']);

        $file = $this->makeExcelUpload([
            ['NIP', 'Nama', 'Golongan'],
            ['111111111111111111', 'Pegawai Satu', 'III/a'],
        ]);

        $component = Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 'mapping');

        $this->assertSame('golongan', $component->get('mapping')[2]);

        $component->call('confirmMapping');

        $preview = $component->get('preview');
        $this->assertSame([], $preview[0]['errors']);

        $component->call('confirmImport')->assertSet('step', 'done');

        $this->assertDatabaseHas('employees', ['nip' => '111111111111111111', 'golongan_id' => $golongan->id]);
    }

    public function test_unknown_golongan_blocks_import_all_or_nothing(): void
    {
        $this->actingAsRole('operator_gaji');

        $file = $this->makeExcelUpload([
            ['NIP', 'Nama', 'Golongan'],
            ['222222222222222222', 'Pegawai Dua', 'Golongan Tidak Ada'],
        ]);

        $component = Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->call('confirmMapping');

        $preview = $component->get('preview');
        $this->assertNotEmpty($preview[0]['errors']);

        $this->expectException(\RuntimeException::class);
        app(EmployeeImportService::class)->import($preview, auth()->user());
    }

    public function test_jabatan_fungsional_column_is_resolved_by_kode_or_nama(): void
    {
        $this->actingAsRole('operator_gaji');
        $jabatan = JabatanFungsional::factory()->create(['kode' => '06901', 'nama' => 'Lektor Kepala']);

        $file = $this->makeExcelUpload([
            ['NIP', 'Nama', 'Jab. Fungsional'],
            ['111111111111111111', 'Pegawai Satu', 'Lektor Kepala'],
        ]);

        $component = Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 'mapping');

        $this->assertSame('jabatan_fungsional', $component->get('mapping')[2]);

        $component->call('confirmMapping');

        $preview = $component->get('preview');
        $this->assertSame([], $preview[0]['errors']);

        $component->call('confirmImport')->assertSet('step', 'done');

        $this->assertDatabaseHas('employees', ['nip' => '111111111111111111', 'jabatan_fungsional_id' => $jabatan->id]);
    }

    public function test_unknown_jabatan_fungsional_blocks_import_all_or_nothing(): void
    {
        $this->actingAsRole('operator_gaji');

        $file = $this->makeExcelUpload([
            ['NIP', 'Nama', 'Jab. Fungsional'],
            ['222222222222222222', 'Pegawai Dua', 'Jabatan Tidak Ada'],
        ]);

        $component = Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->call('confirmMapping');

        $preview = $component->get('preview');
        $this->assertNotEmpty($preview[0]['errors']);

        $this->expectException(\RuntimeException::class);
        app(EmployeeImportService::class)->import($preview, auth()->user());
    }

    public function test_mapping_requires_nip_and_nama(): void
    {
        $this->actingAsRole('operator_gaji');

        $file = $this->makeExcelUpload([
            ['Kolom A', 'Kolom B'],
            ['x', 'y'],
        ]);

        Volt::test('pages.employees.import')
            ->set('file', $file)
            ->call('uploadFile')
            ->set('mapping.0', 'ignore')
            ->set('mapping.1', 'ignore')
            ->call('confirmMapping')
            ->assertSet('step', 'mapping');
    }
}
