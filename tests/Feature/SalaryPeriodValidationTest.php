<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\Salary\SalaryPeriodService;
use App\Services\Salary\SalaryPeriodValidationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryPeriodValidationTest extends TestCase
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

    public function test_empty_period_fails_checklist(): void
    {
        $period = SalaryPeriod::factory()->create();

        $this->assertFalse(app(SalaryPeriodValidationService::class)->isValid($period));
    }

    public function test_period_with_clean_salary_record_passes_checklist(): void
    {
        $period = SalaryPeriod::factory()->create();
        $employee = Employee::factory()->create(['status_aktif' => true]);
        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'bersih_pusat' => 5000000,
            'gaji_bersih_final' => 5000000,
        ]);

        $this->assertTrue(app(SalaryPeriodValidationService::class)->isValid($period));
    }

    public function test_inactive_employee_fails_checklist(): void
    {
        $period = SalaryPeriod::factory()->create();
        $employee = Employee::factory()->create(['status_aktif' => false]);
        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        $errors = app(SalaryPeriodValidationService::class)->errors($period);
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'nonaktif')));
    }

    public function test_negative_deduction_fails_checklist(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create();
        $employee = Employee::factory()->create();
        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);
        $type = DeductionType::factory()->create();

        // Nominal negatif seharusnya tidak lolos validasi form/import — dibuat
        // langsung lewat model di sini untuk mensimulasikan data yang entah
        // bagaimana lolos ke tabel, membuktikan checklist ini jadi lapis
        // pertahanan terakhir (defense in depth), bukan cuma andalkan validasi
        // form/import saja.
        DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => -5000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => $operator->id,
        ]);

        $errors = app(SalaryPeriodValidationService::class)->errors($period);
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'negatif')));
    }

    public function test_unprocessed_deduction_change_fails_checklist(): void
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
            'total_potongan_fakultas' => 0,
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

        // Belum Proses Gaji ulang setelah potongan ditambahkan.
        $errors = app(SalaryPeriodValidationService::class)->errors($period);
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'diproses ulang')));
    }

    public function test_finalisasi_is_blocked_when_checklist_fails(): void
    {
        $verifikator = $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->verifikasi()->create();
        // Tidak ada salary_records sama sekali -> checklist gagal di poin pertama.

        try {
            app(SalaryPeriodService::class)->finalisasi($period, $verifikator);
            $this->fail('Seharusnya melempar RuntimeException karena checklist §16 gagal.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('§16', $e->getMessage());
        }

        $this->assertSame(SalaryPeriod::STATUS_VERIFIKASI, $period->fresh()->status);
    }

    public function test_finalisasi_succeeds_when_checklist_passes(): void
    {
        $verifikator = $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->verifikasi()->create();
        $employee = Employee::factory()->create();
        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        app(SalaryPeriodService::class)->finalisasi($period, $verifikator);

        $this->assertSame(SalaryPeriod::STATUS_FINAL, $period->fresh()->status);
    }
}
