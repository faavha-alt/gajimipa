<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Golongan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GolonganManagementTest extends TestCase
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

    public function test_super_admin_can_view_golongans_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('golongans.index'))
            ->assertOk()
            ->assertSeeVolt('pages.golongans.index');
    }

    public function test_pegawai_cannot_access_golongans_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('golongans.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_golongan(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.golongans.index')
            ->call('openCreate')
            ->set('kode', '45')
            ->set('nama', 'III/a')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('golongans', [
            'kode' => '45',
            'nama' => 'III/a',
            'status_aktif' => true,
        ]);
    }

    public function test_kode_golongan_must_be_unique(): void
    {
        $this->actingAsRole('super_admin');
        Golongan::factory()->create(['kode' => '45']);

        Volt::test('pages.golongans.index')
            ->call('openCreate')
            ->set('kode', '45')
            ->set('nama', 'III/a Duplikat')
            ->call('save')
            ->assertHasErrors('kode');
    }

    public function test_operator_can_view_but_not_manage_golongans(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('golongans.index'))->assertOk();

        Volt::test('pages.golongans.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_super_admin_cannot_delete_golongan_with_employees(): void
    {
        $this->actingAsRole('super_admin');
        $golongan = Golongan::factory()->create();
        Employee::factory()->create(['golongan_id' => $golongan->id]);

        Volt::test('pages.golongans.index')->call('delete', $golongan->id);

        $this->assertDatabaseHas('golongans', ['id' => $golongan->id]);
    }

    public function test_super_admin_can_deactivate_golongan(): void
    {
        $this->actingAsRole('super_admin');
        $golongan = Golongan::factory()->create(['status_aktif' => true]);

        Volt::test('pages.golongans.index')->call('toggleActive', $golongan->id);

        $this->assertFalse($golongan->fresh()->status_aktif);
    }
}
