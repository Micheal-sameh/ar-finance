<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(AccountType::cases());

        return [
            'code' => (string) $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->words(2, true),
            'type' => $type,
            'normal_balance' => $type->defaultNormalBalance(),
            'is_active' => true,
        ];
    }
}
