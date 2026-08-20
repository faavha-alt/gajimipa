<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\Payslip\PayslipService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

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

    private function buatSalaryRecord(SalaryPeriod $period, ?Employee $employee = null): SalaryRecord
    {
        $employee ??= Employee::factory()->create();

        $record = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'total_penghasilan_kotor' => 8590495,
            'total_potongan_pusat' => 765895,
            'bersih_pusat' => 7824600,
            'total_potongan_fakultas' => 50000,
            'gaji_bersih_final' => 7774600,
        ]);

        SalaryComponent::create([
            'salary_record_id' => $record->id,
            'kategori' => SalaryComponent::KATEGORI_PENGHASILAN,
            'kode_komponen' => 'gjpokok',
            'nama_komponen' => 'Gaji Pokok',
            'nominal' => 6373200,
        ]);

        $type = DeductionType::factory()->create(['nama' => 'Koperasi']);
        DeductionRecord::create([
            'salary_record_id' => $record->id,
            'deduction_type_id' => $type->id,
            'nominal' => 50000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => User::factory()->create()->id,
        ]);

        return $record;
    }

    public function test_operator_can_generate_payslip_for_final_period(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $record = $this->buatSalaryRecord($period);

        $payslip = app(PayslipService::class)->generate($record, $operator);

        $this->assertSame('SLIP/MIPA/VIII/2026/0001', $payslip->nomor_dokumen);
        Storage::disk('local')->assertExists($payslip->path_file);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Generate Slip Gaji']);
    }

    public function test_cannot_generate_payslip_for_draft_period(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create(['status' => SalaryPeriod::STATUS_DRAFT]);
        $record = $this->buatSalaryRecord($period);

        $this->expectException(\RuntimeException::class);
        app(PayslipService::class)->generate($record, $operator);
    }

    public function test_document_numbers_are_sequential_within_period(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create(['bulan' => 3, 'tahun' => 2026]);
        $recordA = $this->buatSalaryRecord($period);
        $recordB = $this->buatSalaryRecord($period);

        $slipA = app(PayslipService::class)->generate($recordA, $operator);
        $slipB = app(PayslipService::class)->generate($recordB, $operator);

        $this->assertSame('SLIP/MIPA/III/2026/0001', $slipA->nomor_dokumen);
        $this->assertSame('SLIP/MIPA/III/2026/0002', $slipB->nomor_dokumen);
    }

    public function test_generate_batch_skips_records_that_already_have_payslip(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $this->buatSalaryRecord($period);
        $this->buatSalaryRecord($period);
        $this->buatSalaryRecord($period);

        $service = app(PayslipService::class);
        $dibuat1 = $service->generateBatch($period, $operator, batchSize: 2);
        $dibuat2 = $service->generateBatch($period, $operator, batchSize: 2);
        $dibuat3 = $service->generateBatch($period, $operator, batchSize: 2);

        $this->assertSame(2, $dibuat1);
        $this->assertSame(1, $dibuat2);
        $this->assertSame(0, $dibuat3);
        $this->assertSame(3, \App\Models\Payslip::count());
    }

    public function test_verifikator_cannot_access_payslip_management_page(): void
    {
        $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->final()->create();

        $this->get(route('payslips.index', $period))->assertForbidden();
    }

    public function test_employee_can_preview_own_payslip_but_not_others(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        $recordA = $this->buatSalaryRecord($period, $employeeA);

        $payslip = app(PayslipService::class)->generate($recordA, $operator);

        $userA = User::factory()->create(['employee_id' => $employeeA->id]);
        $userA->assignRole('pegawai');
        $userB = User::factory()->create(['employee_id' => $employeeB->id]);
        $userB->assignRole('pegawai');

        $this->actingAs($userA)->get(route('payslips.preview', $payslip))->assertOk();
        $this->actingAs($userB)->get(route('payslips.preview', $payslip))->assertForbidden();
    }

    public function test_employee_sees_own_payslips_on_mine_page(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $employee = Employee::factory()->create();
        $record = $this->buatSalaryRecord($period, $employee);
        app(PayslipService::class)->generate($record, $operator);

        $user = User::factory()->create(['employee_id' => $employee->id]);
        $user->assignRole('pegawai');
        $this->actingAs($user);

        Volt::test('pages.payslips.mine')->assertSee($period->nama_periode);
    }
}
