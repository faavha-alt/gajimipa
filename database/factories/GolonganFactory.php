<?php

namespace Database\Factories;

use App\Models\Golongan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Golongan>
 */
class GolonganFactory extends Factory
{
    protected $model = Golongan::class;

    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->numerify('##'),
            'nama' => fake()->unique()->randomElement(['III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b']),
            'status_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['status_aktif' => false]);
    }
}
