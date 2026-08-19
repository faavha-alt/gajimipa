<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportColumnMapping extends Model
{
    use HasFactory;

    protected $fillable = ['nama_template', 'jenis', 'definisi_kolom', 'status_aktif'];

    protected function casts(): array
    {
        return [
            'definisi_kolom' => 'array',
            'status_aktif' => 'boolean',
        ];
    }

    public function salaryImports(): HasMany
    {
        return $this->hasMany(SalaryImport::class);
    }

    public function deductionImports(): HasMany
    {
        return $this->hasMany(DeductionImport::class);
    }
}
