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
        // Enforced for BOTH free-form and template sends — opt-out is absolute.
        if ($conversation->isOptedOut()) {
            $this->markSkipped($recipient, $campaign, BulkCampaignRecipient::STATUS_SKIPPED_OPTOUT);

            return;
        }

        $usesTemplate = $campaign->usesTemplate();

        // 2) 24h window: for a FREE-FORM send, outside the window is a Meta
        // violation, so skip — NEVER send free-form (§11). A TEMPLATE send is
        // exactly what is allowed outside the window (its whole purpose), so the
        // window check is skipped for templates — opt-out + the atomic cap still
        // fully apply below.
        if (! $usesTemplate && ! $conversation->isWindowOpen()) {
            $this->markSkipped($recipient, $campaign, BulkCampaignRecipient::STATUS_SKIPPED_WINDOW);

            return;
        }

        $account = $campaign->whatsappAccount;

        if ($account === null) {
            $this->markFailed($recipient, $campaign, 'account_missing');

            return;
        }

        // 3) Template approval — the BAN-CRITICAL re-check (§11, §9 devil's
        // advocate). The bulk send is ASYNC (ADR-03: queued jobs run later via
        // Cron), so a template that was approved when the campaign was created
        // can be rejected / paused / disabled by Meta before this job runs.
        // Sending a NON-approved template — and templates deliberately reach the
        // contact OUTSIDE the 24h window — is a direct path to a number ban. The
        // create-time guard is not enough; re-check the LIVE status here, and do
        // it BEFORE consuming a cap slot so an un-approved campaign wastes none.
        // $template stays null for a free-form campaign (the send branch below
        // keys on it), and is a currently-approved template otherwise.
        $template = null;

        if ($usesTemplate) {
            $template = $campaign->template;

            if ($template === null) {
                // Deleted after the campaign was queued — fail explicitly (§3).
                $this->markFailed($recipient, $campaign, 'template_missing');

                return;
            }

            if (! $template->isApproved()) {
                // No longer approved — skip, never send (§11). Not a failure: the
                // owner can re-sync and re-send once Meta re-approves it.
                $this->markSkipped($recipient, $campaign, BulkCampaignRecipient::STATUS_SKIPPED_TEMPLATE);

                return;
            }
        }

        // 4) Daily cap: atomically reserve a slot. If the cap is reached this
        // returns false WITHOUT incrementing — the message is NOT sent (§11/§13).
        // Applies identically to template and free-form sends.
        if (! $quota->tryConsume($account)) {
            $this->markSkipped($recipient, $campaign, BulkCampaignRecipient::STATUS_SKIPPED_CAP);

            return;
        }

        // 5) Send. A reserved slot is already consumed; on send failure we do not
        // refund it (conservative — better to under-send than risk the number).
        // A non-null $template means an approved template send; otherwise free-form.
        try {
            if ($template !== null) {
                $sent = $client->sendTemplate(
                    $account,
                    $conversation->wa_contact_id,
                    $template->name,
                    $template->language,
                    $this->templateComponents($campaign),
                );
            } else {
                $sent = $client->sendText($account, $conversation->wa_contact_id, $campaign->body);
            }
        } catch (Throwable $e) {
            // Report once; record the failure. No retry loop (§3, §12).
            report($e);
            $this->markFailed($recipient, $campaign, 'send_failed');

            return;
        }

        // Persist the outbound Message (authored by the campaign's owner) so it
        // shows in the inbox just like an agent reply (§5 visible output). The
        // body is the campaign's stored copy (rendered template text, or free text)
        // and the type records which channel was used.
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $campaign->user_id,
            'wa_message_id' => $sent['wa_message_id'],
            'direction' => 'out',
            'type' => $usesTemplate ? 'template' : 'text',
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
     * Build the Cloud API `components` for a template send from the campaign's
     * stored positional variables. With no variables an EMPTY array is returned, so
     * a parameter-less template ships no `components` block at all (the client omits
     * an empty array). Each variable becomes a body text parameter, in order — the
     * count was already verified to match the template at create time (§11).
     *
     * @return array<int, mixed>
     */
    private function templateComponents(BulkCampaign $campaign): array
    {
        $variables = $campaign->template_variables ?? [];

        if ($variables === []) {
            return [];
        }

        $parameters = [];

        foreach ($variables as $value) {
            $parameters[] = ['type' => 'text', 'text' => (string) $value];
        }

        return [[
            'type' => 'body',
            'parameters' => $parameters,
        ]];
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
