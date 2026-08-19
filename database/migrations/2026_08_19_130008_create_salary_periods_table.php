<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_periods', function (Blueprint $table) {
            $table->id();
            $table->string('nama_periode', 30);
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('status', 20)->default('DRAFT');
            $table->unsignedSmallInteger('versi')->default(1);
            $table->foreignId('periode_asal_id')->nullable()->constrained('salary_periods')->nullOnDelete();
            $table->boolean('status_supersede')->default(false);
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->text('alasan_revisi')->nullable();
            $table->timestamps();

            $table->unique(['bulan', 'tahun', 'versi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_periods');
    }
};
