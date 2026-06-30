<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BulkCampaignRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recipient row of a bulk campaign — the unit a SendBulkMessageJob processes
 * (Phase 6d). tenant-owned (BelongsToTenant): isolated by TenantScope (§1).
 *
 * `status` is the per-contact outcome and is written ONLY by the job through
 * trusted writes (never request input), so it stays OUT of $fillable (§13):
 *   - pending          — not yet processed
 *   - sent             — delivered to Cloud API (wa_message_id recorded)
 *   - skipped_window   — outside the 24h window; a template is required (§11)
 *   - skipped_optout   — the contact unsubscribed
 *   - skipped_cap      — the daily cap was reached before this row's turn
 *   - failed           — the Cloud API send raised (failed_reason recorded)
 *
 * @property int $id
 * @property string $status
 * @property string $wa_contact_id
 * @property string|null $wa_message_id
 * @property string|null $failed_reason
 */
class BulkCampaignRecipient extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BulkCampaignRecipientFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED_WINDOW = 'skipped_window';

    public const STATUS_SKIPPED_OPTOUT = 'skipped_optout';

    public const STATUS_SKIPPED_CAP = 'skipped_cap';

    public const STATUS_FAILED = 'failed';

    /**
     * Only the identifying descriptors are mass-assignable. `status`,
     * `wa_message_id`, and `failed_reason` are outcome fields written only by the
     * job and are DELIBERATELY ABSENT (§13).
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'bulk_campaign_id',
        'conversation_id',
        'wa_contact_id',
    ];

    /**
     * @return BelongsTo<BulkCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BulkCampaign::class, 'bulk_campaign_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
