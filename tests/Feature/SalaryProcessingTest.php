<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\Salary\SalaryProcessingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SalaryProcessingTest extends TestCase
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

    public function test_verifikator_cannot_access_processing_page(): void
    {
        $this->actingAsRole('verifikator');

        $this->get(route('salary-processing.create'))->assertForbidden();
    }

    public function test_operator_can_process_salary_combining_pusat_and_deductions(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $employee = Employee::factory()->create();

        // Data nyata baris "Suranto" (docs/pemetaan-field-gaji.md SS3): bersih pusat 7.824.600.
        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'total_penghasilan_kotor' => 8590495,
            'total_potongan_pusat' => 765895,
            'bersih_pusat' => 7824600,
            'total_potongan_fakultas' => 0,
            'gaji_bersih_final' => 7824600,
        ]);

        $koperasi = DeductionType::factory()->create();
        $kesejahteraan = DeductionType::factory()->create();

        DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $koperasi->id,
            'nominal' => 85000,
            'sumber' => DeductionRecord::SUMBER_IMPORT,
            'dibuat_oleh' => $operator->id,
        ]);
        DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $kesejahteraan->id,
            'nominal' => 9000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => $operator->id,
        ]);

        // 'preview' dihitung ulang tiap render lewat with(), bukan properti
        // ter-track — dicek langsung lewat service, bukan Testable::get().
        $preview = app(SalaryProcessingService::class)->preview($period);
        $this->assertEquals(94000, $preview[0]['total_potongan_fakultas_baru']);
        $this->assertEquals(7730600, $preview[0]['gaji_bersih_final_baru']);
        $this->assertTrue($preview[0]['berubah']);

        Volt::test('pages.salary-processing.create')
            ->set('periodId', (string) $period->id)
            ->assertSee('94.000')
            ->assertSee('7.730.600')
            ->call('proses');

        $salaryRecord->refresh();
        $this->assertEquals(94000, $salaryRecord->total_potongan_fakultas);
        $this->assertEquals(7730600, $salaryRecord->gaji_bersih_final);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Proses Gaji']);
    }

    public function test_preview_shows_no_change_after_processing(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $employee = Employee::factory()->create();

        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'bersih_pusat' => 5000000,
            'gaji_bersih_final' => 5000000,
        ]);

        $type = DeductionType::factory()->create();
        DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => 20000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => $operator->id,
        ]);

        app(SalaryProcessingService::class)->proses($period, $operator);

        $preview = app(SalaryProcessingService::class)->preview($period);
        $this->assertFalse($preview[0]['berubah']);
    }

    public function test_cannot_process_non_draft_period(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->verifikasi()->create();

        $this->expectException(\RuntimeException::class);
        app(SalaryProcessingService::class)->proses($period, $operator);
    }

    public function test_cannot_process_period_without_salary_data(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(SalaryProcessingService::class)->proses($period, $operator);
    }
}
