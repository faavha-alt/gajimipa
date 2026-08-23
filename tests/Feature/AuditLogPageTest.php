<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuditLogger;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
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

    public function test_super_admin_can_view_audit_log_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSeeVolt('pages.audit-logs.index');
    }

    public function test_operator_can_view_audit_log_page(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('audit-logs.index'))->assertOk();
    }

    public function test_verifikator_pimpinan_pegawai_cannot_access_audit_log_page(): void
    {
        foreach (['verifikator', 'pimpinan', 'pegawai'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('audit-logs.index'))->assertForbidden();
        }
    }

    public function test_operator_only_sees_own_activity(): void
    {
        $operatorA = $this->actingAsRole('operator_gaji');
        $this->actingAs($operatorA);
        AuditLogger::log('Aktivitas A', 'Dilakukan oleh operator A');

        $operatorB = User::factory()->create();
        $operatorB->assignRole('operator_gaji');
        $this->actingAs($operatorB);
        AuditLogger::log('Aktivitas B', 'Dilakukan oleh operator B');

        $this->actingAs($operatorA);

        Volt::test('pages.audit-logs.index')
            ->assertSee('Aktivitas A')
            ->assertDontSee('Aktivitas B');
    }

    public function test_super_admin_sees_activity_from_all_users(): void
    {
        $admin = $this->actingAsRole('super_admin');

        $operator = User::factory()->create();
        $operator->assignRole('operator_gaji');
        $this->actingAs($operator);
        AuditLogger::log('Aktivitas Operator', 'Dilakukan oleh operator');

        $this->actingAs($admin);
        AuditLogger::log('Aktivitas Admin', 'Dilakukan oleh admin');

        Volt::test('pages.audit-logs.index')
            ->assertSee('Aktivitas Operator')
            ->assertSee('Aktivitas Admin');
    }

    public function test_filter_by_aktivitas(): void
    {
        $admin = $this->actingAsRole('super_admin');

        AuditLogger::log('Login', 'Masuk ke sistem');
        AuditLogger::log('Import Gaji', 'Impor data gaji pusat');

        Volt::test('pages.audit-logs.index')
            ->set('filterAktivitas', 'Login')
            ->assertSee('Masuk ke sistem')
            ->assertDontSee('Impor data gaji pusat');
    }
}
