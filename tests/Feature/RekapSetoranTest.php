<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\SubmissionRecord;
use App\Models\User;
use App\Services\Report\RekapSetoranService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RekapSetoranTest extends TestCase
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

    private function buatDeductionRecord(SalaryPeriod $period, DeductionType $type, float $nominal, ?Bank $bank = null, ?float $totalPotonganFakultas = null): DeductionRecord
    {
        $employee = Employee::factory()->create(['bank_id' => $bank?->id]);

        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'total_potongan_fakultas' => $totalPotonganFakultas ?? $nominal,
        ]);

        return DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => $nominal,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => User::factory()->create()->id,
        ]);
    }

    public function test_per_jenis_potongan_sums_correctly_across_employees(): void
    {
        $period = SalaryPeriod::factory()->final()->create();
        $koperasi = DeductionType::factory()->create(['nama' => 'Koperasi']);
        $bpjs = DeductionType::factory()->create(['nama' => 'BPJS']);

        $this->buatDeductionRecord($period, $koperasi, 50000);
        $this->buatDeductionRecord($period, $koperasi, 75000);
        $this->buatDeductionRecord($period, $bpjs, 30000);

        $rekap = app(RekapSetoranService::class)->perJenisPotongan($period)->keyBy('nama');

        $this->assertSame(2, $rekap['Koperasi']['jumlah_pegawai']);
        $this->assertEquals(125000.0, $rekap['Koperasi']['total_nominal']);
        $this->assertSame(1, $rekap['BPJS']['jumlah_pegawai']);
        $this->assertEquals(30000.0, $rekap['BPJS']['total_nominal']);
    }

    public function test_per_bank_groups_employees_and_flags_missing_bank(): void
    {
        $period = SalaryPeriod::factory()->final()->create();
        $type = DeductionType::factory()->create();
        $bri = Bank::factory()->create(['nama' => 'Bank Rakyat Indonesia']);

        $this->buatDeductionRecord($period, $type, 50000, $bri);
        $this->buatDeductionRecord($period, $type, 20000, null); // belum ada bank

        $perBank = app(RekapSetoranService::class)->perBank($period);

        $this->assertCount(1, $perBank['Bank Rakyat Indonesia']);
        $this->assertCount(1, $perBank['Belum Ada Bank']);
        $this->assertEquals(50000.0, $perBank['Bank Rakyat Indonesia'][0]['total']);
    }

    public function test_operator_can_generate_and_it_persists_submission_records(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $type = DeductionType::factory()->create(['nama' => 'Koperasi']);
        $this->buatDeductionRecord($period, $type, 50000);

        app(RekapSetoranService::class)->generate($period, $operator);

        $this->assertDatabaseHas('submission_records', [
            'salary_period_id' => $period->id,
            'deduction_type_id' => $type->id,
            'jumlah_pegawai' => 1,
            'total_nominal' => 50000,
        ]);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Generate Rekap Setoran']);
    }

    public function test_regenerate_replaces_old_submission_records_not_duplicates(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $type = DeductionType::factory()->create();
        $this->buatDeductionRecord($period, $type, 50000);

        app(RekapSetoranService::class)->generate($period, $operator);
        app(RekapSetoranService::class)->generate($period, $operator);

        $this->assertSame(1, SubmissionRecord::where('salary_period_id', $period->id)->count());
    }

    public function test_cannot_generate_for_draft_period(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create(['status' => SalaryPeriod::STATUS_DRAFT]);

        $this->expectException(\RuntimeException::class);
        app(RekapSetoranService::class)->generate($period, $operator);
    }

    public function test_verifikator_can_view_but_not_generate(): void
    {
        $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->final()->create();

        $this->get(route('rekap-setoran.index', $period))->assertOk();

        Volt::test('pages.rekap-setoran.index', ['period' => $period])
            ->call('generate')
            ->assertStatus(403);
    }

    public function test_pegawai_cannot_access_rekap_page(): void
    {
        $this->actingAsRole('pegawai');
        $period = SalaryPeriod::factory()->final()->create();

        $this->get(route('rekap-setoran.index', $period))->assertForbidden();
    }

    public function test_pdf_and_excel_routes_are_accessible_to_operator(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create();
        $type = DeductionType::factory()->create();
        $this->buatDeductionRecord($period, $type, 50000);

        $this->get(route('rekap-setoran.jenis-pdf', $period))->assertOk();
        $this->get(route('rekap-setoran.jenis-excel', $period))->assertOk();
        $this->get(route('rekap-setoran.bank-pdf', $period))->assertOk();
        $this->get(route('rekap-setoran.bank-excel', $period))->assertOk();
    }
}
