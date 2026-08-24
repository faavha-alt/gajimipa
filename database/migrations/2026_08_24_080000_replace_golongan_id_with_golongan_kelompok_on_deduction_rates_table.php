<?php

use App\Models\Golongan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif Potongan berbasis Golongan ternyata berlaku per KELOMPOK golongan
 * (mis. "III", "IV"), bukan per sub-golongan ("III/a" vs "III/b") —
 * dikonfirmasi user 2026-08-24, dan terbukti di data nyata yang sudah
 * diinput operator: semua sub-golongan III sama nominalnya, semua IV sama.
 * Migration ini menggabungkan baris-baris per sub-golongan yang sudah
 * terlanjur diinput satu-satu jadi 1 baris per kelompok, lalu mengganti
 * `golongan_id` (FK) dengan `golongan_kelompok` (string, mis. "III").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_rates', function (Blueprint $table) {
            $table->string('golongan_kelompok', 20)->nullable()->after('golongan_id');
        });

        $kelompokPerGolongan = Golongan::all()->mapWithKeys(fn (Golongan $g) => [$g->id => $g->kelompok()]);

        $rows = DB::table('deduction_rates')->whereNotNull('golongan_id')->get();
        $grouped = $rows->groupBy(fn ($r) => $r->deduction_type_id.'|'.$kelompokPerGolongan[$r->golongan_id]);

        foreach ($grouped as $group) {
            $nominalBerbeda = $group->pluck('nominal')->unique()->count() > 1;

            if ($nominalBerbeda) {
                // Ada nominal yang tidak konsisten dalam 1 kelompok golongan
                // — jangan tebak, hentikan migrasi supaya operator/pengembang
                // memeriksa manual dulu (lebih aman drpd salah gabung data
                // tarif keuangan).
                throw new \RuntimeException(
                    'Migrasi dihentikan: ditemukan nominal tarif yang berbeda dalam 1 kelompok golongan untuk deduction_type_id='
                    .$group->first()->deduction_type_id.'. Periksa tabel deduction_rates secara manual sebelum melanjutkan.'
                );
            }

            $kelompok = $kelompokPerGolongan[$group->first()->golongan_id];
            $terpakai = $group->sortBy('berlaku_mulai')->first();

            DB::table('deduction_rates')->where('id', $terpakai->id)->update([
                'golongan_kelompok' => $kelompok,
                'golongan_id' => null,
            ]);

            $idHapus = $group->pluck('id')->reject(fn ($id) => $id === $terpakai->id)->values();
            if ($idHapus->isNotEmpty()) {
                DB::table('deduction_rates')->whereIn('id', $idHapus)->delete();
            }
        }

        Schema::table('deduction_rates', function (Blueprint $table) {
            $table->dropIndex(['deduction_type_id', 'golongan_id', 'berlaku_mulai']);
            $table->dropForeign(['golongan_id']);
            $table->dropColumn('golongan_id');

            $table->index(['deduction_type_id', 'golongan_kelompok', 'berlaku_mulai']);
        });
    }

    public function down(): void
    {
        Schema::table('deduction_rates', function (Blueprint $table) {
            $table->foreignId('golongan_id')->nullable()->after('deduction_type_id')->constrained()->cascadeOnDelete();
            $table->dropColumn('golongan_kelompok');
        });
    }
};
