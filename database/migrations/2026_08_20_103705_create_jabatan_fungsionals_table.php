<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatan_fungsionals', function (Blueprint $table) {
            $table->id();
            // Lebih lebar dari Master Golongan/Unit — kode Non-PNS bisa berupa
            // frasa mentah FUNGSIONAL (mis. "Tenaga Kependidikan"), bukan cuma
            // kode singkat numerik seperti kdjab PNS.
            $table->string('kode', 100)->unique();
            $table->string('nama', 100);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatan_fungsionals');
    }
};
