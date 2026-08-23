<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Pengaturan sistem (CLAUDE.md §9 modul 17) — key/value di `system_settings`.
 * Cakupan awal (STEP 18): kop surat & prefix nomor dokumen, sebelumnya
 * hardcode di 6 view PDF dan 2 Service. Key yang belum pernah disimpan
 * jatuh ke DEFAULTS supaya deploy lama (tabel kosong) tetap tampil sama
 * seperti sebelum fitur ini ada.
 */
class Settings
{
    private const DEFAULTS = [
        'nama_universitas' => 'UNIVERSITAS SEBELAS MARET',
        'nama_fakultas' => 'FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM',
        'alamat_fakultas' => '',
        'prefix_nomor_slip' => 'SLIP/MIPA',
        'prefix_nomor_potongan' => 'POTONGAN/MIPA',
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

        return collect(self::DEFAULTS)
            ->map(fn ($default, $key) => $stored[$key] ?? $default)
            ->all();
    }
}
