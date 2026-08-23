<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang dinonaktifkan (§23 CLAUDE.md — User & Hak Akses) langsung
 * di-logout begitu terdeteksi, bukan cuma dicegah login baru — supaya
 * sesi yang sudah berjalan pun ikut terhenti begitu Super Admin
 * menonaktifkan akun itu.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && ! auth()->user()->status_aktif) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun ini sudah dinonaktifkan. Hubungi Super Admin.',
            ]);
        }

        return $next($request);
    }
}
