<?php

namespace Tests\Feature;

use App\Models\DeductionRecord;
use App\Models\DeductionType;
use App\Models\EmailLog;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\SalaryPeriod;
use App\Models\SalaryRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Email\EmailService;
use App\Services\Payslip\PayslipService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailLogPageTest extends TestCase
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

    private function buatSlip(SalaryPeriod $period, ?Employee $employee = null): \App\Models\Payslip
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

        return app(PayslipService::class)->generate($record, User::factory()->create());
    }

    private function aktifkanSmtp(): void
    {
        SystemSetting::updateOrCreate(['key' => 'smtp_enabled'], ['value' => '1']);
        SystemSetting::updateOrCreate(['key' => 'smtp_host'], ['value' => 'smtp.example.com']);
        SystemSetting::updateOrCreate(['key' => 'smtp_port'], ['value' => '587']);
    }

    public function test_super_admin_can_view_email_log_page(): void
    {
        $this->actingAsRole('super_admin');

        $this->get(route('email-logs.index'))
            ->assertOk()
            ->assertSeeVolt('pages.email-logs.index');
    }

    public function test_pegawai_cannot_access_email_log_page(): void
    {
        $this->actingAsRole('pegawai');

        $this->get(route('email-logs.index'))
            ->assertForbidden();
    }

    public function test_page_menampilkan_log_dengan_status(): void
    {
        Mail::fake();
        $this->aktifkanSmtp();
        $this->actingAsRole('super_admin');

        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $employee = Employee::factory()->create(['nama' => 'Budi Santoso']);
        $payslip = $this->buatSlip($period, $employee);

        app(EmailService::class)->kirimSatu($payslip, User::factory()->create());

        Volt::test('pages.email-logs.index')
            ->assertSee('Budi Santoso')
            ->assertSee('Terkirim')
            ->assertSee($period->nama_periode);
    }

    public function test_filter_status_menyempitkan_daftar(): void
    {
        Mail::fake();
        $this->aktifkanSmtp();
        $this->actingAsRole('super_admin');

        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);

        // Terkirim
        $payslipA = $this->buatSlip($period, Employee::factory()->create(['nama' => 'Andi Terkirim']));
        app(EmailService::class)->kirimSatu($payslipA, User::factory()->create());

        // Gagal (tanpa email)
        $payslipB = $this->buatSlip($period, Employee::factory()->create(['nama' => 'Cici Gagal', 'email' => null]));
        app(EmailService::class)->kirimSatu($payslipB, User::factory()->create());

        Volt::test('pages.email-logs.index')
            ->set('filterStatus', EmailLog::STATUS_GAGAL)
            ->assertSee('Cici Gagal')
            ->assertDontSee('Andi Terkirim');
    }

    public function test_kirim_ulang_dari_halaman_mencatat_dikirim_ulang(): void
    {
        Mail::fake();
        $this->aktifkanSmtp();
        $operator = $this->actingAsRole('super_admin');

        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $payslip = $this->buatSlip($period, Employee::factory()->create());

        // Simulasi percobaan pertama gagal.
        EmailLog::create([
            'payslip_id' => $payslip->id,
            'email_tujuan' => 'x@example.com',
            'status' => EmailLog::STATUS_GAGAL,
            'pesan_error' => 'SMTP timeout',
            'dibuat_oleh' => $operator->id,
        ]);

        Volt::test('pages.email-logs.index')
            ->call('kirimUlang', $payslip->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('email_logs', [
            'payslip_id' => $payslip->id,
            'status' => EmailLog::STATUS_DIKIRIM_ULANG,
        ]);
    }
}
