<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_records', function (Blueprint $table) {
            $table->foreignId('recurring_deduction_id')->nullable()->after('deduction_import_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deduction_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_deduction_id');
        });
    }
};
