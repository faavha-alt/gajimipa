<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UnitManagementTest extends TestCase
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

    public function test_super_admin_can_view_units_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('units.index'))
            ->assertOk()
            ->assertSeeVolt('pages.units.index');
    }

    public function test_pegawai_cannot_access_units_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('units.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_unit(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.units.index')
            ->call('openCreate')
            ->set('kode_unit', 'PRODI-MAT')
            ->set('nama_unit', 'Prodi Matematika')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', [
            'kode_unit' => 'PRODI-MAT',
            'nama_unit' => 'Prodi Matematika',
            'status_aktif' => true,
        ]);
    }

    public function test_kode_unit_must_be_unique(): void
    {
        $this->actingAsRole('super_admin');
        Unit::factory()->create(['kode_unit' => 'PRODI-FIS']);

        Volt::test('pages.units.index')
            ->call('openCreate')
            ->set('kode_unit', 'PRODI-FIS')
            ->set('nama_unit', 'Prodi Fisika Duplikat')
            ->call('save')
            ->assertHasErrors('kode_unit');
    }

    public function test_operator_can_view_but_not_manage_units(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('units.index'))->assertOk();

        Volt::test('pages.units.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_super_admin_cannot_delete_unit_with_employees(): void
    {
        $this->actingAsRole('super_admin');
        $unit = Unit::factory()->create();
        Employee::factory()->create(['unit_id' => $unit->id]);

        Volt::test('pages.units.index')->call('delete', $unit->id);

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_super_admin_can_deactivate_unit(): void
    {
        $this->actingAsRole('super_admin');
        $unit = Unit::factory()->create(['status_aktif' => true]);

        Volt::test('pages.units.index')->call('toggleActive', $unit->id);

        $this->assertFalse($unit->fresh()->status_aktif);
    }
}
