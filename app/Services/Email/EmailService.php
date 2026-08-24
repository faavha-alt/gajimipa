<?php

namespace App\Services\Email;

use App\Mail\PayslipEmail;
use App\Models\EmailLog;
use App\Models\Payslip;
use App\Models\SalaryPeriod;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Notifikasi email slip gaji (CLAUDE.md §22 / STEP 17).
 *
 * Konfigurasi SMTP diambil dari pengaturan aplikasi (Pengaturan → SMTP Email),
 * diterapkan ke runtime config via Settings::applyMailConfig() — tanpa ubah .env.
 * Status tiap pengiriman dicatat di `email_logs`.
 */
class EmailService
{
    public function bisaKirim(SalaryPeriod $period): bool
    {
        return Settings::smtpAktif()
            && in_array($period->status, [SalaryPeriod::STATUS_FINAL, SalaryPeriod::STATUS_ARSIP]);
    }

    /**
     * Kirim satu slip ke email pegawai; catat EmailLog TERKIRIM/GAGAL.
     * Jika sebelumnya pernah gagal, sukses berikutnya dicatat DIKIRIM_ULANG.
     */
    public function kirimSatu(Payslip $payslip, User $operator): void
    {
        Settings::applyMailConfig();

        $email = $payslip->salaryRecord?->employee?->email;

        if (! $email) {
            EmailLog::create([
                'payslip_id' => $payslip->id,
                'email_tujuan' => '',
                'status' => EmailLog::STATUS_GAGAL,
                'pesan_error' => 'Pegawai tidak memiliki alamat email di Master Pegawai.',
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new PayslipEmail($payslip));

            EmailLog::create([
                'payslip_id' => $payslip->id,
                'email_tujuan' => $email,
                'status' => $this->pernahGagal($payslip) ? EmailLog::STATUS_DIKIRIM_ULANG : EmailLog::STATUS_TERKIRIM,
                'dikirim_pada' => now(),
            ]);
        } catch (\Throwable $e) {
            EmailLog::create([
                'payslip_id' => $payslip->id,
                'email_tujuan' => $email,
                'status' => EmailLog::STATUS_GAGAL,
                'pesan_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Kirim massal bertahap (sinkron, progresif — pola sama dgn generateBatch slip).
     *
     * @return int jumlah sisa slip yang belum terkirim (0 = selesai)
     */
    public function kirimBatch(SalaryPeriod $period, User $operator, int $batchSize = 10): int
    {
        if (! $this->bisaKirim($period)) {
            return 0;
        }

        $this->eligiblePayslips($period)->take($batchSize)->each(
            fn (Payslip $payslip) => $this->kirimSatu($payslip, $operator)
        );

        return $this->eligiblePayslips($period)->count();
    }

    public function sisaKirim(SalaryPeriod $period): int
    {
        return $this->eligiblePayslips($period)->count();
    }

    /** Slip yang belum pernah terkirim atau pernah gagal (bisa dikirim/dikirim ulang). */
    private function eligiblePayslips(SalaryPeriod $period): Collection
    {
        return $period->salaryRecords()
            ->with(['payslips' => fn ($q) => $q->with('emailLogs')->latest()])
            ->get()
            ->pluck('payslips')
            ->flatten()
            ->filter(fn (Payslip $payslip) => $this->belumTerkirim($payslip));
    }

    private function belumTerkirim(Payslip $payslip): bool
    {
        $latest = $payslip->emailLogs->sortByDesc('id')->first();

        return ! $latest || in_array($latest->status, [EmailLog::STATUS_BELUM_DIKIRIM, EmailLog::STATUS_GAGAL]);
    }

    private function pernahGagal(Payslip $payslip): bool
    {
        return $payslip->emailLogs()->whereIn('status', [EmailLog::STATUS_GAGAL, EmailLog::STATUS_DIKIRIM_ULANG])->exists();
    }
}
