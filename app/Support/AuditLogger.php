<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Pencatat audit log ringan (CLAUDE.md §24). Modul Audit Log penuh (tampilan,
 * filter, dst.) baru dibangun di STEP 18 — helper ini dipakai lebih awal oleh
 * modul-modul yang aktivitasnya wajib tercatat sejak awal (mis. siklus
 * Periode Gaji §17: verifikasi, finalisasi, revisi).
 */
class AuditLogger
{
    public static function log(string $aktivitas, string $deskripsi, array $dataTerkait = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'aktivitas' => $aktivitas,
            'deskripsi' => $deskripsi,
            'data_terkait' => $dataTerkait,
            'ip_address' => Request::ip(),
        ]);
    }
}
