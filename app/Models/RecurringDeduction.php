<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Potongan Berulang — daftar pinjaman/iuran tetap per pegawai yang otomatis
 * diusulkan ke Data Potongan tiap periode baru lewat tombol "Terapkan
 * Potongan Berulang" (bukan otomatis diam-diam — semua aksi di sistem ini
 * eksplisit lewat operator). Lihat App\Services\Deduction\RecurringDeductionService.
 *
 * 4 mode:
 *  - TETAP: nominal diketik manual, jalan terus sampai dihentikan manual.
 *  - ANGSURAN: nominal + jumlah_cicilan diketik manual, otomatis LUNAS
 *    setelah cicilan_ke mencapai jumlah_cicilan.
 *  - TARIF_GOLONGAN / TARIF_STATUS_PEGAWAI: nominal TIDAK diketik manual,
 *    diambil dari DeductionRate berdasarkan Golongan/Status Pegawai pegawai
 *    ybs pada saat periode diterapkan (jadi otomatis ikut kalau golongan
 *    pegawai berubah, atau tarifnya sendiri direvisi).
 */
class RecurringDeduction extends Model
{
    use HasFactory;

    public const MODE_TETAP = 'TETAP';

    public const MODE_ANGSURAN = 'ANGSURAN';

    public const MODE_TARIF_GOLONGAN = 'TARIF_GOLONGAN';

    public const MODE_TARIF_STATUS_PEGAWAI = 'TARIF_STATUS_PEGAWAI';

    public const STATUS_AKTIF = 'AKTIF';

    public const STATUS_LUNAS = 'LUNAS';

    public const STATUS_DIHENTIKAN = 'DIHENTIKAN';

    protected $fillable = [
        'employee_id', 'deduction_type_id', 'mode', 'nominal', 'jumlah_cicilan',
        'cicilan_ke', 'periode_mulai_id', 'status', 'keterangan', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }

    public function periodeMulai(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class, 'periode_mulai_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function deductionRecords(): HasMany
    {
        return $this->hasMany(DeductionRecord::class);
    }
}
