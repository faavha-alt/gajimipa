<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_period_id', 'deduction_type_id', 'jumlah_pegawai', 'total_nominal', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['total_nominal' => 'decimal:2'];
    }

    public function salaryPeriod(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class);
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
