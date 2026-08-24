<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory;

    public const STATUS_BELUM_DIKIRIM = 'BELUM_DIKIRIM';

    public const STATUS_TERKIRIM = 'TERKIRIM';

    public const STATUS_GAGAL = 'GAGAL';

    public const STATUS_DIKIRIM_ULANG = 'DIKIRIM_ULANG';

    protected $fillable = [
        'payslip_id', 'email_tujuan', 'status', 'pesan_error', 'dikirim_pada', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['dikirim_pada' => 'datetime'];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
