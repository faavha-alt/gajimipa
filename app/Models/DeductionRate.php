<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarif Potongan berdasarkan Golongan ATAU Status Pegawai (bukan keduanya
 * sekaligus — dicek di layer aplikasi, bukan DB constraint, biar portable).
 * Dipakai oleh RecurringDeduction bermode TARIF_GOLONGAN/TARIF_STATUS_PEGAWAI.
 * Histori tarif disimpan per `berlaku_mulai` (bukan overwrite) supaya periode
 * lama tetap memakai tarif yang berlaku saat itu — konsisten dgn §29 CLAUDE.md.
 */
class DeductionRate extends Model
{
    use HasFactory;

    protected $fillable = ['deduction_type_id', 'golongan_id', 'employee_status_id', 'nominal', 'berlaku_mulai'];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'berlaku_mulai' => 'date',
        ];
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }

    public function golongan(): BelongsTo
    {
        return $this->belongsTo(Golongan::class);
    }

    public function employeeStatus(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatus::class);
    }
}
