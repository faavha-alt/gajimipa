<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\JabatanFungsional;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
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

    public function test_operator_can_view_employees_page(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('employees.index'))
            ->assertOk()
            ->assertSeeVolt('pages.employees.index');
    }

    public function test_pegawai_cannot_access_employees_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('employees.index'))->assertForbidden();
    }

    public function test_operator_can_create_employee(): void
    {
        $this->actingAsRole('operator_gaji');
        $unit = Unit::factory()->create();
        $status = EmployeeStatus::factory()->create();
        $golongan = Golongan::factory()->create();
        $jabatan = JabatanFungsional::factory()->create();

        Volt::test('pages.employees.index')
            ->call('openCreate')
            ->set('nip', '195708201985031004')
            ->set('nama', 'Prof. Drs. Suranto, M.Sc., Ph.D.')
            ->set('unit_id', (string) $unit->id)
            ->set('employee_status_id', (string) $status->id)
            ->set('golongan_id', (string) $golongan->id)
            ->set('jabatan_fungsional_id', (string) $jabatan->id)
            ->set('email', 'suranto@staff.uns.ac.id')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'nip' => '195708201985031004',
            'unit_id' => $unit->id,
            'employee_status_id' => $status->id,
            'golongan_id' => $golongan->id,
            'jabatan_fungsional_id' => $jabatan->id,
        ]);
    }

    public function test_nip_must_be_numeric_and_unique(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create(['nip' => '195708201985031004']);

        Volt::test('pages.employees.index')
            ->call('openCreate')
            ->set('nip', '195708201985031004')
            ->set('nama', 'Pegawai Lain')
            ->call('save')
            ->assertHasErrors('nip');

        Volt::test('pages.employees.index')
            ->call('openCreate')
            ->set('nip', 'BUKAN-ANGKA')
            ->set('nama', 'Pegawai Lain')
            ->call('save')
            ->assertHasErrors('nip');
    }

    public function test_verifikator_can_view_but_not_manage_employees(): void
    {
        $this->actingAsRole('verifikator');

        $this->get(route('employees.index'))->assertOk();

        Volt::test('pages.employees.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_search_filters_by_nip_or_name(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create(['nip' => '111111111111111111', 'nama' => 'Budi Santoso']);
        Employee::factory()->create(['nip' => '222222222222222222', 'nama' => 'Siti Aminah']);

        Volt::test('pages.employees.index')
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');
    }

    public function test_operator_cannot_delete_employee_with_salary_history(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create();

        $period = SalaryPeriod::create([
            'nama_periode' => 'Agustus 2026',
            'bulan' => 8,
            'tahun' => 2026,
        ]);

        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        Volt::test('pages.employees.index')->call('delete', $employee->id);

        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_operator_can_deactivate_employee_instead_of_deleting(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create(['status_aktif' => true]);

        Volt::test('pages.employees.index')->call('toggleActive', $employee->id);

        $this->assertFalse($employee->fresh()->status_aktif);
    }

    public function test_operator_can_save_identity_and_sensitive_fields(): void
    {
        $this->actingAsRole('operator_gaji');

        Volt::test('pages.employees.index')
            ->call('openCreate')
            ->set('nip', '195708201985031004')
            ->set('nik', '3372011234560001')
            ->set('nama', 'Prof. Drs. Suranto, M.Sc., Ph.D.')
            ->set('no_hp', '081234567890')
            ->set('id_simpeg', 'SIMPEG-00123')
            ->set('npwp', '09.876.543.2-111.000')
            ->set('no_rekening', '1234567890')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'nip' => '195708201985031004',
            'nik' => '3372011234560001',
            'no_hp' => '081234567890',
            'id_simpeg' => 'SIMPEG-00123',
            'npwp' => '09.876.543.2-111.000',
            'no_rekening' => '1234567890',
        ]);
    }

    public function test_nik_must_be_16_digits_and_unique(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create(['nik' => '3372011234560001']);

        Volt::test('pages.employees.index')
            ->call('openCreate')
            ->set('nip', '111111111111111111')
            ->set('nama', 'Pegawai Baru')
            ->set('nik', '3372011234560001')
            ->call('save')
            ->assertHasErrors('nik');

        Volt::test('pages.employees.index')
            ->call('openCreate')
            ->set('nip', '111111111111111111')
            ->set('nama', 'Pegawai Baru')
            ->set('nik', '123')
            ->call('save')
            ->assertHasErrors('nik');
    }

    public function test_npwp_and_rekening_are_not_exposed_in_employee_list(): void
    {
        $this->actingAsRole('operator_gaji');
        Employee::factory()->create([
            'nama' => 'Pegawai Rahasia',
            'npwp' => '01.234.567.8-999.000',
            'no_rekening' => '9988776655',
        ]);

        Volt::test('pages.employees.index')
            ->assertSee('Pegawai Rahasia')
            ->assertDontSee('01.234.567.8-999.000')
            ->assertDontSee('9988776655');
    }
}
