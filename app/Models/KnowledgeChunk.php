<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\KnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<KnowledgeChunkFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'knowledge_document_id',
        'content',
        'embedding',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function knowledgeDocument(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }
}
