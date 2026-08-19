<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'deduction_record_id', 'nomor_dokumen', 'path_file', 'is_revisi', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['is_revisi' => 'boolean'];
    }

    public function deductionRecord(): BelongsTo
    {
        return $this->belongsTo(DeductionRecord::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
