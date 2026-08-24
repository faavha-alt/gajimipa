<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\RecurringDeduction;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RecurringDeductionManagementTest extends TestCase
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

    public function test_operator_can_view_page(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('recurring-deductions.index'))
            ->assertOk()
            ->assertSeeVolt('pages.recurring-deductions.index');
    }

    public function test_pegawai_cannot_access_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('recurring-deductions.index'))->assertForbidden();
    }

    public function test_verifikator_can_view_but_not_manage(): void
    {
        $this->actingAsRole('verifikator');

        $this->get(route('recurring-deductions.index'))->assertOk();

        Volt::test('pages.recurring-deductions.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_operator_can_create_tetap_mode(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();

        Volt::test('pages.recurring-deductions.index')
            ->call('openCreate')
            ->set('employeeId', (string) $employee->id)
            ->set('deductionTypeId', (string) $type->id)
            ->set('mode', 'TETAP')
            ->set('nominal', '200000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recurring_deductions', [
            'employee_id' => $employee->id,
            'mode' => 'TETAP',
            'nominal' => 200000,
        ]);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Buat Potongan Berulang']);
    }

    public function test_tetap_mode_requires_nominal(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();

        Volt::test('pages.recurring-deductions.index')
            ->call('openCreate')
            ->set('employeeId', (string) $employee->id)
            ->set('deductionTypeId', (string) $type->id)
            ->set('mode', 'TETAP')
            ->set('nominal', '')
            ->call('save')
            ->assertHasErrors('nominal');
    }

    public function test_angsuran_mode_requires_jumlah_cicilan(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();

        Volt::test('pages.recurring-deductions.index')
            ->call('openCreate')
            ->set('employeeId', (string) $employee->id)
            ->set('deductionTypeId', (string) $type->id)
            ->set('mode', 'ANGSURAN')
            ->set('nominal', '500000')
            ->set('jumlahCicilan', '')
            ->call('save')
            ->assertHasErrors('jumlahCicilan');
    }

    public function test_tarif_golongan_mode_does_not_require_nominal(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();

        Volt::test('pages.recurring-deductions.index')
            ->call('openCreate')
            ->set('employeeId', (string) $employee->id)
            ->set('deductionTypeId', (string) $type->id)
            ->set('mode', 'TARIF_GOLONGAN')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recurring_deductions', [
            'employee_id' => $employee->id,
            'mode' => 'TARIF_GOLONGAN',
            'nominal' => null,
        ]);
    }

    public function test_hentikan_stops_recurring_deduction(): void
    {
        $this->actingAsRole('operator_gaji');
        $rd = RecurringDeduction::factory()->create(['dibuat_oleh' => User::factory()->create()->id]);

        Volt::test('pages.recurring-deductions.index')->call('hentikan', $rd->id);

        $this->assertSame('DIHENTIKAN', $rd->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Hentikan Potongan Berulang']);
    }

    public function test_cannot_delete_recurring_deduction_already_used(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();
        $period = SalaryPeriod::factory()->create();
        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        $rd = RecurringDeduction::factory()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'dibuat_oleh' => User::factory()->create()->id,
        ]);

        \App\Models\DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'recurring_deduction_id' => $rd->id,
            'nominal' => 100000,
            'sumber' => 'BERULANG',
            'dibuat_oleh' => auth()->id(),
        ]);

        Volt::test('pages.recurring-deductions.index')->call('delete', $rd->id);

        $this->assertNotNull($rd->fresh());
    }

    public function test_can_delete_recurring_deduction_never_used(): void
    {
        $this->actingAsRole('operator_gaji');
        $rd = RecurringDeduction::factory()->create(['dibuat_oleh' => User::factory()->create()->id]);

        Volt::test('pages.recurring-deductions.index')->call('delete', $rd->id);

        $this->assertNull($rd->fresh());
    }

    public function test_filter_by_golongan_narrows_the_list(): void
    {
        // "Pegawai Golongan B" tetap muncul di dropdown pegawai pada modal
        // Tambah/Edit (selalu ada di HTML, cuma disembunyikan x-show di
        // klien) — jadi assert pada wire:key baris tabelnya, bukan teks
        // nama, supaya tidak salah ketangkep dropdown itu.
        $this->actingAsRole('operator_gaji');
        $creator = User::factory()->create();
        $golonganA = Golongan::factory()->create();
        $golonganB = Golongan::factory()->create();
        $employeeA = Employee::factory()->create(['golongan_id' => $golonganA->id]);
        $employeeB = Employee::factory()->create(['golongan_id' => $golonganB->id]);

        $rdA = RecurringDeduction::factory()->create(['employee_id' => $employeeA->id, 'dibuat_oleh' => $creator->id]);
        $rdB = RecurringDeduction::factory()->create(['employee_id' => $employeeB->id, 'dibuat_oleh' => $creator->id]);

        Volt::test('pages.recurring-deductions.index')
            ->set('filterGolonganId', (string) $golonganA->id)
            ->assertSee('wire:key="rd-'.$rdA->id.'"', false)
            ->assertDontSee('wire:key="rd-'.$rdB->id.'"', false);
    }

    public function test_filter_by_status_pegawai_narrows_the_list(): void
    {
        $this->actingAsRole('operator_gaji');
        $creator = User::factory()->create();
        $statusA = EmployeeStatus::factory()->create();
        $statusB = EmployeeStatus::factory()->create();
        $employeeA = Employee::factory()->create(['employee_status_id' => $statusA->id]);
        $employeeB = Employee::factory()->create(['employee_status_id' => $statusB->id]);

        $rdA = RecurringDeduction::factory()->create(['employee_id' => $employeeA->id, 'dibuat_oleh' => $creator->id]);
        $rdB = RecurringDeduction::factory()->create(['employee_id' => $employeeB->id, 'dibuat_oleh' => $creator->id]);

        Volt::test('pages.recurring-deductions.index')
            ->set('filterEmployeeStatusId', (string) $statusA->id)
            ->assertSee('wire:key="rd-'.$rdA->id.'"', false)
            ->assertDontSee('wire:key="rd-'.$rdB->id.'"', false);
    }
}
