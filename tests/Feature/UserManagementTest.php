<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_super_admin_can_view_users_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSeeVolt('pages.users.index');
    }

    public function test_non_super_admin_cannot_access_users_page(): void
    {
        foreach (['operator_gaji', 'verifikator', 'pimpinan', 'pegawai'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('users.index'))->assertForbidden();
        }
    }

    public function test_super_admin_can_create_user_with_role_and_employee(): void
    {
        $this->actingAsRole('super_admin');
        $employee = Employee::factory()->create();

        Volt::test('pages.users.index')
            ->call('openCreate')
            ->set('name', 'Budi Operator')
            ->set('email', 'budi@staff.uns.ac.id')
            ->set('password', 'rahasia123')
            ->set('role', 'operator_gaji')
            ->set('employee_id', (string) $employee->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'budi@staff.uns.ac.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('operator_gaji'));
        $this->assertSame($employee->id, $user->employee_id);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Buat User']);
    }

    public function test_pegawai_role_requires_employee_link(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.users.index')
            ->call('openCreate')
            ->set('name', 'Pegawai Baru')
            ->set('email', 'pegawai@staff.uns.ac.id')
            ->set('password', 'rahasia123')
            ->set('role', 'pegawai')
            ->set('employee_id', '')
            ->call('save')
            ->assertHasErrors('employee_id');
    }

    public function test_cannot_link_same_employee_to_two_users(): void
    {
        $this->actingAsRole('super_admin');
        $employee = Employee::factory()->create();
        User::factory()->create(['employee_id' => $employee->id]);

        Volt::test('pages.users.index')
            ->call('openCreate')
            ->set('name', 'Duplikat')
            ->set('email', 'duplikat@staff.uns.ac.id')
            ->set('password', 'rahasia123')
            ->set('role', 'pegawai')
            ->set('employee_id', (string) $employee->id)
            ->call('save')
            ->assertHasErrors('employee_id');
    }

    public function test_editing_without_password_keeps_old_password(): void
    {
        $admin = $this->actingAsRole('super_admin');
        $target = User::factory()->create(['password' => Hash::make('password-lama')]);
        $target->assignRole('operator_gaji');

        Volt::test('pages.users.index')
            ->call('openEdit', $target->id)
            ->set('name', 'Nama Baru')
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('password-lama', $target->fresh()->password));
        $this->assertSame('Nama Baru', $target->fresh()->name);
    }

    public function test_super_admin_can_deactivate_other_user_but_not_self(): void
    {
        $admin = $this->actingAsRole('super_admin');
        $other = User::factory()->create();
        $other->assignRole('operator_gaji');

        Volt::test('pages.users.index')->call('toggleActive', $other->id);
        $this->assertFalse($other->fresh()->status_aktif);

        Volt::test('pages.users.index')->call('toggleActive', $admin->id);
        $this->assertTrue($admin->fresh()->status_aktif);
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create(['status_aktif' => true]);
        $user->assignRole('operator_gaji');
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();

        $user->update(['status_aktif' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
