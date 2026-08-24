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

    public function test_operator_can_create_rate_by_golongan_kelompok(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();
        Golongan::factory()->create(['nama' => 'III/a']);

        Volt::test('pages.recurring-deductions.tarif')
            ->call('openCreate')
            ->set('deductionTypeId', (string) $type->id)
            ->set('tipe', 'GOLONGAN')
            ->set('golonganKelompok', 'III')
            ->set('nominal', '85000')
            ->set('berlakuMulai', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deduction_rates', [
            'deduction_type_id' => $type->id,
            'golongan_kelompok' => 'III',
            'employee_status_id' => null,
            'nominal' => 85000,
        ]);
    }

    public function test_one_rate_applies_to_every_sub_golongan_in_the_group(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();
        Golongan::factory()->create(['nama' => 'III/a']);
        $subGolonganLain = Golongan::factory()->create(['nama' => 'III/b']);

        Volt::test('pages.recurring-deductions.tarif')
            ->call('openCreate')
            ->set('deductionTypeId', (string) $type->id)
            ->set('tipe', 'GOLONGAN')
            ->set('golonganKelompok', 'III')
            ->set('nominal', '1500')
            ->set('berlakuMulai', '2026-01-01')
            ->call('save');

        $this->assertSame('III', $subGolonganLain->kelompok());
        $this->assertSame(1, DeductionRate::where('golongan_kelompok', 'III')->count());
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
            'golongan_kelompok' => null,
            'nominal' => 30000,
        ]);
    }

    public function test_golongan_required_when_tipe_is_golongan(): void
    {
        $this->actingAsRole('operator_gaji');
        $type = DeductionType::factory()->create();
        Golongan::factory()->create();

        Volt::test('pages.recurring-deductions.tarif')
            ->call('openCreate')
            ->set('deductionTypeId', (string) $type->id)
            ->set('tipe', 'GOLONGAN')
            ->set('golonganKelompok', '')
            ->set('nominal', '50000')
            ->set('berlakuMulai', '2026-01-01')
            ->call('save')
            ->assertHasErrors('golonganKelompok');
    }

    public function test_can_edit_rate_berlaku_mulai_and_nominal(): void
    {
        $this->actingAsRole('operator_gaji');
        Golongan::factory()->create(['nama' => 'III/a']);
        $rate = DeductionRate::factory()->create([
            'golongan_kelompok' => 'III',
            'nominal' => 5000,
            'berlaku_mulai' => '2026-08-24',
        ]);

        Volt::test('pages.recurring-deductions.tarif')
            ->call('openEdit', $rate->id)
            ->assertSet('nominal', '5000.00')
            ->assertSet('golonganKelompok', 'III')
            ->set('nominal', '7500')
            ->set('berlakuMulai', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $rate->refresh();
        $this->assertSame('7500.00', $rate->nominal);
        $this->assertSame('2026-01-01', $rate->berlaku_mulai->toDateString());
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Ubah Tarif Potongan']);
    }

    public function test_editing_does_not_create_a_duplicate_row(): void
    {
        $this->actingAsRole('operator_gaji');
        Golongan::factory()->create(['nama' => 'III/a']);
        $rate = DeductionRate::factory()->create(['golongan_kelompok' => 'III']);

        Volt::test('pages.recurring-deductions.tarif')
            ->call('openEdit', $rate->id)
            ->set('nominal', '99000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, DeductionRate::count());
        $this->assertSame('99000.00', $rate->fresh()->nominal);
    }

    public function test_can_delete_rate(): void
    {
        $this->actingAsRole('operator_gaji');
        $rate = DeductionRate::factory()->create();

        Volt::test('pages.recurring-deductions.tarif')->call('delete', $rate->id);

        $this->assertNull($rate->fresh());
    }
}
