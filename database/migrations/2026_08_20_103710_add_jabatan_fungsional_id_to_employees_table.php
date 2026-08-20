<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Jabatan Fungsional (mengikuti pola Master Golongan, migration
 * 2026_08_20_102320 — lihat komentar di sana untuk alasan lengkap).
 * `employees.jabatan_saat_ini` (kode mentah dari kdjab/FUNGSIONAL) diganti
 * FK `jabatan_fungsional_id`, dengan backfill data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('jabatan_fungsional_id')->nullable()->after('jabatan_saat_ini')->constrained('jabatan_fungsionals')->nullOnDelete();
        });

        $now = now();
        DB::table('employees')
            ->whereNotNull('jabatan_saat_ini')
            ->distinct()
            ->pluck('jabatan_saat_ini')
            ->each(function (string $kode) use ($now) {
                $jabatanId = DB::table('jabatan_fungsionals')->insertGetId([
                    'kode' => $kode,
                    'nama' => $kode,
                    'status_aktif' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('employees')->where('jabatan_saat_ini', $kode)->update(['jabatan_fungsional_id' => $jabatanId]);
            });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('jabatan_saat_ini');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('jabatan_saat_ini', 10)->nullable()->after('jabatan_fungsional_id');
        });

        DB::table('employees')
            ->join('jabatan_fungsionals', 'jabatan_fungsionals.id', '=', 'employees.jabatan_fungsional_id')
            ->update(['employees.jabatan_saat_ini' => DB::raw('jabatan_fungsionals.kode')]);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jabatan_fungsional_id');
        });
    }
};
