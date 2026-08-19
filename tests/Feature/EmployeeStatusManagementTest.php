<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmployeeStatusManagementTest extends TestCase
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

    public function test_super_admin_can_create_employee_status(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.employee-statuses.index')
            ->call('openCreate')
            ->set('kode', 'PNS')
            ->set('nama', 'Pegawai Negeri Sipil')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee_statuses', ['kode' => 'PNS', 'nama' => 'Pegawai Negeri Sipil']);
    }

    public function test_operator_can_view_but_not_manage_employee_statuses(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('employee-statuses.index'))->assertOk();

        Volt::test('pages.employee-statuses.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_pegawai_cannot_access_employee_statuses_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('employee-statuses.index'))->assertForbidden();
    }

    public function test_super_admin_cannot_delete_employee_status_in_use(): void
    {
        $this->actingAsRole('super_admin');
        $status = EmployeeStatus::factory()->create();
        Employee::factory()->create(['employee_status_id' => $status->id]);

        Volt::test('pages.employee-statuses.index')->call('delete', $status->id);

        $this->assertDatabaseHas('employee_statuses', ['id' => $status->id]);
    }
}
