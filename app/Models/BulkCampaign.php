<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BulkCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bulk-message campaign: one `body` the owner sends to many eligible contacts
 * under the daily cap (Phase 6d, §11). tenant-owned (BelongsToTenant) — every
 * read/write is filtered by TenantScope, so a foreign campaign 404s (§1).
 *
 * `status` is the campaign's lifecycle (queued → sending → completed | stopped)
 * and the four counters mirror the per-recipient outcomes. They are written ONLY
 * by trusted server logic (BulkCampaignService / SendBulkMessageJob), never from
 * request input, so they stay OUT of $fillable (§13).
 *
 * @property int $id
 * @property string $body
 * @property string $status
 * @property int|null $message_template_id
 * @property list<string>|null $template_variables
 * @property int $recipients_total
 * @property int $sent_count
 * @property int $skipped_count
 * @property int $failed_count
 */
class BulkCampaign extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BulkCampaignFactory> */
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_STOPPED = 'stopped';

    /**
     * Only the non-sensitive descriptors are mass-assignable. `status` and the
     * counters are server-controlled lifecycle/audit fields and are DELIBERATELY
     * ABSENT — they change only through the service/job via trusted writes (§13).
     *
     * `message_template_id` and `template_variables` ARE safe to mass-assign here:
     * the service still verifies the template is this tenant's AND approved AND that
     * the variable count matches before writing — the FK is set from a validated,
     * tenant-scoped id, never trusted blindly (§13).
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'user_id',
        'message_template_id',
        'body',
        'template_variables',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_variables' => 'array',
            'recipients_total' => 'integer',
            'sent_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    /**
     * Whether this campaign sends a Meta-approved TEMPLATE (reaches contacts even
     * outside the 24h window) rather than free-form text. The job branches on this:
     * a template campaign SKIPS the window check but keeps opt-out + the atomic cap.
     */
    public function usesTemplate(): bool
    {
        return $this->message_template_id !== null;
    }

    /**
     * Whether the owner has stopped this campaign (the kill switch). Once stopped
     * every still-queued job exits without sending (§9 reversibility).
     */
    public function isStopped(): bool
    {
        return $this->status === self::STATUS_STOPPED;
    }

    /**
     * @return BelongsTo<WhatsappAccount, $this>
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    /**
     * The Meta-approved template this campaign sends, or null for a free-form
     * campaign. Resolves through TenantScope, so it can only ever be this tenant's.
     *
     * @return BelongsTo<MessageTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    /**
     * The owner who created the campaign; recorded as the author (user_id) of
     * every outbound bulk Message so the inbox shows who sent it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BulkCampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BulkCampaignRecipient::class);
    }
}
