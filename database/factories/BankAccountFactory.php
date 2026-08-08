<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Hesabı',
            'bank_name' => fake()->company(),
            'branch' => fake()->city(),
            'account_holder' => fake()->company(),
            'account_number' => fake()->numerify('########'),
            'iban' => 'TR' . fake()->numerify('00012345678901234567890'),
            'currency' => 'TRY',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
