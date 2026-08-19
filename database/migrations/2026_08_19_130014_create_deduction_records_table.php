<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_record_id')->constrained('salary_records')->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->restrictOnDelete();
            $table->foreignId('deduction_import_id')->nullable()->constrained('deduction_imports')->nullOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->string('keterangan', 255)->nullable();
            $table->string('sumber', 10); // IMPORT | MANUAL
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_records');
    }
};
