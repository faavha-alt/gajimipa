<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\Unit;
use App\Models\User;
use App\Services\Report\LaporanService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanTest extends TestCase
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

    private function buatSalaryRecord(SalaryPeriod $period, string $unit, float $kotor, float $bersih): SalaryRecord
    {
        $employee = Employee::factory()->create(['unit_id' => Unit::factory()->create(['nama_unit' => $unit])->id]);

        return SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
            'unit_snapshot' => $unit,
            'total_penghasilan_kotor' => $kotor,
            'gaji_bersih_final' => $bersih,
        ]);
    }

    public function test_bulanan_aggregates_per_unit_and_totals(): void
    {
        $period = SalaryPeriod::factory()->final()->create();
        $this->buatSalaryRecord($period, 'Prodi Matematika', 10000000, 9000000);
        $this->buatSalaryRecord($period, 'Prodi Matematika', 8000000, 7000000);
        $this->buatSalaryRecord($period, 'Prodi Fisika', 12000000, 11000000);

        $data = app(LaporanService::class)->bulanan($period);

        $this->assertSame(3, $data['totals']['jumlah_pegawai']);
        $this->assertEquals(30000000, $data['totals']['total_penghasilan_kotor']);
        $this->assertEquals(27000000, $data['totals']['total_gaji_bersih']);
        $this->assertSame(2, $data['perUnit']['Prodi Matematika']['jumlah_pegawai']);
        $this->assertEquals(18000000, $data['perUnit']['Prodi Matematika']['total_penghasilan_kotor']);
        $this->assertSame(1, $data['perUnit']['Prodi Fisika']['jumlah_pegawai']);
    }

    public function test_bulanan_includes_deduction_recap(): void
    {
        $period = SalaryPeriod::factory()->final()->create();
        $record = $this->buatSalaryRecord($period, 'Prodi Matematika', 10000000, 9500000);
        $type = DeductionType::factory()->create(['nama' => 'Koperasi']);
        DeductionRecord::create([
            'salary_record_id' => $record->id,
            'deduction_type_id' => $type->id,
            'nominal' => 50000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => User::factory()->create()->id,
        ]);

        $data = app(LaporanService::class)->bulanan($period);
        $rekap = $data['perJenisPotongan']->keyBy('nama');

        $this->assertEquals(50000, $rekap['Koperasi']['total_nominal']);
    }

    public function test_tahunan_only_counts_final_arsip_non_superseded_periods(): void
    {
        $periodJuli = SalaryPeriod::factory()->final()->create(['bulan' => 7, 'tahun' => 2026]);
        $this->buatSalaryRecord($periodJuli, 'Prodi Matematika', 10000000, 9000000);

        $periodAgustus = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSalaryRecord($periodAgustus, 'Prodi Matematika', 12000000, 11000000);

        // Periode DRAFT tidak boleh ikut terhitung.
        $periodSeptember = SalaryPeriod::factory()->create(['bulan' => 9, 'tahun' => 2026, 'status' => SalaryPeriod::STATUS_DRAFT]);
        $this->buatSalaryRecord($periodSeptember, 'Prodi Matematika', 99999999, 99999999);

        // Periode yang sudah digantikan (superseded) tidak boleh ikut terhitung dobel.
        $periodOktoberLama = SalaryPeriod::factory()->final()->create(['bulan' => 10, 'tahun' => 2026, 'status_supersede' => true]);
        $this->buatSalaryRecord($periodOktoberLama, 'Prodi Matematika', 5000000, 4000000);
        $periodOktoberBaru = SalaryPeriod::factory()->final()->create(['bulan' => 10, 'tahun' => 2026, 'periode_asal_id' => $periodOktoberLama->id, 'versi' => 2]);
        $this->buatSalaryRecord($periodOktoberBaru, 'Prodi Matematika', 6000000, 5500000);

        $data = app(LaporanService::class)->tahunan(2026);

        $this->assertEquals(28000000, $data['totals']['total_penghasilan_kotor']); // 10jt + 12jt + 6jt (bukan yg lama/draft)
        $this->assertCount(3, $data['perBulan']);
    }

    public function test_tahun_tersedia_returns_distinct_years_with_final_periods(): void
    {
        SalaryPeriod::factory()->final()->create(['tahun' => 2025]);
        SalaryPeriod::factory()->final()->create(['tahun' => 2026]);
        SalaryPeriod::factory()->create(['tahun' => 2027, 'status' => SalaryPeriod::STATUS_DRAFT]);

        $tahun = app(LaporanService::class)->tahunTersedia();

        $this->assertEqualsCanonicalizing([2025, 2026], $tahun);
    }

    public function test_pegawai_cannot_access_laporan(): void
    {
        $this->actingAsRole('pegawai');
        $period = SalaryPeriod::factory()->final()->create();

        $this->get(route('laporan.index'))->assertForbidden();
        $this->get(route('laporan.bulanan', $period))->assertForbidden();
        $this->get(route('laporan.tahunan', 2026))->assertForbidden();
    }

    public function test_verifikator_and_pimpinan_can_view_laporan(): void
    {
        $period = SalaryPeriod::factory()->final()->create();

        foreach (['verifikator', 'pimpinan'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('laporan.index'))->assertOk();
            $this->get(route('laporan.bulanan', $period))->assertOk();
        }
    }

    public function test_pdf_and_excel_routes_are_accessible_to_operator(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->final()->create(['tahun' => 2026]);
        $this->buatSalaryRecord($period, 'Prodi Matematika', 10000000, 9000000);

        $this->get(route('laporan.bulanan-pdf', $period))->assertOk();
        $this->get(route('laporan.bulanan-excel', $period))->assertOk();
        $this->get(route('laporan.tahunan-pdf', 2026))->assertOk();
        $this->get(route('laporan.tahunan-excel', 2026))->assertOk();
    }
}
