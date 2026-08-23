<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard defensif: percobaan migrate pertama di server dev sempat
        // membuat tabel ini lewat CREATE TABLE (DDL MySQL auto-commit, tidak
        // ikut ter-rollback) sebelum baris migrasinya sempat tercatat —
        // jadi tabel bisa saja sudah ada tapi belum "tercatat jalan".
        if (Schema::hasTable('deduction_rates')) {
            return;
        }

        Schema::create('deduction_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deduction_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('golongan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_status_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->date('berlaku_mulai');
            $table->timestamps();

            $table->index(['deduction_type_id', 'golongan_id', 'berlaku_mulai']);
            $table->index(['deduction_type_id', 'employee_status_id', 'berlaku_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_rates');
    }
};
