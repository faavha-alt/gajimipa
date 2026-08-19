<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['kode_unit', 'nama_unit', 'status_aktif'];

    protected function casts(): array
    {
        return ['status_aktif' => 'boolean'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
