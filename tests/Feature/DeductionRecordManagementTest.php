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
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeductionRecordManagementTest extends TestCase
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

    private function employeeWithSalaryRecord(SalaryPeriod $period): Employee
    {
        $employee = Employee::factory()->create();

        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        return $employee;
    }

    public function test_pegawai_cannot_access_deduction_records_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('deduction-records.index'))->assertForbidden();
    }

    public function test_verifikator_can_view_but_not_manage(): void
    {
        $this->actingAsRole('verifikator');

        $this->get(route('deduction-records.index'))->assertOk();

        Volt::test('pages.deduction-records.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_operator_can_add_manual_deduction(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $employee = $this->employeeWithSalaryRecord($period);
        $type = DeductionType::factory()->create();

        Volt::test('pages.deduction-records.index')
            ->set('periodId', (string) $period->id)
            ->call('openCreate')
            ->set('employeeId', (string) $employee->id)
            ->set('deductionTypeId', (string) $type->id)
            ->set('nominal', '50000')
            ->set('keterangan', 'Iuran bulanan')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deduction_records', [
            'deduction_type_id' => $type->id,
            'nominal' => 50000,
            'sumber' => 'MANUAL',
        ]);
    }

    public function test_cannot_add_deduction_for_employee_without_salary_record(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $employeeWithoutRecord = Employee::factory()->create();
        $type = DeductionType::factory()->create();

        // eligibleEmployees kosong karena tidak ada salary_record, tapi kita
        // paksa set employeeId untuk memastikan guard server-side jalan,
        // bukan cuma bergantung pada dropdown yang kosong di UI.
        Volt::test('pages.deduction-records.index')
            ->set('periodId', (string) $period->id)
            ->call('openCreate')
            ->set('employeeId', (string) $employeeWithoutRecord->id)
            ->set('deductionTypeId', (string) $type->id)
            ->set('nominal', '50000')
            ->call('save')
            ->assertHasErrors('employeeId');

        $this->assertSame(0, DeductionRecord::count());
    }

    public function test_cannot_manage_deduction_when_period_not_draft(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->verifikasi()->create();
        $employee = $this->employeeWithSalaryRecord($period);
        $type = DeductionType::factory()->create();

        Volt::test('pages.deduction-records.index')
            ->set('periodId', (string) $period->id)
            ->call('openCreate')
            ->set('employeeId', (string) $employee->id)
            ->set('deductionTypeId', (string) $type->id)
            ->set('nominal', '50000')
            ->call('save');

        $this->assertSame(0, DeductionRecord::count());
    }

    public function test_operator_can_edit_and_delete_manual_deduction(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $employee = $this->employeeWithSalaryRecord($period);
        $type = DeductionType::factory()->create();
        $salaryRecord = SalaryRecord::where('employee_id', $employee->id)->first();

        $record = DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => 10000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => $operator->id,
        ]);

        Volt::test('pages.deduction-records.index')
            ->set('periodId', (string) $period->id)
            ->call('openEdit', $record->id)
            ->assertSet('nominal', '10000.00')
            ->set('nominal', '75000')
            ->call('save');

        $this->assertSame('75000.00', $record->fresh()->nominal);

        Volt::test('pages.deduction-records.index')
            ->set('periodId', (string) $period->id)
            ->call('delete', $record->id);

        $this->assertSame(0, DeductionRecord::count());
    }
}
