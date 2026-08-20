<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * jabatan_snapshot dulu diisi kdjab PNS saja (kode numerik pendek, mis.
 * "06901"), makanya VARCHAR(10) cukup. Format Non-PNS (docs/excel-gaji-
 * nonpns.md) mengisi field yang sama dengan FUNGSIONAL mentah — frasa teks
 * penuh (mis. "Tenaga Pengajar", "Tenaga Kependidikan") yang bisa melebihi
 * 10 karakter. Server pakai STRICT_TRANS_TABLES — nilai kepanjangan akan
 * ditolak MySQL (bukan dipotong diam-diam), jadi ini harus dilebarkan
 * sebelum import Non-PNS sungguhan pertama kali dijalankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_records', function (Blueprint $table) {
            $table->string('jabatan_snapshot', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('salary_records', function (Blueprint $table) {
            $table->string('jabatan_snapshot', 10)->nullable()->change();
        });
    }
};
