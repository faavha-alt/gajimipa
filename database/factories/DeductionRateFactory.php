<?php

namespace Database\Factories;

use App\Models\DeductionRate;
use App\Models\DeductionType;
use App\Models\Golongan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeductionRate>
 */
class DeductionRateFactory extends Factory
{
    protected $model = DeductionRate::class;

    public function definition(): array
    {
        return [
            'deduction_type_id' => DeductionType::factory(),
            'golongan_id' => Golongan::factory(),
            'employee_status_id' => null,
            'nominal' => fake()->numberBetween(10000, 200000),
            'berlaku_mulai' => now()->startOfMonth(),
        ];
    }
}
