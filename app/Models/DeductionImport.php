<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_period_id', 'import_column_mapping_id', 'nama_file',
        'path_file', 'diupload_oleh', 'status', 'jumlah_baris', 'jumlah_error',
    ];

    public function salaryPeriod(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class);
    }

    public function importColumnMapping(): BelongsTo
    {
        return $this->belongsTo(ImportColumnMapping::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diupload_oleh');
    }

    public function deductionRecords(): HasMany
    {
        return $this->hasMany(DeductionRecord::class);
    }
}
