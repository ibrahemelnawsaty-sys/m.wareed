<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Meta-approved message template (Phase 7c, §11). Sending a template reaches a
 * contact even OUTSIDE the 24h service window — that is its purpose — so it is the
 * single most dangerous artefact for the customer's number: only an `approved`
 * template may ever be sent (isApproved() gate, enforced in the controller/service
 * and in a test that rejects pending/rejected).
 *
 * tenant-owned (BelongsToTenant) — every read/write is TenantScope-filtered, so a
 * foreign template 404s and can never be selected for another tenant's campaign (§1).
 *
 * Trust boundary (§13): `status`, `category`, `variable_count`, and `last_synced_at`
 * are the cached mirror of Meta's truth (or an owner's manual entry verified
 * server-side). They are written ONLY through the trusted writers below
 * (syncFromMeta / setManually), never mass-assigned — Meta's payload is untrusted
 * input, and a request must never be able to flip a pending template to approved.
 * `tenant_id` is likewise out of $fillable (set by BelongsToTenant from context).
 *
 * @property int $id
 * @property string $name
 * @property string $language
 * @property string $category
 * @property string $status
 * @property string|null $body_text
 * @property int $variable_count
 * @property Carbon|null $last_synced_at
 */
class MessageTemplate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory;

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_UNKNOWN = 'unknown';

    /**
     * Only the owner-authored descriptors are mass-assignable. `status`,
     * `variable_count`, and `last_synced_at` are the trusted Meta-mirror fields and
     * are DELIBERATELY ABSENT — they change only through syncFromMeta()/setManually()
     * via forceFill, never from request input (§13).
     *
     * @var list<string>
     */
    protected $fillable = [
        'whatsapp_account_id',
        'name',
        'language',
        'category',
        'body_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variable_count' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Whether this template may be sent. ONLY an approved template is eligible —
     * sending a non-approved (or marketing-to-non-opted-in) template invites Meta
     * bans (§11). This is the gate the controller/service consult before queuing a
     * template campaign.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Mirror this template's live state from Meta (TemplateSync). Server-trusted:
     * the Meta payload is untrusted, so the cache fields are written via forceFill,
     * never mass assignment, and a request can never flip the approval status (§13).
     */
    public function syncFromMeta(string $status, string $category, int $variableCount, ?string $bodyText): void
    {
        $this->forceFill([
            'status' => $status,
            'category' => $category,
            'variable_count' => $variableCount,
            'body_text' => $bodyText,
            'last_synced_at' => now(),
        ])->save();
    }

    /**
     * Whether this template is currently selectable for a bulk campaign.
     *
     * @param  Builder<MessageTemplate>  $query
     * @return Builder<MessageTemplate>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * @return BelongsTo<WhatsappAccount, $this>
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }
}
