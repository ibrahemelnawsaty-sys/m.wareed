<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'whatsapp_account_id' => WhatsappAccount::factory()->for($tenant),
            'wa_contact_id' => (string) fake()->numerify('###########'),
            'status' => 'open',
            'window_expires_at' => now()->addDay(),
        ];
    }
}
