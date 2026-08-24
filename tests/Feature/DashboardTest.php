<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function buatSalaryRecord(SalaryPeriod $period, Employee $employee, float $kotor, float $potonganPusat, float $potonganFakultas, float $bersih): SalaryRecord
    {
        return SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'unit_snapshot' => $employee->unit?->nama_unit,
            'total_penghasilan_kotor' => $kotor,
            'total_potongan_pusat' => $potonganPusat,
            'bersih_pusat' => $kotor - $potonganPusat,
            'total_potongan_fakultas' => $potonganFakultas,
            'gaji_bersih_final' => $bersih,
        ]);
    }

    public function test_shows_empty_state_when_no_period_exists(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum ada Periode Gaji');
    }

    public function test_staff_roles_see_aggregate_dashboard_for_latest_period(): void
    {
        $this->actingAsRole('super_admin');

        $lama = SalaryPeriod::factory()->arsip()->create(['bulan' => 7, 'tahun' => 2026]);
        $unit = Unit::factory()->create(['nama_unit' => 'Prodi Matematika']);
        $this->buatSalaryRecord($lama, Employee::factory()->create(['unit_id' => $unit->id]), 5000000, 500000, 0, 4500000);

        $aktif = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $pegawai1 = Employee::factory()->create(['unit_id' => $unit->id]);
        $pegawai2 = Employee::factory()->create(['unit_id' => $unit->id]);
        $this->buatSalaryRecord($aktif, $pegawai1, 10000000, 1000000, 500000, 8500000);
        $this->buatSalaryRecord($aktif, $pegawai2, 8000000, 800000, 200000, 7000000);

        $jenis = DeductionType::factory()->create(['nama' => 'Koperasi']);
        DeductionRecord::create([
            'salary_record_id' => SalaryRecord::where('salary_period_id', $aktif->id)->where('employee_id', $pegawai1->id)->first()->id,
            'deduction_type_id' => $jenis->id,
            'nominal' => 500000,
            'sumber' => 'MANUAL',
            'dibuat_oleh' => User::factory()->create()->id,
        ]);

        $response = Volt::test('pages.dashboard');

        $response->assertSee($aktif->nama_periode)
            ->assertSee('Draft') // status periode ditampilkan dalam Bahasa Indonesia sejak lanjutan 48
            ->assertSee('Rp 18.000.000') // total penghasilan aktif (10jt+8jt), bukan periode lama
            ->assertSee('Rp 15.500.000') // gaji bersih aktif (8.5jt+7jt)
            ->assertSee('Prodi Matematika')
            ->assertSee('Koperasi')
            ->assertSee($lama->nama_periode); // muncul di histori periode
    }

    public function test_pegawai_without_employee_link_sees_notice_not_aggregate_data(): void
    {
        $this->actingAsRole('pegawai');

        $period = SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($period, Employee::factory()->create(), 99999999, 0, 0, 99999999);

        $response = Volt::test('pages.dashboard');

        $response->assertSee('belum ditautkan ke data Master Pegawai')
            ->assertDontSee('99.999.999');
    }

    public function test_pegawai_only_sees_own_salary_not_faculty_totals(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['employee_id' => $employee->id]);
        $user->assignRole('pegawai');
        $this->actingAs($user);

        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($period, $employee, 10000000, 1000000, 0, 9000000);

        $lainOrangLain = Employee::factory()->create();
        $this->buatSalaryRecord($period, $lainOrangLain, 77777777, 0, 0, 77777777);

        $response = Volt::test('pages.dashboard');

        $response->assertSee('Rp 9.000.000')
            ->assertDontSee('77.777.777')
            ->assertDontSee('Rekap per Unit'); // widget fakultas-wide tidak boleh muncul di versi Pegawai
    }
}
