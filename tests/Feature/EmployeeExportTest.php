<?php

namespace Tests\Feature;

use App\Exports\EmployeesExport;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_operator_can_download_export(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create();

        $this->get(route('employees.export'))->assertOk();
    }

    public function test_verifikator_can_download_export(): void
    {
        // Verifikator cuma "lihat" Master Pegawai (bukan employees.manage),
        // tapi tetap boleh download krn datanya sama dgn yang sudah terlihat
        // di tabel — export digerbang employees.view, bukan .manage.
        $this->actingAsRole('verifikator');
        Employee::factory()->create();

        $this->get(route('employees.export'))->assertOk();
    }

    public function test_pegawai_cannot_download_export(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('employees.export'))->assertForbidden();
    }

    public function test_export_excludes_sensitive_financial_fields(): void
    {
        $headings = (new EmployeesExport([]))->headings();

        $this->assertNotContains('NPWP', $headings);
        $this->assertNotContains('No. Rekening', $headings);
        $this->assertNotContains('Nama Pemilik Rekening', $headings);
    }

    public function test_export_respects_unit_filter(): void
    {
        $unitA = Unit::factory()->create();
        $unitB = Unit::factory()->create();
        Employee::factory()->create(['unit_id' => $unitA->id, 'nama' => 'Pegawai Unit A']);
        Employee::factory()->create(['unit_id' => $unitB->id, 'nama' => 'Pegawai Unit B']);

        $rows = (new EmployeesExport(['unit' => (string) $unitA->id]))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('Pegawai Unit A', $rows->first()->nama);
    }

    public function test_export_respects_search_filter(): void
    {
        Employee::factory()->create(['nama' => 'Budi Santoso', 'nip' => '111111111111111111']);
        Employee::factory()->create(['nama' => 'Citra Dewi', 'nip' => '222222222222222222']);

        $rows = (new EmployeesExport(['search' => 'Budi']))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('Budi Santoso', $rows->first()->nama);
    }

    public function test_export_with_no_filters_returns_all_active_and_inactive(): void
    {
        Employee::factory()->create(['status_aktif' => true]);
        Employee::factory()->create(['status_aktif' => false]);

        $rows = (new EmployeesExport([]))->collection();

        $this->assertCount(2, $rows);
    }
}
