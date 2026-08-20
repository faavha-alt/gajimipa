<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Golongan (mengikuti pola Master Unit/Status Pegawai, §11 CLAUDE.md —
 * bukan nilai bebas/hardcode). `employees.golongan_saat_ini` yang sebelumnya
 * menyimpan kode mentah dari import gaji pusat (kdgol) diganti jadi FK
 * `golongan_id` ke tabel referensi kode->label yang bisa dikelola manual.
 *
 * Data lama di-backfill: tiap nilai unik golongan_saat_ini yang sudah ada
 * dibuatkan baris Golongan (kode=nilai, nama=nilai sbg placeholder awal,
 * operator bisa edit nama-nya jadi label yang benar via Master Golongan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('golongan_id')->nullable()->after('golongan_saat_ini')->constrained('golongans')->nullOnDelete();
        });

        $now = now();
        DB::table('employees')
            ->whereNotNull('golongan_saat_ini')
            ->distinct()
            ->pluck('golongan_saat_ini')
            ->each(function (string $kode) use ($now) {
                $golonganId = DB::table('golongans')->insertGetId([
                    'kode' => $kode,
                    'nama' => $kode,
                    'status_aktif' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('employees')->where('golongan_saat_ini', $kode)->update(['golongan_id' => $golonganId]);
            });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('golongan_saat_ini');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('golongan_saat_ini', 10)->nullable()->after('golongan_id');
        });

        DB::table('employees')
            ->join('golongans', 'golongans.id', '=', 'employees.golongan_id')
            ->update(['employees.golongan_saat_ini' => DB::raw('golongans.kode')]);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('golongan_id');
        });
    }
};
