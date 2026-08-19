<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_period_id')->constrained('salary_periods')->cascadeOnDelete();
            $table->foreignId('import_column_mapping_id')->nullable()->constrained('import_column_mappings')->nullOnDelete();
            $table->string('nama_file', 255);
            $table->string('path_file', 255);
            $table->foreignId('diupload_oleh')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('DRAFT_PREVIEW');
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('jumlah_error')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_imports');
    }
};
