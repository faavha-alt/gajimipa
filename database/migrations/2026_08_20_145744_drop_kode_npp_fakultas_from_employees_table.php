<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan C1 (docs/keputusan-desain.md) yang mengasumsikan file potongan
 * fakultas cuma punya NPP internal (bukan NIP) ternyata tidak berlaku —
 * file yang sebenarnya dipakai fakultas memang punya NIP langsung. NPP
 * tidak pernah terpakai (0/224 pegawai terisi, 0 deduction_records/imports
 * ada saat migration ini dibuat) — aman dihapus total, bukan cuma
 * dikosongkan. Identifier pegawai di Import Potongan Fakultas sekarang
 * konsisten pakai NIP, sama seperti Import Gaji Pusat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['kode_npp_fakultas']);
            $table->dropColumn('kode_npp_fakultas');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('kode_npp_fakultas', 20)->unique()->nullable()->after('nik');
        });
    }
};
