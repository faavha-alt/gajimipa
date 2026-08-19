<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_period_id', 'employee_id', 'salary_import_id', 'income_type_id',
        'nip_snapshot', 'nama_snapshot', 'unit_snapshot', 'golongan_snapshot',
        'jabatan_snapshot', 'kode_gaji_pokok_snapshot', 'status_kawin_snapshot',
        'total_penghasilan_kotor', 'total_potongan_pusat', 'bersih_pusat',
        'total_potongan_fakultas', 'gaji_bersih_final',
    ];

    protected function casts(): array
    {
        return [
            'total_penghasilan_kotor' => 'decimal:2',
            'total_potongan_pusat' => 'decimal:2',
            'bersih_pusat' => 'decimal:2',
            'total_potongan_fakultas' => 'decimal:2',
            'gaji_bersih_final' => 'decimal:2',
        ];
    }

    public function salaryPeriod(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryImport(): BelongsTo
    {
        return $this->belongsTo(SalaryImport::class);
    }

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryComponent::class);
    }

    public function deductionRecords(): HasMany
    {
        return $this->hasMany(DeductionRecord::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
