<?php

namespace Tests\Feature;

use App\Models\DeductionRate;
use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Golongan;
use App\Models\RecurringDeduction;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\Deduction\RecurringDeductionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringDeductionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function buatSalaryRecord(SalaryPeriod $period, Employee $employee): SalaryRecord
    {
        return SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'total_penghasilan_kotor' => 10000000,
            'gaji_bersih_final' => 9000000,
        ]);
    }

    public function test_mode_tetap_uses_manual_nominal_every_period(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($period, $employee);

        $rd = RecurringDeduction::factory()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'mode' => RecurringDeduction::MODE_TETAP,
            'nominal' => 150000,
            'dibuat_oleh' => $user->id,
        ]);

        $hasil = $service->terapkan($period, $user);

        $this->assertSame(1, $hasil['jumlah']);
        $this->assertDatabaseHas('deduction_records', [
            'recurring_deduction_id' => $rd->id,
            'nominal' => 150000,
            'sumber' => DeductionRecord::SUMBER_BERULANG,
        ]);
    }

    public function test_mode_angsuran_stops_automatically_after_reaching_jumlah_cicilan(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();

        $rd = RecurringDeduction::factory()->angsuran(3)->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'nominal' => 500000,
            'dibuat_oleh' => $user->id,
        ]);

        foreach ([1, 2, 3] as $bulan) {
            $period = SalaryPeriod::factory()->create(['bulan' => $bulan, 'tahun' => 2027]);
            $this->buatSalaryRecord($period, $employee);
            $service->terapkan($period, $user);
        }

        $rd->refresh();
        $this->assertSame(3, $rd->cicilan_ke);
        $this->assertSame(RecurringDeduction::STATUS_LUNAS, $rd->status);
        $this->assertSame(3, DeductionRecord::where('recurring_deduction_id', $rd->id)->count());

        // Periode ke-4: sudah LUNAS, tidak lagi ikut diterapkan.
        $periodeKe4 = SalaryPeriod::factory()->create(['bulan' => 4, 'tahun' => 2027]);
        $this->buatSalaryRecord($periodeKe4, $employee);
        $hasil = $service->terapkan($periodeKe4, $user);

        $this->assertSame(0, $hasil['jumlah']);
    }

    public function test_mode_tarif_golongan_looks_up_nominal_from_rate_table(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $golongan = Golongan::factory()->create();
        $employee = Employee::factory()->create(['golongan_id' => $golongan->id]);
        $type = DeductionType::factory()->create();

        DeductionRate::factory()->create([
            'deduction_type_id' => $type->id,
            'golongan_id' => $golongan->id,
            'nominal' => 75000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $rd = RecurringDeduction::factory()->tarifGolongan()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'dibuat_oleh' => $user->id,
        ]);

        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($period, $employee);

        $service->terapkan($period, $user);

        $this->assertDatabaseHas('deduction_records', [
            'recurring_deduction_id' => $rd->id,
            'nominal' => 75000,
        ]);
    }

    public function test_tarif_history_picks_the_rate_effective_at_period_date_not_the_latest(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $golongan = Golongan::factory()->create();
        $employee = Employee::factory()->create(['golongan_id' => $golongan->id]);
        $type = DeductionType::factory()->create();

        DeductionRate::factory()->create(['deduction_type_id' => $type->id, 'golongan_id' => $golongan->id, 'nominal' => 50000, 'berlaku_mulai' => '2026-01-01']);
        DeductionRate::factory()->create(['deduction_type_id' => $type->id, 'golongan_id' => $golongan->id, 'nominal' => 60000, 'berlaku_mulai' => '2027-01-01']);

        RecurringDeduction::factory()->tarifGolongan()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'dibuat_oleh' => $user->id,
        ]);

        // Periode Agustus 2026 — tarif yg berlaku saat itu masih 50.000 (tarif 60.000 baru mulai Jan 2027).
        $periodeLama = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($periodeLama, $employee);
        $service->terapkan($periodeLama, $user);

        $this->assertDatabaseHas('deduction_records', ['nominal' => 50000]);
        $this->assertDatabaseMissing('deduction_records', ['nominal' => 60000]);
    }

    public function test_mode_tarif_status_pegawai_looks_up_nominal_from_rate_table(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $status = EmployeeStatus::factory()->create();
        $employee = Employee::factory()->create(['employee_status_id' => $status->id]);
        $type = DeductionType::factory()->create();

        DeductionRate::factory()->create([
            'deduction_type_id' => $type->id,
            'employee_status_id' => $status->id,
            'nominal' => 25000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        RecurringDeduction::factory()->tarifStatusPegawai()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'dibuat_oleh' => $user->id,
        ]);

        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($period, $employee);

        $service->terapkan($period, $user);

        $this->assertDatabaseHas('deduction_records', ['nominal' => 25000]);
    }

    public function test_skipped_when_rate_not_configured(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $golongan = Golongan::factory()->create();
        $employee = Employee::factory()->create(['golongan_id' => $golongan->id]);
        $type = DeductionType::factory()->create();

        RecurringDeduction::factory()->tarifGolongan()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'dibuat_oleh' => $user->id,
        ]);

        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($period, $employee);

        $preview = $service->preview($period);
        $this->assertFalse($preview->first()['bisa_diterapkan']);
        $this->assertSame('Tarif belum diatur', $preview->first()['alasan_dilewati']);

        $hasil = $service->terapkan($period, $user);
        $this->assertSame(0, $hasil['jumlah']);
    }

    public function test_skipped_when_employee_has_no_salary_record_this_period(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();

        RecurringDeduction::factory()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'nominal' => 100000,
            'dibuat_oleh' => $user->id,
        ]);

        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        // Sengaja tidak buat SalaryRecord utk pegawai ini.

        $preview = $service->preview($period);
        $this->assertFalse($preview->first()['bisa_diterapkan']);
        $this->assertSame('Belum ada data gaji pusat periode ini', $preview->first()['alasan_dilewati']);
    }

    public function test_terapkan_is_idempotent_running_twice_does_not_duplicate(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($period, $employee);

        RecurringDeduction::factory()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'nominal' => 100000,
            'dibuat_oleh' => $user->id,
        ]);

        $service->terapkan($period, $user);
        $hasilKedua = $service->terapkan($period, $user);

        $this->assertSame(0, $hasilKedua['jumlah']);
        $this->assertSame(1, DeductionRecord::count());
    }

    public function test_dihentikan_status_is_never_applied(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $type = DeductionType::factory()->create();
        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($period, $employee);

        RecurringDeduction::factory()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $type->id,
            'nominal' => 100000,
            'status' => RecurringDeduction::STATUS_DIHENTIKAN,
            'dibuat_oleh' => $user->id,
        ]);

        $hasil = $service->terapkan($period, $user);
        $this->assertSame(0, $hasil['jumlah']);
    }

    public function test_cannot_terapkan_to_non_draft_period(): void
    {
        $service = app(RecurringDeductionService::class);
        $user = User::factory()->create();
        $period = SalaryPeriod::factory()->final()->create();

        $this->expectException(\RuntimeException::class);
        $service->terapkan($period, $user);
    }
}
