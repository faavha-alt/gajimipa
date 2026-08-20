<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Bank — mengikuti pola Master Golongan/Jabatan Fungsional (§11
 * CLAUDE.md, bukan free text/hardcode). Dibutuhkan supaya rekap potongan
 * per-bank (untuk dikirim ke bank, proses "tarik tunai" di luar aplikasi —
 * §20 CLAUDE.md, sistem cuma bikin rekap, bukan transfer) nanti akurat:
 * nama bank bebas teks ("BRI" vs "Bank BRI" vs "BANK RAKYAT INDONESIA")
 * bisa kepecah jadi grup terpisah padahal bank yang sama. File gaji pusat
 * asli (docs/excel-gaji-pusat.md) sudah punya kode+nama bank sendiri
 * (kdbankspan/nmbankspan) — kode di sini kompatibel dengan pola itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
