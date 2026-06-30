<?php

declare(strict_types=1);

namespace App\Services\Bulk;

use App\Jobs\SendBulkMessageJob;
use App\Models\BulkCampaign;
use App\Models\BulkCampaignRecipient;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates a bulk campaign (Phase 6d, §11) — runs INSIDE a bound tenant
 * context, so every read/write is TenantScope-filtered (§1).
 *
 * Meta-safety guarantees this service upholds:
 *   - opt-in: recipients are conversations only (they messaged us first), minus
 *     anyone opted out (eligibleForBulk scope).
 *   - cap pre-flight: a campaign whose size exceeds the day's remaining cap is
 *     rejected up front (BulkCapExceededException) — but the HARD enforcement is
 *     still the atomic SendQuota::tryConsume at send time, since other campaigns
 *     or webhooks may consume the cap concurrently between create and send.
 *   - 24h window & opt-out are re-checked per recipient in the job, never here.
 */
class BulkCampaignService
{
    public function __construct(private readonly SendQuota $quota) {}

    /**
     * The contacts eligible to receive a bulk message on this account: this
     * tenant's conversations on the account that have NOT opted out (opt-in is
     * implicit — they have a conversation). The 24h-window decision is made per
     * recipient at send time (the job), so it is not filtered here (§11).
     *
     * @return Collection<int, Conversation>
     */
    public function eligibleRecipients(WhatsappAccount $account): Collection
    {
        return Conversation::query()
            ->where('whatsapp_account_id', $account->id)
            ->eligibleForBulk()
            ->get();
    }

    /**
     * How many contacts are eligible — a DB-level COUNT(*), so the list page can
     * show the figure without hydrating every conversation into memory (§14, on
     * shared hosting). Use this for display; eligibleRecipients() (which loads
     * the rows) only where the actual collection is needed to build a campaign.
     */
    public function eligibleRecipientsCount(WhatsappAccount $account): int
    {
        return Conversation::query()
            ->where('whatsapp_account_id', $account->id)
            ->eligibleForBulk()
            ->count();
    }

    /**
     * Create a campaign for $recipients and dispatch one queued send job per
     * recipient (database queue — no synchronous send, no permanent worker;
     * ADR-03). Rejects up front if the count exceeds the day's remaining cap.
     *
     * The campaign + its recipient rows are created in ONE transaction so a
     * half-built campaign can never leak; jobs are dispatched only AFTER commit
     * (afterCommit) so a worker can never pick up a row that was rolled back.
     *
     * @param  Collection<int, Conversation>  $recipients
     *
     * @throws BulkCapExceededException when the count exceeds the remaining cap
     */
    public function create(WhatsappAccount $account, User $user, string $body, Collection $recipients): BulkCampaign
    {
        $remaining = $this->quota->remainingToday($account);

        if ($recipients->count() > $remaining) {
            // Loud, explicit rejection — never silently truncate the list (§3).
            throw new BulkCapExceededException($remaining);
        }

        /** @var BulkCampaign $campaign */
        $campaign = DB::transaction(function () use ($account, $user, $body, $recipients): BulkCampaign {
            $campaign = BulkCampaign::query()->create([
                'whatsapp_account_id' => $account->id,
                'user_id' => $user->id,
                'body' => $body,
            ]);

            // recipients_total is server-controlled (not fillable), set via a
            // trusted write after we know the count.
            $campaign->forceFill(['recipients_total' => $recipients->count()])->save();

            foreach ($recipients as $conversation) {
                BulkCampaignRecipient::query()->create([
                    'bulk_campaign_id' => $campaign->id,
                    'conversation_id' => $conversation->id,
                    'wa_contact_id' => $conversation->wa_contact_id,
                ]);
            }

            return $campaign;
        });

        // Dispatch one job per recipient onto the database queue. afterCommit so
        // no worker sees a recipient row before the transaction is durable.
        $campaign->recipients()->each(function (BulkCampaignRecipient $recipient) use ($campaign): void {
            SendBulkMessageJob::dispatch($campaign->tenant_id, $recipient->id)->afterCommit();
        });

        return $campaign;
    }

    /**
     * The owner's kill switch (§9 reversibility): mark the campaign stopped so
     * every still-queued job exits without sending. status is server-controlled
     * (not fillable), written via a trusted save (§13).
     */
    public function stop(BulkCampaign $campaign): void
    {
        $campaign->forceFill(['status' => BulkCampaign::STATUS_STOPPED])->save();
    }
}
