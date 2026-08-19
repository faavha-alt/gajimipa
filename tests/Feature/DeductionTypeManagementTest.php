<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\DeductionRecord;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeductionTypeManagementTest extends TestCase
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

    public function test_super_admin_can_view_deduction_types_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('deduction-types.index'))
            ->assertOk()
            ->assertSeeVolt('pages.deduction-types.index');
    }

    public function test_pegawai_cannot_access_deduction_types_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('deduction-types.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_deduction_type_with_auto_generated_kode(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.deduction-types.index')
            ->call('openCreate')
            ->set('nama', 'Koperasi UNS - Simpanan Wajib')
            ->set('keterangan', 'Dipotong tiap bulan otomatis')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deduction_types', [
            'kode' => 'KOPERASI_UNS_SIMPANAN_WAJIB',
            'nama' => 'Koperasi UNS - Simpanan Wajib',
        ]);
    }

    public function test_kode_is_stable_across_edits_and_duplicate_names_get_suffixed(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.deduction-types.index')
            ->call('openCreate')->set('nama', 'BPJS')->call('save');

        // Nama yang menghasilkan kode dasar sama harus otomatis diberi suffix, bukan error.
        Volt::test('pages.deduction-types.index')
            ->call('openCreate')->set('nama', 'BPJS')->call('save');

        $this->assertDatabaseHas('deduction_types', ['kode' => 'BPJS']);
        $this->assertDatabaseHas('deduction_types', ['kode' => 'BPJS_2']);

        // Kode tidak berubah saat nama diedit setelah dibuat.
        $type = DeductionType::where('kode', 'BPJS')->first();
        Volt::test('pages.deduction-types.index')
            ->call('openEdit', $type->id)
            ->set('nama', 'BPJS Kesehatan (diubah)')
            ->call('save');

        $this->assertSame('BPJS', $type->fresh()->kode);
        $this->assertSame('BPJS Kesehatan (diubah)', $type->fresh()->nama);
    }

    public function test_operator_can_view_but_not_manage_deduction_types(): void
    {
        $this->actingAsRole('operator_gaji');

        $this->get(route('deduction-types.index'))->assertOk();

        Volt::test('pages.deduction-types.index')
            ->call('openCreate')
            ->assertStatus(403);
    }

    public function test_super_admin_cannot_delete_deduction_type_in_use(): void
    {
        $admin = $this->actingAsRole('super_admin');
        $type = DeductionType::factory()->create();
        $employee = Employee::factory()->create();
        $period = SalaryPeriod::factory()->create();

        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => 10000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => $admin->id,
        ]);

        Volt::test('pages.deduction-types.index')->call('delete', $type->id);

        $this->assertDatabaseHas('deduction_types', ['id' => $type->id]);
    }

    public function test_super_admin_can_deactivate_deduction_type(): void
    {
        $this->actingAsRole('super_admin');
        $type = DeductionType::factory()->create(['status_aktif' => true]);

        Volt::test('pages.deduction-types.index')->call('toggleActive', $type->id);

        $this->assertFalse($type->fresh()->status_aktif);
    }
}
