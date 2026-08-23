<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained()->restrictOnDelete();
            $table->string('mode', 30);
            $table->decimal('nominal', 15, 2)->nullable();
            $table->unsignedInteger('jumlah_cicilan')->nullable();
            $table->unsignedInteger('cicilan_ke')->default(0);
            $table->foreignId('periode_mulai_id')->nullable()->constrained('salary_periods')->nullOnDelete();
            $table->string('status', 20)->default('AKTIF');
            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_deductions');
    }
};
