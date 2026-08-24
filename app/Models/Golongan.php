<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Golongan extends Model
{
    use HasFactory;

    protected $fillable = ['kode', 'nama', 'status_aktif'];

    protected function casts(): array
    {
        return ['status_aktif' => 'boolean'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Kelompok golongan (mis. "III" dari "III/b") — dipakai buat Tarif
     * Potongan (App\Models\DeductionRate) karena tarif berbasis golongan
     * ternyata berlaku per kelompok, bukan per sub-golongan (dikonfirmasi
     * user 2026-08-24: semua sub-golongan III sama, semua IV sama, dst).
     * Diturunkan dari `nama`, bukan kolom tersendiri — biar tidak ada 2
     * sumber kebenaran yang bisa tidak sinkron.
     */
    public function kelompok(): string
    {
        return str_contains($this->nama, '/') ? explode('/', $this->nama)[0] : $this->nama;
    }
}
