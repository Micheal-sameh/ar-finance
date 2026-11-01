<?php

namespace Database\Factories;

use App\Enums\GlMappingRole;
use App\Models\Account;
use App\Models\GlMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlMapping>
 */
class GlMappingFactory extends Factory
{
    protected $model = GlMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => fake()->randomElement(GlMappingRole::cases()),
            'account_id' => Account::factory(),
        ];
    }
}
