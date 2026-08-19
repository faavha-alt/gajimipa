<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('nama_template', 150);
            $table->enum('jenis', ['GAJI_PUSAT', 'POTONGAN_FAKULTAS']);
            $table->json('definisi_kolom');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_column_mappings');
    }
};
