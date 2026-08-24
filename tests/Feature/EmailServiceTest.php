<?php

namespace Tests\Feature;

use App\Mail\PayslipEmail;
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
use App\Support\Settings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * STEP 17 — Notifikasi Email slip gaji (CLAUDE.md §22): SMTP dari pengaturan
 * aplikasi, pengiriman, dan pencatatan email_logs.
 */
class EmailServiceTest extends TestCase
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

    private function aktifkanSmtp(): void
    {
        SystemSetting::updateOrCreate(['key' => 'smtp_enabled'], ['value' => '1']);
        SystemSetting::updateOrCreate(['key' => 'smtp_host'], ['value' => 'smtp.example.com']);
        SystemSetting::updateOrCreate(['key' => 'smtp_port'], ['value' => '587']);
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

    private function buatSlip(SalaryPeriod $period, ?Employee $employee = null): \App\Models\Payslip
    {
        $record = $this->buatSalaryRecord($period, $employee);

        return app(PayslipService::class)->generate($record, User::factory()->create());
    }

    public function test_bisa_kirim_membutuhkan_smtp_aktif_dan_periode_final(): void
    {
        $draft = SalaryPeriod::factory()->create();
        $final = SalaryPeriod::factory()->final()->create(['bulan' => 7, 'tahun' => 2026]);

        $service = app(EmailService::class);

        // SMTP belum diaktifkan.
        $this->assertFalse($service->bisaKirim($draft));
        $this->assertFalse($service->bisaKirim($final));

        $this->aktifkanSmtp();

        // Periode masih DRAFT tetap tidak bisa.
        $this->assertFalse($service->bisaKirim($draft));
        $this->assertTrue($service->bisaKirim($final));
    }

    public function test_kirim_satu_mengirim_email_dan_mencatat_log_terkirim(): void
    {
        Mail::fake();
        $this->aktifkanSmtp();

        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $payslip = $this->buatSlip($period);

        app(EmailService::class)->kirimSatu($payslip, User::factory()->create());

        Mail::assertSent(PayslipEmail::class);
        $this->assertDatabaseHas('email_logs', [
            'payslip_id' => $payslip->id,
            'status' => EmailLog::STATUS_TERKIRIM,
        ]);
    }

    public function test_kirim_tanpa_email_pegawai_mencatat_gagal(): void
    {
        Mail::fake();
        $this->aktifkanSmtp();

        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $employee = Employee::factory()->create(['email' => null]);
        $payslip = $this->buatSlip($period, $employee);

        app(EmailService::class)->kirimSatu($payslip, User::factory()->create());

        Mail::assertNothingSent();
        $this->assertDatabaseHas('email_logs', [
            'payslip_id' => $payslip->id,
            'status' => EmailLog::STATUS_GAGAL,
        ]);
    }

    public function test_kirim_ulang_setelah_gagal_mencatat_dikirim_ulang(): void
    {
        Mail::fake();
        $this->aktifkanSmtp();

        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $payslip = $this->buatSlip($period);

        EmailLog::create([
            'payslip_id' => $payslip->id,
            'email_tujuan' => 'x@example.com',
            'status' => EmailLog::STATUS_GAGAL,
            'pesan_error' => 'SMTP timeout',
        ]);

        app(EmailService::class)->kirimSatu($payslip, User::factory()->create());

        $this->assertDatabaseHas('email_logs', [
            'payslip_id' => $payslip->id,
            'status' => EmailLog::STATUS_DIKIRIM_ULANG,
        ]);
    }

    public function test_kirim_batch_mengirim_semua_slip_dan_sisa_menjadi_nol(): void
    {
        Mail::fake();
        $this->aktifkanSmtp();

        $period = SalaryPeriod::factory()->final()->create(['bulan' => 8, 'tahun' => 2026]);
        $this->buatSlip($period);
        $this->buatSlip($period);
        $this->buatSlip($period);

        $service = app(EmailService::class);

        $this->assertSame(3, $service->sisaKirim($period));

        $service->kirimBatch($period, User::factory()->create(), batchSize: 2);

        $this->assertSame(1, $service->sisaKirim($period));

        $service->kirimBatch($period, User::factory()->create());

        $this->assertSame(0, $service->sisaKirim($period));
        Mail::assertSent(PayslipEmail::class, 3);
    }

    public function test_settings_page_menyimpan_smtp_dan_password_terenkripsi(): void
    {
        $this->actingAsRole('super_admin');

        Volt::test('pages.settings')
            ->set('smtp_enabled', true)
            ->set('smtp_host', 'smtp.example.com')
            ->set('smtp_port', '587')
            ->set('smtp_username', 'user@example.com')
            ->set('smtp_password', 'rahasia123')
            ->set('smtp_encryption', 'tls')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('system_settings', ['key' => 'smtp_enabled', 'value' => '1']);

        // Password tersimpan terenkripsi, tapi terbaca benar oleh helper.
        $stored = SystemSetting::where('key', 'smtp_password')->value('value');
        $this->assertNotSame('rahasia123', $stored);
        $this->assertSame('rahasia123', Settings::get('smtp_password'));
    }

    public function test_settings_test_smtp_mengirim_email_uji(): void
    {
        Mail::fake();
        $this->actingAsRole('super_admin');

        Volt::test('pages.settings')
            ->set('smtp_host', 'smtp.example.com')
            ->set('smtp_port', '587')
            ->call('testSmtp')
            ->assertHasNoErrors()
            ->assertSee('Email uji terkirim');
    }
}
