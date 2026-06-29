<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'wa_contact_id',
        'status',
        'window_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_expires_at' => 'datetime',
        ];
    }

    /**
     * Whether the 24-hour customer service window is still open (§11). Free-form
     * replies are only permitted while this is true; outside it a pre-approved
     * template is required. `window_expires_at` is the source of truth.
     */
    public function isWindowOpen(): bool
    {
        return $this->window_expires_at !== null
            && now()->lessThan($this->window_expires_at);
    }

    /**
     * @return BelongsTo<WhatsappAccount, $this>
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
