<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'method' => 'bank_transfer',
            'account_id' => Account::factory(),
            'label' => fake()->words(2, true),
        ];
    }
}
