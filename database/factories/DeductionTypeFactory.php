<?php

namespace Database\Factories;

use App\Models\DeductionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeductionType>
 */
class DeductionTypeFactory extends Factory
{
    protected $model = DeductionType::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('POTONGAN_???')),
            'nama' => fake()->unique()->words(3, true),
            'keterangan' => null,
            'status_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['status_aktif' => false]);
    }
}
