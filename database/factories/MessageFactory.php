<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'conversation_id' => Conversation::factory()->for($tenant),
            'wa_message_id' => 'wamid.'.fake()->unique()->bothify('??##########'),
            'direction' => fake()->randomElement(['in', 'out']),
            'type' => 'text',
            'body' => fake()->sentence(),
            'tokens_in' => fake()->numberBetween(0, 500),
            'tokens_out' => fake()->numberBetween(0, 500),
            'cost_micros' => fake()->numberBetween(0, 100000),
            'status' => 'received',
        ];
    }
}
