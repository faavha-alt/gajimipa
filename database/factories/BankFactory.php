<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        return [
            'kode' => Str::upper(fake()->unique()->lexify('BANK???')),
            'nama' => fake()->unique()->randomElement(['Bank Rakyat Indonesia', 'Bank Negara Indonesia', 'Bank Mandiri', 'Bank Jateng', 'Bank Tabungan Negara']),
            'status_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['status_aktif' => false]);
    }
}
