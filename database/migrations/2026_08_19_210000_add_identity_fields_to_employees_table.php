<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan field identitas & kontak pegawai yang sebelumnya sengaja
     * tidak dimasukkan ke skema STEP 4 (keputusan A3 di docs/keputusan-desain.md
     * — rekening/NPWP dianggap di luar cakupan §6 karena sistem ini bukan
     * payroll banking). Keputusan itu di-override atas permintaan eksplisit
     * user 2026-08-19 — lihat pembaruan di docs/keputusan-desain.md.
     *
     * `npwp` dan `no_rekening` tetap data sensitif: hanya ditampilkan di form
     * Master Pegawai untuk role dengan permission `employees.manage`
     * (Operator & Super Admin), tidak pernah muncul di tabel daftar pegawai.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('nip');
            $table->string('id_simpeg', 30)->nullable()->unique()->after('kode_npp_fakultas');
            $table->string('no_hp', 20)->nullable()->after('email');
            $table->string('npwp', 25)->nullable()->unique()->after('id_simpeg');
            $table->string('no_rekening', 30)->nullable()->after('npwp');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['nik', 'id_simpeg', 'no_hp', 'npwp', 'no_rekening']);
        });
    }
};
