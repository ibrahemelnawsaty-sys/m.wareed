<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WhatsappAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappAccount>
 */
class WhatsappAccountFactory extends Factory
{
    protected $model = WhatsappAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'phone_number_id' => (string) fake()->unique()->numerify('###############'),
            'waba_id' => (string) fake()->numerify('###############'),
            'display_name' => fake()->company(),
            'access_token' => fake()->sha256(),
            // Null by default: most accounts verify the webhook against the
            // PLATFORM secret until the tenant pastes their own app secret. A
            // test that exercises per-tenant verification sets it explicitly.
            'app_secret' => null,
            'ai_model' => 'gemini-2.5-flash-lite',
            'ai_api_key' => fake()->sha256(),
            'system_prompt' => fake()->sentence(),
            'temperature' => fake()->numberBetween(0, 100),
            'status' => 'pending',
        ];
    }
}
