<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_record_id')->constrained('salary_records')->cascadeOnDelete();
            $table->string('kategori', 20); // PENGHASILAN | POTONGAN_PUSAT
            $table->string('kode_komponen', 30);
            $table->string('nama_komponen', 100);
            $table->decimal('nominal', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
