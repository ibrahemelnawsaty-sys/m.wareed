<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use App\Services\AI\FallbackReplyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Self-contained signing so this file does not depend on constants declared in
// WebhookTest.php (file-scoped `const`, not guaranteed to be loaded first).
const HANDOFF_APP_SECRET = 'handoff-test-secret';

/**
 * Build the raw JSON body + matching HMAC the way Meta does.
 *
 * @return array{0: string, 1: string}
 */
function handoffSignedPayload(string $phoneNumberId, string $waMessageId, string $from, string $text, string $name = 'Tester'): array
{
    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA_ID',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '966500000000', 'phone_number_id' => $phoneNumberId],
                    'contacts' => [['wa_id' => $from, 'profile' => ['name' => $name]]],
                    'messages' => [[
                        'from' => $from,
                        'id' => $waMessageId,
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]],
                ],
            ]],
        ]],
    ];

    $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $raw, HANDOFF_APP_SECRET);

    return [$raw, $signature];
}

function postHandoffWebhook(string $raw, string $signature)
{
    return test()->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $raw);
}

beforeEach(function () {
    config()->set('services.whatsapp.app_secret', HANDOFF_APP_SECRET);
    config()->set('services.whatsapp.verify_token', 'handoff-verify-token');
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.OUT_'.uniqid()]],
        ], 200),
    ]);

    // Deterministic, zero-cost reply — asserts the AI path is NOT taken on handoff.
    app()->bind(BotReplyService::class, FallbackReplyService::class);

    app(TenantContext::class)->forget();
});

// 1) A conversation already in human mode: inbound is stored, the bot stays
// silent (no AI reply, no send).
it('stores the inbound message but does not reply when the conversation is in human mode', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_HUMAN']);
    app(TenantContext::class)->set($account->tenant_id);

    $conversation = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000000',
        'window_expires_at' => now()->addHours(24),
    ]);
    // Flip to human via the trusted method (mode is not mass-assignable).
    $conversation->handoffToHumans();

    app(TenantContext::class)->forget();

    [$raw, $signature] = handoffSignedPayload($account->phone_number_id, 'wamid.HUMAN_1', '966500000000', 'مرحبا');
    postHandoffWebhook($raw, $signature)->assertOk();

    // Inbound stored; no outbound at all.
    expect(Message::withoutGlobalScopes()->where('direction', 'in')->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('direction', 'out')->count())->toBe(0);

    Http::assertNothingSent();
});

// 2) A handoff keyword in AI mode flips to human, sends ONE courtesy message,
// records handoff_at, and skips the AI reply.
it('hands off to a human and sends a single courtesy message when the customer asks for an agent', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_HANDOFF']);
    app(TenantContext::class)->forget();

    [$raw, $signature] = handoffSignedPayload($account->phone_number_id, 'wamid.HANDOFF_1', '966511111111', 'أريد التحدث مع موظف');
    postHandoffWebhook($raw, $signature)->assertOk();

    $conversation = Conversation::withoutGlobalScopes()->firstOrFail();

    expect($conversation->mode)->toBe('human')
        ->and($conversation->handoff_at)->not->toBeNull()
        ->and($conversation->assigned_to_user_id)->toBeNull();

    // Exactly one outbound: the courtesy message (no AI reply on top of it).
    $out = Message::withoutGlobalScopes()->where('direction', 'out')->get();
    expect($out)->toHaveCount(1)
        ->and($out->first()->user_id)->toBeNull()
        ->and($out->first()->status)->toBe('sent');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains((string) $request['text']['body'], 'خدمة العملاء'));
});

// 3) The WhatsApp profile name from the payload is cached on the conversation.
it('stores the contact name from the webhook payload', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_NAME']);
    app(TenantContext::class)->forget();

    [$raw, $signature] = handoffSignedPayload($account->phone_number_id, 'wamid.NAME_1', '966522222222', 'هل لديكم توصيل؟');
    postHandoffWebhook($raw, $signature)->assertOk();

    $conversation = Conversation::withoutGlobalScopes()->firstOrFail();
    // signedPayload sets contacts.0.profile.name = 'Tester'.
    expect($conversation->contact_name)->toBe('Tester');
});
