<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_period_id')->constrained('salary_periods')->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->restrictOnDelete();
            $table->unsignedInteger('jumlah_pegawai');
            $table->decimal('total_nominal', 15, 2);
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_records');
    }
};
