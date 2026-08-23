<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\RecurringDeduction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RecurringDeductionBulkCreateTest extends TestCase
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

    public function test_operator_can_view_bulk_create_page(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('recurring-deductions.bulk-create'))
            ->assertOk()
            ->assertSeeVolt('pages.recurring-deductions.bulk-create');
    }

    public function test_verifikator_cannot_access_bulk_create_page(): void
    {
        $this->actingAsRole('verifikator');

        $this->get(route('recurring-deductions.bulk-create'))->assertForbidden();
    }

    public function test_can_enroll_multiple_employees_with_tarif_golongan_mode(): void
    {
        $this->actingAsRole('operator_gaji');
        $golongan = Golongan::factory()->create();
        $type = DeductionType::factory()->create();
        $e1 = Employee::factory()->create(['golongan_id' => $golongan->id]);
        $e2 = Employee::factory()->create(['golongan_id' => $golongan->id]);
        $eLain = Employee::factory()->create();

        Volt::test('pages.recurring-deductions.bulk-create')
            ->set('deductionTypeId', (string) $type->id)
            ->set('mode', 'TARIF_GOLONGAN')
            ->set('selected', [$e1->id, $e2->id])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recurring_deductions', ['employee_id' => $e1->id, 'deduction_type_id' => $type->id, 'mode' => 'TARIF_GOLONGAN', 'nominal' => null]);
        $this->assertDatabaseHas('recurring_deductions', ['employee_id' => $e2->id, 'deduction_type_id' => $type->id, 'mode' => 'TARIF_GOLONGAN']);
        $this->assertDatabaseMissing('recurring_deductions', ['employee_id' => $eLain->id]);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Tambah Massal Potongan Berulang']);
    }

    public function test_can_enroll_multiple_employees_with_shared_nominal_tetap_mode(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();
        $employees = Employee::factory()->count(3)->create();

        Volt::test('pages.recurring-deductions.bulk-create')
            ->set('deductionTypeId', (string) $type->id)
            ->set('mode', 'TETAP')
            ->set('nominal', '25000')
            ->set('selected', $employees->pluck('id')->all())
            ->call('submit')
            ->assertHasNoErrors();

        foreach ($employees as $employee) {
            $this->assertDatabaseHas('recurring_deductions', [
                'employee_id' => $employee->id,
                'deduction_type_id' => $type->id,
                'mode' => 'TETAP',
                'nominal' => 25000,
            ]);
        }
    }

    public function test_already_enrolled_employees_are_skipped_not_duplicated(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();
        $sudahTerdaftar = Employee::factory()->create();
        $baru = Employee::factory()->create();

        RecurringDeduction::factory()->create([
            'employee_id' => $sudahTerdaftar->id,
            'deduction_type_id' => $type->id,
            'status' => RecurringDeduction::STATUS_AKTIF,
            'dibuat_oleh' => User::factory()->create()->id,
        ]);

        Volt::test('pages.recurring-deductions.bulk-create')
            ->set('deductionTypeId', (string) $type->id)
            ->set('mode', 'TETAP')
            ->set('nominal', '10000')
            ->set('selected', [$sudahTerdaftar->id, $baru->id])
            ->call('submit');

        $this->assertSame(1, RecurringDeduction::where('employee_id', $sudahTerdaftar->id)->count());
        $this->assertSame(1, RecurringDeduction::where('employee_id', $baru->id)->count());
    }

    public function test_requires_at_least_one_employee_selected(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();

        Volt::test('pages.recurring-deductions.bulk-create')
            ->set('deductionTypeId', (string) $type->id)
            ->set('mode', 'TARIF_GOLONGAN')
            ->set('selected', [])
            ->call('submit')
            ->assertHasErrors('selected');
    }

    public function test_filter_by_golongan_narrows_employee_list(): void
    {
        $this->actingAsRole('operator_gaji');
        $golonganA = Golongan::factory()->create();
        $golonganB = Golongan::factory()->create();
        $eA = Employee::factory()->create(['golongan_id' => $golonganA->id, 'nama' => 'Pegawai Golongan A']);
        $eB = Employee::factory()->create(['golongan_id' => $golonganB->id, 'nama' => 'Pegawai Golongan B']);

        Volt::test('pages.recurring-deductions.bulk-create')
            ->set('filterGolonganId', (string) $golonganA->id)
            ->assertSee('Pegawai Golongan A')
            ->assertDontSee('Pegawai Golongan B');
    }

    public function test_filter_by_status_pegawai_narrows_employee_list(): void
    {
        $this->actingAsRole('operator_gaji');
        $statusA = EmployeeStatus::factory()->create();
        $statusB = EmployeeStatus::factory()->create();
        $eA = Employee::factory()->create(['employee_status_id' => $statusA->id, 'nama' => 'Pegawai Status A']);
        $eB = Employee::factory()->create(['employee_status_id' => $statusB->id, 'nama' => 'Pegawai Status B']);

        Volt::test('pages.recurring-deductions.bulk-create')
            ->set('filterEmployeeStatusId', (string) $statusA->id)
            ->assertSee('Pegawai Status A')
            ->assertDontSee('Pegawai Status B');
    }
}
