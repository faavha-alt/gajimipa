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
        'nip', 'nik', 'nama', 'unit_id', 'employee_status_id', 'email', 'no_hp',
        'id_simpeg', 'npwp', 'no_rekening', 'golongan_id', 'jabatan_fungsional_id',
        'kode_gaji_pokok_saat_ini', 'status_kawin_saat_ini', 'status_aktif',
    ];

    /**
     * `npwp` & `no_rekening` adalah data finansial sensitif (override keputusan
     * A3 — lihat migration 2026_08_19_210000). Disembunyikan dari serialisasi
     * array/JSON default; hanya diakses eksplisit lewat query di halaman yang
     * sudah digerbang permission `employees.manage`.
     */
    protected $hidden = ['npwp', 'no_rekening'];

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

    public function golongan(): BelongsTo
    {
        return $this->belongsTo(Golongan::class);
    }

    public function jabatanFungsional(): BelongsTo
    {
        return $this->belongsTo(JabatanFungsional::class);
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
