<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\DeductionReceipt\DeductionReceiptService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeductionReceiptTest extends TestCase
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

    private function buatDeductionRecord(SalaryPeriod $period, ?Employee $employee = null): DeductionRecord
    {
        $employee ??= Employee::factory()->create();

        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'bersih_pusat' => 7824600,
            'total_potongan_fakultas' => 50000,
            'gaji_bersih_final' => 7774600,
        ]);

        $type = DeductionType::factory()->create(['nama' => 'Koperasi']);

        return DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => 50000,
            'keterangan' => 'Simpanan wajib',
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => User::factory()->create()->id,
        ]);
    }

    public function test_operator_can_generate_receipt_for_final_period(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $record = $this->buatDeductionRecord($period);

        $receipt = app(DeductionReceiptService::class)->generate($record, $operator);

        $this->assertSame('POTONGAN/MIPA/VIII/2026/0001', $receipt->nomor_dokumen);
        Storage::disk('local')->assertExists($receipt->path_file);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Generate Bukti Potongan']);
    }

    public function test_cannot_generate_receipt_for_draft_period(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create(['status' => SalaryPeriod::STATUS_DRAFT]);
        $record = $this->buatDeductionRecord($period);

        $this->expectException(\RuntimeException::class);
        app(DeductionReceiptService::class)->generate($record, $operator);
    }

    public function test_document_numbers_are_sequential_within_period(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create(['bulan' => 3, 'tahun' => 2026]);
        $recordA = $this->buatDeductionRecord($period);
        $recordB = $this->buatDeductionRecord($period);

        $receiptA = app(DeductionReceiptService::class)->generate($recordA, $operator);
        $receiptB = app(DeductionReceiptService::class)->generate($recordB, $operator);

        $this->assertSame('POTONGAN/MIPA/III/2026/0001', $receiptA->nomor_dokumen);
        $this->assertSame('POTONGAN/MIPA/III/2026/0002', $receiptB->nomor_dokumen);
    }

    public function test_generate_batch_skips_records_that_already_have_receipt(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $this->buatDeductionRecord($period);
        $this->buatDeductionRecord($period);
        $this->buatDeductionRecord($period);

        $service = app(DeductionReceiptService::class);
        $dibuat1 = $service->generateBatch($period, $operator, batchSize: 2);
        $dibuat2 = $service->generateBatch($period, $operator, batchSize: 2);
        $dibuat3 = $service->generateBatch($period, $operator, batchSize: 2);

        $this->assertSame(2, $dibuat1);
        $this->assertSame(1, $dibuat2);
        $this->assertSame(0, $dibuat3);
        $this->assertSame(3, \App\Models\DeductionReceipt::count());
    }

    public function test_verifikator_cannot_access_receipt_management_page(): void
    {
        $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->final()->create();

        $this->get(route('deduction-receipts.index', $period))->assertForbidden();
    }

    public function test_employee_can_preview_own_receipt_but_not_others(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        $recordA = $this->buatDeductionRecord($period, $employeeA);

        $receipt = app(DeductionReceiptService::class)->generate($recordA, $operator);

        $userA = User::factory()->create(['employee_id' => $employeeA->id]);
        $userA->assignRole('pegawai');
        $userB = User::factory()->create(['employee_id' => $employeeB->id]);
        $userB->assignRole('pegawai');

        $this->actingAs($userA)->get(route('deduction-receipts.preview', $receipt))->assertOk();
        $this->actingAs($userB)->get(route('deduction-receipts.preview', $receipt))->assertForbidden();
    }

    public function test_employee_sees_own_receipts_on_mine_page(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $employee = Employee::factory()->create();
        $record = $this->buatDeductionRecord($period, $employee);
        app(DeductionReceiptService::class)->generate($record, $operator);

        $user = User::factory()->create(['employee_id' => $employee->id]);
        $user->assignRole('pegawai');
        $this->actingAs($user);

        Volt::test('pages.deduction-receipts.mine')->assertSee($period->nama_periode);
    }
}
