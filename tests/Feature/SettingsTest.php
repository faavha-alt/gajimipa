<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SettingsTest extends TestCase
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

    public function test_settings_default_to_hardcoded_values_when_table_empty(): void
    {
        $values = Settings::all();

        $this->assertSame('UNIVERSITAS SEBELAS MARET', $values['nama_universitas']);
        $this->assertSame('FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM', $values['nama_fakultas']);
        $this->assertSame('SLIP/MIPA', $values['prefix_nomor_slip']);
        $this->assertSame('POTONGAN/MIPA', $values['prefix_nomor_potongan']);
    }

    public function test_super_admin_can_view_settings_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSeeVolt('pages.settings.index');
    }

    public function test_non_super_admin_cannot_access_settings_page(): void
    {
        foreach (['operator_gaji', 'verifikator', 'pimpinan', 'pegawai'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('settings.index'))->assertForbidden();
        }
    }

    public function test_super_admin_can_update_settings(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.settings.index')
            ->set('nama_fakultas', 'FAKULTAS BARU')
            ->set('nama_universitas', 'UNIVERSITAS BARU')
            ->set('alamat_fakultas', 'Jl. Ir. Sutami 36A, Surakarta')
            ->set('prefix_nomor_slip', 'SLIP/TEST')
            ->set('prefix_nomor_potongan', 'POTONGAN/TEST')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('FAKULTAS BARU', SystemSetting::where('key', 'nama_fakultas')->value('value'));
        $this->assertSame('SLIP/TEST', Settings::get('prefix_nomor_slip'));
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Ubah Pengaturan']);
    }

    public function test_prefix_with_invalid_characters_is_rejected(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.settings.index')
            ->set('prefix_nomor_slip', 'slip mipa!')
            ->call('save')
            ->assertHasErrors('prefix_nomor_slip');
    }
}
