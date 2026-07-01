<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'whatsapp_account_id' => WhatsappAccount::factory()->for($tenant),
            'name' => fake()->unique()->slug(2, false),
            'language' => 'ar',
            'category' => 'utility',
            'status' => MessageTemplate::STATUS_APPROVED,
            'body_text' => 'مرحباً، عرضنا اليوم متاح الآن.',
            'variable_count' => 0,
            'last_synced_at' => now(),
        ];
    }

    /**
     * An approved template (the only kind that may be sent).
     */
    public function approved(): static
    {
        return $this->state(['status' => MessageTemplate::STATUS_APPROVED]);
    }

    /**
     * A pending template — must be rejected by the campaign builder.
     */
    public function pending(): static
    {
        return $this->state(['status' => MessageTemplate::STATUS_PENDING]);
    }
}
