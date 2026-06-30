<?php

declare(strict_types=1);

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use App\Services\Inbox\ConversationRouter;
use App\Services\Inbox\HandoffDetector;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Entry point for the Meta WhatsApp Cloud API webhook (ADR-01, §11).
 *
 * - GET  verifies the subscription challenge (hub.verify_token).
 * - POST receives inbound messages; signature is already verified by the
 *   `whatsapp.signature` middleware before this runs.
 *
 * The tenant is resolved from `phone_number_id`; idempotency on
 * `wa_message_id` guards against duplicate delivery before any cost is
 * incurred. Meta is answered 200 quickly; AI/send failures are reported and
 * never crash the webhook (§3).
 */
class WebhookController extends Controller
{
    /**
     * Sent once when a conversation is handed off to a human agent so the
     * customer knows a person will follow up. Kept short and template-safe.
     */
    private const HANDOFF_ACK = 'تم تحويل محادثتك إلى أحد موظفي خدمة العملاء، وسيتواصل معك في أقرب وقت.';

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly BotReplyService $botReply,
        private readonly WhatsAppClient $client,
        private readonly HandoffDetector $handoff,
        private readonly ConversationRouter $router,
    ) {}

    /**
     * GET /whatsapp/webhook — subscription verification handshake.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $configToken = (string) config('services.whatsapp.verify_token');

        // Fail closed if the verify token is not configured (§13).
        if ($configToken === '') {
            abort(403);
        }

        if ($mode === 'subscribe' && is_string($token)
            && hash_equals($configToken, $token)) {
            return response((string) $challenge, 200);
        }

        abort(403);
    }

    /**
     * POST /whatsapp/webhook — process inbound messages.
     */
    public function handle(Request $request): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $value = data_get($payload, 'entry.0.changes.0.value');

        if (! is_array($value)) {
            return $this->ack();
        }

        $phoneNumberId = data_get($value, 'metadata.phone_number_id');

        if (! is_string($phoneNumberId) || $phoneNumberId === '') {
            return $this->ack();
        }

        // No tenant bound yet: TenantScope is inactive, so this lookup is
        // unfiltered and finds the owning account across all tenants.
        $account = WhatsappAccount::query()
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        if ($account === null) {
            // Unknown number — ignore quietly, no error (200).
            return $this->ack();
        }

        // Bot activation gate (§9): a tenant that is pending / suspended / past
        // its subscription end is NOT allowed a live bot. Skip all AI + send
        // work and stay silent — but still ack Meta with 200 as usual so it
        // does not retry. `tenant` has no TenantScope (it is not a tenant-owned
        // model), so this load is safe before binding the context.
        $tenant = $account->tenant;

        if ($tenant === null || ! $tenant->isActive()) {
            return $this->ack();
        }

        // Bind the tenant for the duration of processing, and guarantee it is
        // cleared afterwards (run() restores the prior value in a finally) so
        // tenant state never leaks into a later unit of work in the same
        // process — e.g. a queue worker handling multiple jobs (§3, ADR-02).
        // The WhatsApp profile name of the sender, used to label the inbox row.
        $contactNameRaw = data_get($value, 'contacts.0.profile.name');
        $contactName = is_string($contactNameRaw) ? $contactNameRaw : null;

        $this->tenant->run($account->tenant_id, function () use ($account, $value, $contactName): void {
            $messages = data_get($value, 'messages');

            if (is_array($messages)) {
                foreach ($messages as $message) {
                    if (is_array($message)) {
                        $this->processIncomingMessage($account, $message, $contactName);
                    }
                }
            }
        });

        // Always answer Meta fast so it does not retry (§11).
        return $this->ack();
    }

    /**
     * Handle a single inbound message: idempotency → store → reply → send.
     *
     * @param  array<string, mixed>  $message
     */
    private function processIncomingMessage(WhatsappAccount $account, array $message, ?string $contactName = null): void
    {
        $waMessageId = data_get($message, 'id');

        if (! is_string($waMessageId) || $waMessageId === '') {
            return;
        }

        // Idempotency BEFORE any cost: storage, AI, or send (§3, §11).
        if (Message::query()->where('wa_message_id', $waMessageId)->exists()) {
            return;
        }

        $from = data_get($message, 'from');

        if (! is_string($from) || $from === '') {
            return;
        }

        $incomingText = (string) (data_get($message, 'text.body') ?? '');

        $conversation = $this->resolveConversation($account, $from, $contactName);

        // Persist the inbound message (direction='in'). createOrFirst is
        // race-safe: a concurrent duplicate delivery (Meta retry) that slips
        // past the exists() check above collides on the unique wa_message_id
        // and resolves to the existing row instead of throwing a 500 that
        // would make Meta retry in a loop (§3, §11).
        $inbound = Message::query()->createOrFirst(
            ['wa_message_id' => $waMessageId],
            [
                'conversation_id' => $conversation->id,
                'direction' => 'in',
                'type' => (string) (data_get($message, 'type') ?? 'text'),
                'body' => $incomingText,
                'status' => 'received',
            ],
        );

        // Lost the race — the other request already handled this message.
        if (! $inbound->wasRecentlyCreated) {
            return;
        }

        // Free-form replies are only allowed inside the 24h service window
        // (§11). The inbound message just refreshed it, so this passes here;
        // the guard protects any future deferred/proactive send path.
        if (! $conversation->isWindowOpen()) {
            return;
        }

        // Already in human mode: a live agent owns this thread. The bot stays
        // silent — no AI, no send. The inbound message is stored above and will
        // surface in the agent's inbox for them to answer (§11 handoff).
        if ($conversation->isHumanMode()) {
            return;
        }

        // The customer is explicitly asking for a human. Flip to human mode,
        // send a single courtesy acknowledgement, and skip the AI entirely so
        // we never burn tokens once a handoff is requested (§11, §12).
        if ($this->handoff->wantsHuman($incomingText)) {
            $this->performHandoff($account, $conversation, $from);

            return;
        }

        // Generate + send the reply. Any AI/send failure is reported and
        // contained — the webhook must not crash (§3).
        try {
            $result = $this->botReply->generateReply($account, $conversation, $incomingText);

            $sent = $this->client->sendText($account, $from, $result->reply);

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $sent['wa_message_id'],
                'direction' => 'out',
                'type' => 'text',
                'body' => $result->reply,
                'tokens_in' => $result->tokensIn,
                'tokens_out' => $result->tokensOut,
                'cost_micros' => $result->costMicros,
                'status' => 'sent',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Hand the conversation to the human queue and send one courtesy message.
     * The mode flip persists even if the send fails; the failure is reported
     * and contained so the webhook never crashes (§3).
     *
     * In BALANCED distribution mode the conversation is then auto-assigned to the
     * least-loaded agent under their target (Phase 6c). In CLAIM mode it stays
     * unassigned for any agent to pick up (Phase 6b behaviour, unchanged). The
     * routing is contained: any failure is reported and the conversation simply
     * stays queued — it never crashes the webhook or blocks the courtesy send.
     */
    private function performHandoff(WhatsappAccount $account, Conversation $conversation, string $from): void
    {
        $conversation->handoffToHumans();

        // Balanced mode: route to the least-loaded eligible agent. Contained so a
        // routing hiccup leaves the thread unassigned (still recoverable from the
        // inbox), never breaks the handoff (§3). `tenant` is the bound tenant.
        if ($account->tenant?->isBalancedMode()) {
            try {
                $this->router->assignBestAgent($conversation);
            } catch (Throwable $e) {
                report($e);
            }
        }

        try {
            $sent = $this->client->sendText($account, $from, self::HANDOFF_ACK);

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'wa_message_id' => $sent['wa_message_id'],
                'direction' => 'out',
                'type' => 'text',
                'body' => self::HANDOFF_ACK,
                'status' => 'sent',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Find or create the conversation and refresh the 24h service window (§11).
     * Caches the WhatsApp profile name on first sight (only fills when empty, so
     * a later blank payload never erases a name we already have, §3).
     */
    private function resolveConversation(WhatsappAccount $account, string $from, ?string $contactName = null): Conversation
    {
        $conversation = Conversation::query()->firstOrCreate(
            [
                'whatsapp_account_id' => $account->id,
                'wa_contact_id' => $from,
            ],
            [
                'status' => 'open',
                'window_expires_at' => now()->addHours(24),
            ],
        );

        $attributes = ['window_expires_at' => now()->addHours(24)];

        if (is_string($contactName) && $contactName !== '' && ($conversation->contact_name ?? '') === '') {
            $attributes['contact_name'] = $contactName;
        }

        $conversation->forceFill($attributes)->save();

        return $conversation;
    }

    /**
     * Fast, empty 200 acknowledgement for Meta.
     */
    private function ack(): Response
    {
        return response('', 200);
    }
}
