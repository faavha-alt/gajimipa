<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_import_id')->constrained('salary_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->json('data_mentah');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20);
            $table->text('pesan_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_import_rows');
    }
};
