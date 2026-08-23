<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nonaktifkan akun (bukan hapus) — konsisten dengan pola di seluruh sistem
 * (Employee, Unit, Golongan, dst semua pakai status_aktif, bukan hard
 * delete) dan karena banyak tabel punya FK restrictOnDelete ke users
 * (diupload_oleh, dibuat_oleh, dst) yang bikin hard delete user lama
 * mustahil begitu dia pernah beraktivitas di sistem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('status_aktif')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status_aktif');
        });
    }
};
