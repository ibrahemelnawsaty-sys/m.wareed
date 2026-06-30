<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BulkCampaign;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkCampaign>
 */
class BulkCampaignFactory extends Factory
{
    protected $model = BulkCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'whatsapp_account_id' => WhatsappAccount::factory()->for($tenant),
            'user_id' => User::factory()->for($tenant),
            'body' => fake()->sentence(),
            'status' => BulkCampaign::STATUS_QUEUED,
            'recipients_total' => 0,
            'sent_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
        ];
    }
}
