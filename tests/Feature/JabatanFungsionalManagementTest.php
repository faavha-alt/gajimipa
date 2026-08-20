<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JabatanFungsional;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JabatanFungsionalManagementTest extends TestCase
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

    public function test_super_admin_can_view_jabatan_fungsionals_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('jabatan-fungsionals.index'))
            ->assertOk()
            ->assertSeeVolt('pages.jabatan-fungsionals.index');
    }

    public function test_pegawai_cannot_access_jabatan_fungsionals_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('jabatan-fungsionals.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_jabatan_fungsional(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.jabatan-fungsionals.index')
            ->call('openCreate')
            ->set('kode', '06901')
            ->set('nama', 'Lektor Kepala')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jabatan_fungsionals', [
            'kode' => '06901',
            'nama' => 'Lektor Kepala',
            'status_aktif' => true,
        ]);
    }

    public function test_kode_with_spaces_is_allowed(): void
    {
        // Kode Non-PNS bisa berupa frasa mentah FUNGSIONAL, mis. "Tenaga Pengajar".
        $this->actingAsRole('super_admin');

        Volt::test('pages.jabatan-fungsionals.index')
            ->call('openCreate')
            ->set('kode', 'Tenaga Pengajar')
            ->set('nama', 'Tenaga Pengajar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jabatan_fungsionals', ['kode' => 'Tenaga Pengajar']);
    }

    public function test_kode_jabatan_fungsional_must_be_unique(): void
    {
        $this->actingAsRole('super_admin');
        JabatanFungsional::factory()->create(['kode' => '06901']);

        Volt::test('pages.jabatan-fungsionals.index')
            ->call('openCreate')
            ->set('kode', '06901')
            ->set('nama', 'Duplikat')
            ->call('save')
            ->assertHasErrors('kode');
    }

    public function test_operator_can_view_but_not_manage_jabatan_fungsionals(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('jabatan-fungsionals.index'))->assertOk();

        Volt::test('pages.jabatan-fungsionals.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_super_admin_cannot_delete_jabatan_fungsional_with_employees(): void
    {
        $this->actingAsRole('super_admin');
        $jabatan = JabatanFungsional::factory()->create();
        Employee::factory()->create(['jabatan_fungsional_id' => $jabatan->id]);

        Volt::test('pages.jabatan-fungsionals.index')->call('delete', $jabatan->id);

        $this->assertDatabaseHas('jabatan_fungsionals', ['id' => $jabatan->id]);
    }

    public function test_super_admin_can_deactivate_jabatan_fungsional(): void
    {
        $this->actingAsRole('super_admin');
        $jabatan = JabatanFungsional::factory()->create(['status_aktif' => true]);

        Volt::test('pages.jabatan-fungsionals.index')->call('toggleActive', $jabatan->id);

        $this->assertFalse($jabatan->fresh()->status_aktif);
    }
}
