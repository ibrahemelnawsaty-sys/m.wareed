<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WhatsappAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappAccount extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WhatsappAccountFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'phone_number_id',
        'waba_id',
        'display_name',
        'access_token',
        'ai_model',
        'ai_api_key',
        'system_prompt',
        'temperature',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'ai_api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'ai_api_key' => 'encrypted',
            'temperature' => 'integer',
        ];
    }

    /**
     * @return HasMany<KnowledgeDocument, $this>
     */
    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
