<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\Salary\SalaryPeriodService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SalaryPeriodManagementTest extends TestCase
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

    public function test_operator_can_create_period(): void
    {
        $this->actingAsRole('operator_gaji');

        Volt::test('pages.salary-periods.index')
            ->call('openCreate')
            ->set('bulan', '8')
            ->set('tahun', '2026')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('salary_periods', [
            'nama_periode' => 'Agustus 2026',
            'bulan' => 8,
            'tahun' => 2026,
            'status' => 'DRAFT',
            'versi' => 1,
        ]);
    }

    public function test_cannot_create_duplicate_period(): void
    {
        $this->actingAsRole('operator_gaji');
        SalaryPeriod::factory()->create(['bulan' => 8, 'tahun' => 2026, 'versi' => 1]);

        Volt::test('pages.salary-periods.index')
            ->call('openCreate')
            ->set('bulan', '8')
            ->set('tahun', '2026')
            ->call('save')
            ->assertHasErrors('bulan');
    }

    public function test_pegawai_cannot_access_periods_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('salary-periods.index'))->assertForbidden();
    }

    public function test_full_lifecycle_draft_to_final_to_archive(): void
    {
        $verifikator = $this->actingAsRole('verifikator');
        $operator = User::factory()->create();
        $operator->assignRole('operator_gaji');

        $period = SalaryPeriod::factory()->create(['status' => SalaryPeriod::STATUS_DRAFT]);
        $employee = Employee::factory()->create();
        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        $this->actingAs($operator);
        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('submitVerifikasi')
            ->assertHasNoErrors();

        $period->refresh();
        $this->assertSame(SalaryPeriod::STATUS_VERIFIKASI, $period->status);
        $this->assertSame($operator->id, $period->locked_by_user_id);

        $this->actingAs($verifikator);
        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('finalisasi');

        $period->refresh();
        $this->assertSame(SalaryPeriod::STATUS_FINAL, $period->status);
        $this->assertNull($period->locked_by_user_id);

        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('arsipkan');

        $this->assertSame(SalaryPeriod::STATUS_ARSIP, $period->fresh()->status);
    }

    public function test_verifikator_can_return_period_to_draft_with_reason(): void
    {
        $verifikator = $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->verifikasi()->create();

        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('openKembalikan')
            ->set('alasanKembali', 'Ada NIP yang belum sesuai.')
            ->call('kembalikanKeDraft')
            ->assertHasNoErrors();

        $period->refresh();
        $this->assertSame(SalaryPeriod::STATUS_DRAFT, $period->status);
        $this->assertNull($period->locked_by_user_id);
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Kembalikan ke Draft']);
    }

    public function test_kembalikan_requires_reason(): void
    {
        $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->verifikasi()->create();

        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('openKembalikan')
            ->set('alasanKembali', '')
            ->call('kembalikanKeDraft')
            ->assertHasErrors('alasanKembali');

        $this->assertSame(SalaryPeriod::STATUS_VERIFIKASI, $period->fresh()->status);
    }

    public function test_operator_cannot_finalize_period(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->verifikasi()->create();

        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('finalisasi')
            ->assertStatus(403);

        $this->assertSame(SalaryPeriod::STATUS_VERIFIKASI, $period->fresh()->status);
    }

    public function test_cannot_finalize_draft_period_directly(): void
    {
        $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->create(['status' => SalaryPeriod::STATUS_DRAFT]);

        Volt::test('pages.salary-periods.show', ['period' => $period])->call('finalisasi');

        $this->assertSame(SalaryPeriod::STATUS_DRAFT, $period->fresh()->status);
    }

    public function test_ajukan_revisi_creates_new_version_and_marks_old_superseded(): void
    {
        $this->actingAsRole('verifikator');
        $final = SalaryPeriod::factory()->final()->create(['versi' => 1]);

        $versiBaru = app(SalaryPeriodService::class)->ajukanRevisi($final, auth()->user(), 'Koreksi tunjangan.');

        $this->assertSame(2, $versiBaru->versi);
        $this->assertSame($final->id, $versiBaru->periode_asal_id);
        $this->assertSame(SalaryPeriod::STATUS_VERIFIKASI, $versiBaru->status);
        $this->assertTrue($final->fresh()->status_supersede);
    }

    public function test_finalisasi_is_atomic_when_called_twice(): void
    {
        $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->verifikasi()->create();
        $employee = Employee::factory()->create();
        SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);

        app(SalaryPeriodService::class)->finalisasi($period, auth()->user());

        $this->expectException(\RuntimeException::class);
        app(SalaryPeriodService::class)->finalisasi($period, auth()->user());
    }

    public function test_operator_can_delete_draft_period_with_all_related_data(): void
    {
        $operator = $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->create(['status' => SalaryPeriod::STATUS_DRAFT]);
        $employee = Employee::factory()->create();
        $salaryRecord = SalaryRecord::create([
            'salary_period_id' => $period->id,
            'employee_id' => $employee->id,
            'nip_snapshot' => $employee->nip,
            'nama_snapshot' => $employee->nama,
        ]);
        SalaryComponent::create([
            'salary_record_id' => $salaryRecord->id,
            'kategori' => SalaryComponent::KATEGORI_PENGHASILAN,
            'kode_komponen' => 'gjpokok',
            'nama_komponen' => 'Gaji Pokok',
            'nominal' => 5000000,
        ]);
        $type = DeductionType::factory()->create();
        DeductionRecord::create([
            'salary_record_id' => $salaryRecord->id,
            'deduction_type_id' => $type->id,
            'nominal' => 50000,
            'sumber' => DeductionRecord::SUMBER_MANUAL,
            'dibuat_oleh' => $operator->id,
        ]);

        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('hapusPeriode')
            ->assertRedirect(route('salary-periods.index'));

        $this->assertDatabaseMissing('salary_periods', ['id' => $period->id]);
        $this->assertDatabaseMissing('salary_records', ['id' => $salaryRecord->id]);
        $this->assertSame(0, SalaryComponent::count());
        $this->assertSame(0, DeductionRecord::count());
        $this->assertDatabaseHas('audit_logs', ['aktivitas' => 'Hapus Periode']);
    }

    public function test_cannot_delete_non_draft_period(): void
    {
        $this->actingAsRole('operator_gaji');
        $period = SalaryPeriod::factory()->verifikasi()->create();

        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('hapusPeriode');

        $this->assertDatabaseHas('salary_periods', ['id' => $period->id]);
    }

    public function test_verifikator_cannot_delete_period(): void
    {
        $this->actingAsRole('verifikator');
        $period = SalaryPeriod::factory()->create(['status' => SalaryPeriod::STATUS_DRAFT]);

        Volt::test('pages.salary-periods.show', ['period' => $period])
            ->call('hapusPeriode')
            ->assertStatus(403);

        $this->assertDatabaseHas('salary_periods', ['id' => $period->id]);
    }

    public function test_deleting_revision_draft_restores_origin_period(): void
    {
        $this->actingAsRole('verifikator');
        $final = SalaryPeriod::factory()->final()->create(['versi' => 1]);
        $versiBaru = app(SalaryPeriodService::class)->ajukanRevisi($final, auth()->user(), 'Koreksi tunjangan.');
        $this->assertTrue($final->fresh()->status_supersede);

        // Batalkan revisi dgn kembalikan ke draft dulu (revisi baru start di VERIFIKASI, hapus hanya utk DRAFT).
        app(SalaryPeriodService::class)->kembalikanKeDraft($versiBaru, auth()->user(), 'Batal revisi.');

        $operator = User::factory()->create();
        $operator->assignRole('operator_gaji');
        $this->actingAs($operator);

        Volt::test('pages.salary-periods.show', ['period' => $versiBaru])
            ->call('hapusPeriode')
            ->assertRedirect(route('salary-periods.index'));

        $this->assertDatabaseMissing('salary_periods', ['id' => $versiBaru->id]);
        $this->assertFalse($final->fresh()->status_supersede);
    }
}
