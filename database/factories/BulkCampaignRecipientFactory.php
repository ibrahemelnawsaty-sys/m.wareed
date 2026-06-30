<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BulkCampaign;
use App\Models\BulkCampaignRecipient;
use App\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkCampaignRecipient>
 */
class BulkCampaignRecipientFactory extends Factory
{
    protected $model = BulkCampaignRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'bulk_campaign_id' => BulkCampaign::factory()->for($tenant),
            'conversation_id' => Conversation::factory()->for($tenant),
            'wa_contact_id' => (string) fake()->numerify('###########'),
            'status' => BulkCampaignRecipient::STATUS_PENDING,
        ];
    }
}
