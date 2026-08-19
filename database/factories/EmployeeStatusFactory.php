<?php

namespace Database\Factories;

use App\Models\EmployeeStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeStatus>
 */
class EmployeeStatusFactory extends Factory
{
    protected $model = EmployeeStatus::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('STS-???')),
            'nama' => fake()->unique()->words(2, true),
            'status_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['status_aktif' => false]);
    }
}
