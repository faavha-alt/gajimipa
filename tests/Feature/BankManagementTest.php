<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BankManagementTest extends TestCase
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

    public function test_super_admin_can_view_banks_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('banks.index'))
            ->assertOk()
            ->assertSeeVolt('pages.banks.index');
    }

    public function test_pegawai_cannot_access_banks_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('banks.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_bank(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.banks.index')
            ->call('openCreate')
            ->set('kode', 'BRI')
            ->set('nama', 'Bank Rakyat Indonesia')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('banks', [
            'kode' => 'BRI',
            'nama' => 'Bank Rakyat Indonesia',
            'status_aktif' => true,
        ]);
    }

    public function test_kode_bank_must_be_unique(): void
    {
        $this->actingAsRole('super_admin');
        Bank::factory()->create(['kode' => 'BRI']);

        Volt::test('pages.banks.index')
            ->call('openCreate')
            ->set('kode', 'BRI')
            ->set('nama', 'Bank Rakyat Indonesia Duplikat')
            ->call('save')
            ->assertHasErrors('kode');
    }

    public function test_operator_can_view_but_not_manage_banks(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('banks.index'))->assertOk();

        Volt::test('pages.banks.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_super_admin_cannot_delete_bank_with_employees(): void
    {
        $this->actingAsRole('super_admin');
        $bank = Bank::factory()->create();
        Employee::factory()->create(['bank_id' => $bank->id]);

        Volt::test('pages.banks.index')->call('delete', $bank->id);

        $this->assertDatabaseHas('banks', ['id' => $bank->id]);
    }

    public function test_super_admin_can_deactivate_bank(): void
    {
        $this->actingAsRole('super_admin');
        $bank = Bank::factory()->create(['status_aktif' => true]);

        Volt::test('pages.banks.index')->call('toggleActive', $bank->id);

        $this->assertFalse($bank->fresh()->status_aktif);
    }
}
