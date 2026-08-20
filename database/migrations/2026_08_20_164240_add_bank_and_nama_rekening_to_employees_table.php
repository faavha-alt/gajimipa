<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `no_rekening` sudah ada (migration 2026_08_19_210000). Menambahkan
 * `nama_rekening` (nama pemilik rekening — bisa beda dari nama pegawai,
 * lih. `nmrek` di docs/excel-gaji-pusat.md) dan `bank_id` (FK ke Master
 * Bank baru). Keduanya data finansial sensitif sekelompok dengan npwp/
 * no_rekening (§30 CLAUDE.md), jadi ditaruh setelahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nama_rekening', 150)->nullable()->after('no_rekening');
            $table->foreignId('bank_id')->nullable()->after('nama_rekening')->constrained('banks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn('nama_rekening');
        });
    }
};
