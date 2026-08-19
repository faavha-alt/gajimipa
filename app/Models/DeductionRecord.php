<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionRecord extends Model
{
    use HasFactory;

    public const SUMBER_IMPORT = 'IMPORT';

    public const SUMBER_MANUAL = 'MANUAL';

    protected $fillable = [
        'salary_record_id', 'deduction_type_id', 'deduction_import_id',
        'nominal', 'keterangan', 'sumber', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2'];
    }

    public function salaryRecord(): BelongsTo
    {
        return $this->belongsTo(SalaryRecord::class);
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }

    public function deductionImport(): BelongsTo
    {
        return $this->belongsTo(DeductionImport::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(DeductionReceipt::class);
    }
}
