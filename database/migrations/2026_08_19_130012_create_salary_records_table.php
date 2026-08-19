<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_period_id')->constrained('salary_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('salary_import_id')->nullable()->constrained('salary_imports')->nullOnDelete();
            $table->foreignId('income_type_id')->nullable()->constrained('income_types')->nullOnDelete();

            $table->string('nip_snapshot', 20);
            $table->string('nama_snapshot', 150);
            $table->string('unit_snapshot', 150)->nullable();
            $table->string('golongan_snapshot', 10)->nullable();
            $table->string('jabatan_snapshot', 10)->nullable();
            $table->string('kode_gaji_pokok_snapshot', 10)->nullable();
            $table->string('status_kawin_snapshot', 10)->nullable();

            $table->decimal('total_penghasilan_kotor', 15, 2)->default(0);
            $table->decimal('total_potongan_pusat', 15, 2)->default(0);
            $table->decimal('bersih_pusat', 15, 2)->default(0);
            $table->decimal('total_potongan_fakultas', 15, 2)->default(0);
            $table->decimal('gaji_bersih_final', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['salary_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_records');
    }
};
