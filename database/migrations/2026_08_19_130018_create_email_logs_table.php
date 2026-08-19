<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->string('email_tujuan', 150);
            $table->string('status', 20)->default('BELUM_DIKIRIM');
            $table->text('pesan_error')->nullable();
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
