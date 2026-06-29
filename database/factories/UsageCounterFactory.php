<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\UsageCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageCounter>
 */
class UsageCounterFactory extends Factory
{
    protected $model = UsageCounter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'date' => now()->toDateString(),
            'messages' => fake()->numberBetween(0, 100),
            'tokens_in' => fake()->numberBetween(0, 10000),
            'tokens_out' => fake()->numberBetween(0, 10000),
            'cost_micros' => fake()->numberBetween(0, 1000000),
        ];
    }
}
