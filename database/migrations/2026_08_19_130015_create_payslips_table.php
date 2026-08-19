<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_record_id')->constrained('salary_records')->cascadeOnDelete();
            $table->string('nomor_dokumen', 50)->unique();
            $table->string('path_file', 255);
            $table->boolean('is_revisi')->default(false);
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
