<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_record_id', 'nomor_dokumen', 'path_file', 'is_revisi', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['is_revisi' => 'boolean'];
    }

    public function salaryRecord(): BelongsTo
    {
        return $this->belongsTo(SalaryRecord::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }
}
