<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    protected $model = KnowledgeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'whatsapp_account_id' => WhatsappAccount::factory()->for($tenant),
            'title' => fake()->sentence(3),
            'type' => 'text',
            'content' => fake()->paragraph(),
        ];
    }
}
