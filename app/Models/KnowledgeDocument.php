<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<KnowledgeDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'title',
        'type',
        'content',
    ];

    /**
     * @return BelongsTo<WhatsappAccount, $this>
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }
}
