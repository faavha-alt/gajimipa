<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\RecurringDeduction;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Test integrasi tombol "Terapkan Potongan Berulang" di halaman Data
 * Potongan (bukan cuma service-nya sendiri di RecurringDeductionServiceTest).
 */
class TerapkanPotonganBerulangTest extends TestCase
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

    public function test_button_applies_recurring_deductions_to_draft_period(): void
    {
        $this->actingAsRole('operator_gaji');
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);

        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        RecurringDeduction::factory()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'nominal' => 120000,
            'dibuat_oleh' => auth()->id(),
        ]);

        Volt::test('pages.deduction-records.index')
            ->set('periodId', (string) $period->id)
            ->call('bukaTerapkanModal')
            ->assertSet('showTerapkanModal', true)
            ->call('konfirmasiTerapkan');

        $this->assertDatabaseHas('deduction_records', [
            'salary_record_id' => SalaryRecord::first()->id,
            'nominal' => 120000,
            'sumber' => DeductionRecord::SUMBER_BERULANG,
        ]);
    }

    public function test_button_hidden_for_non_draft_period(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();

        // "Terapkan Potongan Berulang" juga jadi judul modal konfirmasi yang
        // selalu ada di HTML server-render (disembunyikan lewat x-show di
        // klien, bukan dihapus dari markup) — jadi assert pada atribut
        // wire:click tombolnya, bukan teks labelnya, supaya tidak salah
        // ketangkep judul modal.
        Volt::test('pages.deduction-records.index')
            ->set('periodId', (string) $period->id)
            ->assertDontSee('wire:click="bukaTerapkanModal"', false);
    }

    public function test_verifikator_does_not_see_terapkan_button(): void
    {
        $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->create();

        Volt::test('pages.deduction-records.index')
            ->set('periodId', (string) $period->id)
            ->assertDontSee('wire:click="bukaTerapkanModal"', false);
    }
}
