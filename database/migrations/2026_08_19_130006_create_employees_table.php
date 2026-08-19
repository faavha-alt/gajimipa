<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 20)->unique();
            $table->string('nama', 150);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('employee_status_id')->nullable()->constrained('employee_statuses')->nullOnDelete();
            $table->string('email', 150)->unique()->nullable();
            $table->string('kode_npp_fakultas', 20)->unique()->nullable();
            $table->string('golongan_saat_ini', 10)->nullable();
            $table->string('jabatan_saat_ini', 10)->nullable();
            $table->string('kode_gaji_pokok_saat_ini', 10)->nullable();
            $table->string('status_kawin_saat_ini', 10)->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
