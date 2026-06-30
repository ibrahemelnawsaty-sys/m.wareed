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
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'user_id',
        'body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipients_total' => 'integer',
            'sent_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
        ];
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
