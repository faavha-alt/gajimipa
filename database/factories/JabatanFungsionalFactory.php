<?php

namespace Database\Factories;

use App\Models\JabatanFungsional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JabatanFungsional>
 */
class JabatanFungsionalFactory extends Factory
{
    protected $model = JabatanFungsional::class;

    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->numerify('#####'),
            'nama' => fake()->unique()->randomElement(['Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Tenaga Pengajar', 'Guru Besar']),
            'status_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['status_aktif' => false]);
    }
}
