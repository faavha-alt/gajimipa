<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'nip', 'nama', 'unit_id', 'employee_status_id', 'email',
        'kode_npp_fakultas', 'golongan_saat_ini', 'jabatan_saat_ini',
        'kode_gaji_pokok_saat_ini', 'status_kawin_saat_ini', 'status_aktif',
    ];

    protected function casts(): array
    {
        return ['status_aktif' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function employeeStatus(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatus::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function salaryRecords(): HasMany
    {
        return $this->hasMany(SalaryRecord::class);
    }

    public function salaryImportRows(): HasMany
    {
        return $this->hasMany(SalaryImportRow::class);
    }
}
