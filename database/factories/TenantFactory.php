<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'plan' => 'free',
            'status' => 'active',
        ];
    }

    /**
     * A tenant awaiting admin approval (the state a fresh signup lands in).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
        ]);
    }

    /**
     * A tenant whose service has been suspended by an admin.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'suspended',
        ]);
    }
}
