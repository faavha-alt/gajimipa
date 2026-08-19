<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_import_id', 'nomor_baris', 'data_mentah',
        'employee_id', 'status', 'pesan_error',
    ];

    protected function casts(): array
    {
        return ['data_mentah' => 'array'];
    }

    public function salaryImport(): BelongsTo
    {
        return $this->belongsTo(SalaryImport::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
