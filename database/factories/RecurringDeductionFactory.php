<?php

namespace Database\Factories;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\RecurringDeduction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringDeduction>
 */
class RecurringDeductionFactory extends Factory
{
    protected $model = RecurringDeduction::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'deduction_type_id' => DeductionType::factory(),
            'mode' => RecurringDeduction::MODE_TETAP,
            'nominal' => fake()->numberBetween(50000, 500000),
            'jumlah_cicilan' => null,
            'cicilan_ke' => 0,
            'periode_mulai_id' => null,
            'status' => RecurringDeduction::STATUS_AKTIF,
            'keterangan' => null,
            'dibuat_oleh' => User::factory(),
        ];
    }

    public function angsuran(int $jumlahCicilan): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => RecurringDeduction::MODE_ANGSURAN,
            'jumlah_cicilan' => $jumlahCicilan,
        ]);
    }

    public function tarifGolongan(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => RecurringDeduction::MODE_TARIF_GOLONGAN,
            'nominal' => null,
        ]);
    }

    public function tarifStatusPegawai(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => RecurringDeduction::MODE_TARIF_STATUS_PEGAWAI,
            'nominal' => null,
        ]);
    }
}
