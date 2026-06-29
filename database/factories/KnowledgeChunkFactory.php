<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeChunk>
 */
class KnowledgeChunkFactory extends Factory
{
    protected $model = KnowledgeChunk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'knowledge_document_id' => KnowledgeDocument::factory()->for($tenant),
            'content' => fake()->paragraph(),
            'embedding' => null,
        ];
    }
}
