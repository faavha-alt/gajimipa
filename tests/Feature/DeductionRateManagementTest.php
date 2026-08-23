<?php

namespace Tests\Feature;

use App\Models\DeductionRate;
use App\Models\DeductionType;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeductionRateManagementTest extends TestCase
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

    public function test_operator_can_view_tarif_page(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('recurring-deductions.tarif'))
            ->assertOk()
            ->assertSeeVolt('pages.recurring-deductions.tarif');
    }

    public function test_pegawai_cannot_access_tarif_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('recurring-deductions.tarif'))->assertForbidden();
    }

    public function test_operator_can_create_rate_by_golongan(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();
        $golongan = Golongan::factory()->create();

        Volt::test('pages.recurring-deductions.tarif')
            ->call('openCreate')
            ->set('deductionTypeId', (string) $type->id)
            ->set('tipe', 'GOLONGAN')
            ->set('golonganId', (string) $golongan->id)
            ->set('nominal', '85000')
            ->set('berlakuMulai', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deduction_rates', [
            'deduction_type_id' => $type->id,
            'golongan_id' => $golongan->id,
            'employee_status_id' => null,
            'nominal' => 85000,
        ]);
    }

    public function test_operator_can_create_rate_by_status_pegawai(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();
        $status = EmployeeStatus::factory()->create();

        Volt::test('pages.recurring-deductions.tarif')
            ->call('openCreate')
            ->set('deductionTypeId', (string) $type->id)
            ->set('tipe', 'STATUS_PEGAWAI')
            ->set('employeeStatusId', (string) $status->id)
            ->set('nominal', '30000')
            ->set('berlakuMulai', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deduction_rates', [
            'deduction_type_id' => $type->id,
            'employee_status_id' => $status->id,
            'golongan_id' => null,
            'nominal' => 30000,
        ]);
    }

    public function test_golongan_required_when_tipe_is_golongan(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();

        Volt::test('pages.recurring-deductions.tarif')
            ->call('openCreate')
            ->set('deductionTypeId', (string) $type->id)
            ->set('tipe', 'GOLONGAN')
            ->set('golonganId', '')
            ->set('nominal', '50000')
            ->set('berlakuMulai', '2026-01-01')
            ->call('save')
            ->assertHasErrors('golonganId');
    }

    public function test_can_delete_rate(): void
    {
        $this->actingAsRole('operator_gaji');
        $rate = DeductionRate::factory()->create();

        Volt::test('pages.recurring-deductions.tarif')->call('delete', $rate->id);

        $this->assertNull($rate->fresh());
    }
}
