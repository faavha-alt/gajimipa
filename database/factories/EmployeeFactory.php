<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'nip' => fake()->unique()->numerify('##################'),
            'nik' => fake()->unique()->numerify('################'),
            'nama' => fake()->name(),
            'unit_id' => Unit::factory(),
            'employee_status_id' => EmployeeStatus::factory(),
            'email' => fake()->unique()->safeEmail(),
            'no_hp' => fake()->numerify('08##########'),
            'id_simpeg' => fake()->unique()->numerify('SIMPEG-#####'),
            'npwp' => fake()->unique()->numerify('##.###.###.#-###.###'),
            'no_rekening' => fake()->numerify('##########'),
            'status_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['status_aktif' => false]);
    }
}
