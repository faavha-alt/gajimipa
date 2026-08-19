<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SalaryRecordDetailTest extends TestCase
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

    /**
     * Data nyata baris "Suranto" (docs/pemetaan-field-gaji.md §3).
     */
    private function buildSalaryRecordForSuranto(): array
    {
        $period = SalaryPeriod::factory()->create();
        $employee = Employee::factory()->create(['nip' => '195708201985031004', 'nama' => 'Prof. Suranto']);

        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'unit_snapshot' => 'Prodi Fisika',
            'golongan_snapshot' => '45',
            'total_penghasilan_kotor' => 8590495,
            'total_potongan_pusat' => 765895,
            'bersih_pusat' => 7824600,
            'total_potongan_fakultas' => 94000,
            'gaji_bersih_final' => 7730600,
        ]);

        SalaryComponent::create([
            'salary_record_id' => $salaryRecord->id,
            'kategori' => SalaryComponent::KATEGORI_PENGHASILAN,
            'kode_komponen' => 'gjpokok',
            'nama_komponen' => 'Gaji Pokok',
            'nominal' => 6373200,
        ]);
        SalaryComponent::create([
            'salary_record_id' => $salaryRecord->id,
            'kategori' => SalaryComponent::KATEGORI_POTONGAN_PUSAT,
            'kode_komponen' => 'potpfk10',
            'nama_komponen' => 'Potongan PFK 10%',
            'nominal' => 560841,
        ]);

        $type = DeductionType::factory()->create(['nama' => 'Koperasi UNS - Simpanan Wajib']);
        DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => 85000,
            'sumber' => DeductionRecord::SUMBER_IMPORT,
            'dibuat_oleh' => User::factory()->create()->id,
        ]);

        return [$period, $salaryRecord];
    }

    public function test_pegawai_cannot_access_salary_record_list(): void
    {
        [$period] = $this->buildSalaryRecordForSuranto();
        $this->actingAsRole('pegawai');

        $this->get(route('salary-records.index', $period))->assertForbidden();
    }

    public function test_verifikator_can_view_list_and_detail(): void
    {
        [$period, $salaryRecord] = $this->buildSalaryRecordForSuranto();
        $this->actingAsRole('verifikator');

        $this->get(route('salary-records.index', $period))
            ->assertOk()
            ->assertSee('Prof. Suranto');

        $this->get(route('salary-records.show', [$period, $salaryRecord]))
            ->assertOk()
            ->assertSee('Gaji Pokok')
            ->assertSee('Potongan PFK 10%')
            ->assertSee('Koperasi UNS - Simpanan Wajib');
    }

    public function test_detail_page_breaks_down_income_deduction_and_final_totals(): void
    {
        [$period, $salaryRecord] = $this->buildSalaryRecordForSuranto();
        $this->actingAsRole('operator_gaji');

        Volt::test('pages.salary-records.show', ['period' => $period, 'salaryRecord' => $salaryRecord])
            ->assertSee('6.373.200') // gaji pokok
            ->assertSee('560.841')  // potongan pfk 10%
            ->assertSee('7.824.600') // bersih pusat
            ->assertSee('85.000')   // potongan fakultas
            ->assertSee('7.730.600'); // gaji bersih final
    }

    public function test_mismatched_period_and_salary_record_returns_404(): void
    {
        [, $salaryRecord] = $this->buildSalaryRecordForSuranto();
        $otherPeriod = SalaryPeriod::factory()->create();
        $this->actingAsRole('operator_gaji');

        $this->get(route('salary-records.show', [$otherPeriod, $salaryRecord]))->assertNotFound();
    }

    public function test_search_filters_salary_record_list(): void
    {
        [$period] = $this->buildSalaryRecordForSuranto();
        $other = Employee::factory()->create(['nip' => '222222222222222222', 'nama' => 'Siti Aminah']);
        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $other->id,
            'nip_snapshot' => $other->nip,
            'nama_snapshot' => $other->nama,
        ]);

        $this->actingAsRole('operator_gaji');

        Volt::test('pages.salary-records.index', ['period' => $period])
            ->set('search', 'Suranto')
            ->assertSee('Prof. Suranto')
            ->assertDontSee('Siti Aminah');
    }
}
