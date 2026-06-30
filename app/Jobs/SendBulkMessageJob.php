<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BulkCampaign;
use App\Models\BulkCampaignRecipient;
use App\Models\Message;
use App\Services\Bulk\SendQuota;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends ONE bulk-campaign message to ONE recipient (Phase 6d, §11) on the
 * database queue, run by `queue:work --stop-when-empty` from Cron — no permanent
 * worker, no synchronous webhook send (ADR-03).
 *
 * Devil's advocate (§9): a bulk send is the single most dangerous path for the
 * customer's number — over the cap, to a non-opted-in contact, or free-form
 * outside the 24h window all risk a ban. This job is therefore a strict gate, in
 * order: stopped/already-processed → opted out → window closed → cap reached →
 * send. The cap check is the ATOMIC SendQuota::tryConsume, so concurrent jobs can
 * never collectively exceed 250.
 *
 * Isolation (§1): a queued job does NOT inherit the request's tenant context, so
 * it binds the tenant explicitly with TenantContext::run before touching any
 * tenant-scoped model — otherwise the TenantScope would be inert and the lookups
 * could cross tenants. Every model below is loaded INSIDE that bound context.
 *
 * No retry storm (§12): $tries = 1. A failed send is reported once and recorded
 * as failed; it is never retried in a loop that would burn Meta throughput.
 */
class SendBulkMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Exactly one attempt — a failed send must not be retried into a storm (§12).
     */
    public int $tries = 1;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $recipientId,
    ) {}

    /**
     * Bind the tenant, then process the recipient under the bound context.
     */
    public function handle(WhatsAppClient $client, SendQuota $quota, TenantContext $tenant): void
    {
        $tenant->run($this->tenantId, function () use ($client, $quota): void {
            $this->process($client, $quota);
        });
    }

    /**
     * The gated send. Runs with the tenant bound, so every query is
     * TenantScope-filtered (§1).
     */
    private function process(WhatsAppClient $client, SendQuota $quota): void
    {
        /** @var BulkCampaignRecipient|null $recipient */
        $recipient = BulkCampaignRecipient::query()->find($this->recipientId);

        // Vanished (e.g. campaign deleted) — nothing to do.
        if ($recipient === null) {
            return;
        }

        // Already handled (re-dispatch / duplicate) — never send twice.
        if ($recipient->status !== BulkCampaignRecipient::STATUS_PENDING) {
            return;
        }

        $campaign = $recipient->campaign;

        // The owner stopped the campaign (kill switch) — exit without sending.
        if ($campaign === null || $campaign->isStopped()) {
            return;
        }

        $conversation = $recipient->conversation;

        if ($conversation === null) {
            $this->markFailed($recipient, $campaign, 'conversation_missing');

            return;
        }

        // 1) Opt-out: the contact unsubscribed — never message them again (§11).
        if ($conversation->isOptedOut()) {
            $this->markSkipped($recipient, $campaign, BulkCampaignRecipient::STATUS_SKIPPED_OPTOUT);

            return;
        }

        // 2) 24h window: outside it a free-form send is a Meta violation; a
        // template is required (a later phase). Skip — NEVER send free-form (§11).
        if (! $conversation->isWindowOpen()) {
            $this->markSkipped($recipient, $campaign, BulkCampaignRecipient::STATUS_SKIPPED_WINDOW);

            return;
        }

        $account = $campaign->whatsappAccount;

        if ($account === null) {
            $this->markFailed($recipient, $campaign, 'account_missing');

            return;
        }

        // 3) Daily cap: atomically reserve a slot. If the cap is reached this
        // returns false WITHOUT incrementing — the message is NOT sent (§11/§13).
        if (! $quota->tryConsume($account)) {
            $this->markSkipped($recipient, $campaign, BulkCampaignRecipient::STATUS_SKIPPED_CAP);

            return;
        }

        // 4) Send. A reserved slot is already consumed; on send failure we do not
        // refund it (conservative — better to under-send than risk the number).
        try {
            $sent = $client->sendText($account, $conversation->wa_contact_id, $campaign->body);
        } catch (Throwable $e) {
            // Report once; record the failure. No retry loop (§3, §12).
            report($e);
            $this->markFailed($recipient, $campaign, 'send_failed');

            return;
        }

        // Persist the outbound Message (authored by the campaign's owner) so it
        // shows in the inbox just like an agent reply (§5 visible output).
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $campaign->user_id,
            'wa_message_id' => $sent['wa_message_id'],
            'direction' => 'out',
            'type' => 'text',
            'body' => $campaign->body,
            'status' => 'sent',
        ]);

        $recipient->forceFill([
            'status' => BulkCampaignRecipient::STATUS_SENT,
            'wa_message_id' => $sent['wa_message_id'],
        ])->save();

        $campaign->increment('sent_count');

        $this->finaliseIfDone($campaign);
    }

    /**
     * Record a skip outcome on the recipient and bump the campaign's skipped
     * counter (§3: explicit reason, never silent).
     */
    private function markSkipped(BulkCampaignRecipient $recipient, BulkCampaign $campaign, string $status): void
    {
        $recipient->forceFill(['status' => $status])->save();
        $campaign->increment('skipped_count');

        $this->finaliseIfDone($campaign);
    }

    /**
     * Record a failure with its reason and bump the failed counter (§3).
     */
    private function markFailed(BulkCampaignRecipient $recipient, BulkCampaign $campaign, string $reason): void
    {
        $recipient->forceFill([
            'status' => BulkCampaignRecipient::STATUS_FAILED,
            'failed_reason' => $reason,
        ])->save();
        $campaign->increment('failed_count');

        $this->finaliseIfDone($campaign);
    }

    /**
     * Move the campaign to completed once no recipient is still pending, or to
     * sending while work remains. status is server-controlled (forceFill, §13).
     */
    private function finaliseIfDone(BulkCampaign $campaign): void
    {
        // A stopped campaign stays stopped — its final state is the owner's call.
        if ($campaign->isStopped()) {
            return;
        }

        $pending = $campaign->recipients()
            ->where('status', BulkCampaignRecipient::STATUS_PENDING)
            ->exists();

        $campaign->forceFill([
            'status' => $pending ? BulkCampaign::STATUS_SENDING : BulkCampaign::STATUS_COMPLETED,
        ])->save();
    }
}
