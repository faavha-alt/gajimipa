<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryComponent extends Model
{
    use HasFactory;

    public const KATEGORI_PENGHASILAN = 'PENGHASILAN';

    public const KATEGORI_POTONGAN_PUSAT = 'POTONGAN_PUSAT';

    protected $fillable = [
        'salary_record_id', 'kategori', 'kode_komponen', 'nama_komponen', 'nominal',
    ];

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2'];
    }

    public function salaryRecord(): BelongsTo
    {
        return $this->belongsTo(SalaryRecord::class);
    }
}
