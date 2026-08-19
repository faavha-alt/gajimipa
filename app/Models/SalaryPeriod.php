<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPeriod extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_VERIFIKASI = 'VERIFIKASI';

    public const STATUS_FINAL = 'FINAL';

    public const STATUS_ARSIP = 'ARSIP';

    protected $fillable = [
        'nama_periode', 'bulan', 'tahun', 'status', 'versi',
        'periode_asal_id', 'status_supersede', 'locked_by_user_id',
        'locked_at', 'alasan_revisi',
    ];

    protected function casts(): array
    {
        return [
            'status_supersede' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function periodeAsal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'periode_asal_id');
    }

    public function revisi(): HasMany
    {
        return $this->hasMany(self::class, 'periode_asal_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function salaryImports(): HasMany
    {
        return $this->hasMany(SalaryImport::class);
    }

    public function deductionImports(): HasMany
    {
        return $this->hasMany(DeductionImport::class);
    }

    public function salaryRecords(): HasMany
    {
        return $this->hasMany(SalaryRecord::class);
    }

    public function submissionRecords(): HasMany
    {
        return $this->hasMany(SubmissionRecord::class);
    }
}
