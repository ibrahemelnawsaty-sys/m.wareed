<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceMenu;
use App\Models\ServiceMenuRow;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceMenuRow>
 */
class ServiceMenuRowFactory extends Factory
{
    protected $model = ServiceMenuRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'service_menu_id' => ServiceMenu::factory()->for($tenant),
            'row_key' => 'row_'.fake()->unique()->numberBetween(1, 100000),
            'title' => fake()->words(2, true),
            'description' => null,
            'action_type' => 'reply',
            'reply_text' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 9),
        ];
    }

    public function reply(string $text = 'هذا ردّ جاهز.'): static
    {
        return $this->state(fn (): array => [
            'action_type' => 'reply',
            'reply_text' => $text,
        ]);
    }

    public function handoff(): static
    {
        return $this->state(fn (): array => [
            'action_type' => 'handoff',
            'reply_text' => null,
        ]);
    }
}
