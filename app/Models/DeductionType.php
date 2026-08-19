<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionType extends Model
{
    use HasFactory;

    protected $fillable = ['kode', 'nama', 'keterangan', 'status_aktif'];

    protected function casts(): array
    {
        return ['status_aktif' => 'boolean'];
    }

    public function deductionRecords(): HasMany
    {
        return $this->hasMany(DeductionRecord::class);
    }

    public function submissionRecords(): HasMany
    {
        return $this->hasMany(SubmissionRecord::class);
    }
}
