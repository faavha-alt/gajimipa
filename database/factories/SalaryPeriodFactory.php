<?php

namespace Database\Factories;

use App\Models\SalaryPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryPeriod>
 */
class SalaryPeriodFactory extends Factory
{
    protected $model = SalaryPeriod::class;

    private const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function definition(): array
    {
        $bulan = fake()->unique()->numberBetween(1, 12);
        $tahun = fake()->numberBetween(2025, 2030);

        return [
            'nama_periode' => self::NAMA_BULAN[$bulan].' '.$tahun,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'status' => SalaryPeriod::STATUS_DRAFT,
            'versi' => 1,
        ];
    }

    public function verifikasi(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SalaryPeriod::STATUS_VERIFIKASI]);
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SalaryPeriod::STATUS_FINAL]);
    }

    public function arsip(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SalaryPeriod::STATUS_ARSIP]);
    }
}
