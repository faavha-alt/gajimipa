<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Pengaturan sistem (CLAUDE.md §9 modul 17) — key/value di `system_settings`.
 * Cakupan: kop surat & prefix nomor dokumen, plus konfigurasi SMTP email
 * (STEP 17) yang bisa diset dari aplikasi (Pengaturan) tanpa ubah kode/.env.
 * Key yang belum pernah disimpan jatuh ke DEFAULTS.
 */
class Settings
{
    private const DEFAULTS = [
        'nama_universitas' => 'UNIVERSITAS SEBELAS MARET',
        'nama_fakultas' => 'FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM',
        'alamat_fakultas' => '',
        'prefix_nomor_slip' => 'SLIP/MIPA',
        'prefix_nomor_potongan' => 'POTONGAN/MIPA',
        // SMTP email (STEP 17) — diatur lewat halaman Pengaturan.
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'mail_from_address' => '',
        'mail_from_name' => '',
    ];

    public static function get(string $key): string
    {
        return self::all()[$key] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $stored = SystemSetting::whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key');

        $values = collect(self::DEFAULTS)
            ->map(fn ($default, $key) => $stored[$key] ?? $default)
            ->all();

        // Password SMTP disimpan terenkripsi; hanya dipakai internal (kirim/uji).
        if (! empty($values['smtp_password'])) {
            try {
                $values['smtp_password'] = Crypt::decryptString($values['smtp_password']);
            } catch (\Throwable) {
                $values['smtp_password'] = '';
            }
        }

        return $values;
    }

    public static function smtpAktif(): bool
    {
        return self::get('smtp_enabled') === '1';
    }

    /**
     * Terapkan konfigurasi SMTP dari pengaturan aplikasi ke runtime config
     * mail (tidak menyentuh .env). Dipanggil sebelum kirim/uji email.
     *
     * @param  array<string, string>|null  $form  nilai form saat uji koneksi (belum disimpan)
     */
    public static function applyMailConfig(?array $form = null): void
    {
        $s = $form ?? self::all();

        if (($form === null && ! self::smtpAktif()) || (($s['smtp_enabled'] ?? '0') !== '1')) {
            return;
        }

        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', $s['smtp_host'] ?? '');
        config()->set('mail.mailers.smtp.port', (int) ($s['smtp_port'] ?? 587));
        config()->set('mail.mailers.smtp.username', $s['smtp_username'] ?? '');
        config()->set('mail.mailers.smtp.password', $s['smtp_password'] ?? '');
        config()->set('mail.mailers.smtp.encryption', ($s['smtp_encryption'] ?? 'tls') ?: null);
        config()->set('mail.from.address', ($s['mail_from_address'] ?? '') ?: config('mail.from.address'));
        config()->set('mail.from.name', ($s['mail_from_name'] ?? '') ?: config('mail.from.name'));
    }
}
