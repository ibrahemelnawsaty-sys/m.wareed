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

const TEST_APP_SECRET = 'test-app-secret-shhh';
const TEST_VERIFY_TOKEN = 'test-verify-token';

beforeEach(function () {
    config()->set('services.whatsapp.app_secret', TEST_APP_SECRET);
    config()->set('services.whatsapp.verify_token', TEST_VERIFY_TOKEN);
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    // No real outbound calls; default fake returns a Cloud API message id.
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.OUT_'.uniqid()]],
        ], 200),
    ]);

    // These tests exercise webhook routing/idempotency/tenancy, not the AI.
    // Bind the deterministic, zero-cost fallback so the reply is predictable
    // (the account system_prompt) and no Gemini HTTP call is made here. The
    // real Gemini path is covered by tests/Feature/AI/GeminiReplyServiceTest.
    app()->bind(BotReplyService::class, FallbackReplyService::class);

    // Webhook entry resolves its own tenant; tests start with none bound.
    app(TenantContext::class)->forget();
});

/**
 * Build the raw JSON body + signature the way Meta does, so the middleware
 * sees a body whose HMAC matches exactly.
 *
 * @return array{0: string, 1: string}
 */
function signedPayload(string $phoneNumberId, string $waMessageId, string $from = '966500000000', string $text = 'مرحبا'): array
{
    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA_ID',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => [
                        'display_phone_number' => '966500000000',
                        'phone_number_id' => $phoneNumberId,
                    ],
                    'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Tester']]],
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
    $signature = 'sha256='.hash_hmac('sha256', $raw, TEST_APP_SECRET);

    return [$raw, $signature];
}

/**
 * POST the raw body with the given signature header.
 */
function postWebhook(string $raw, string $signature)
{
    return test()->call(
        'POST',
        '/api/whatsapp/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ],
        $raw,
    );
}

// 1) GET verification handshake.
it('returns the challenge when the verify token is correct', function () {
    $response = $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token='.TEST_VERIFY_TOKEN.'&hub.challenge=CHALLENGE_123');

    $response->assertOk();
    expect($response->getContent())->toBe('CHALLENGE_123');
});

it('rejects the handshake when the verify token is wrong', function () {
    $response = $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=WRONG&hub.challenge=CHALLENGE_123');

    $response->assertForbidden();
});

// 2) Bad signature → 403, nothing stored.
it('rejects a POST with an invalid signature and stores nothing', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_BAD_SIG']);
    app(TenantContext::class)->forget();

    [$raw] = signedPayload($account->phone_number_id, 'wamid.BADSIG');

    $response = postWebhook($raw, 'sha256=deadbeef');

    $response->assertForbidden();
    expect(Message::withoutGlobalScopes()->count())->toBe(0);
    Http::assertNothingSent();
});

// 3) Valid signature + new message → inbound + outbound stored, reply sent.
it('stores inbound and outbound messages and sends a reply for a valid new message', function () {
    $account = WhatsappAccount::factory()->create([
        'phone_number_id' => 'PNID_GOOD',
        'system_prompt' => 'أهلاً بك في متجرنا.',
    ]);
    app(TenantContext::class)->forget();

    [$raw, $signature] = signedPayload($account->phone_number_id, 'wamid.NEW_1', '966511111111', 'هل لديكم توصيل؟');

    $response = postWebhook($raw, $signature);

    $response->assertOk();

    $messages = Message::withoutGlobalScopes()->get();
    expect($messages)->toHaveCount(2);

    $in = $messages->firstWhere('direction', 'in');
    $out = $messages->firstWhere('direction', 'out');

    expect($in)->not->toBeNull()
        ->and($in->wa_message_id)->toBe('wamid.NEW_1')
        ->and($in->body)->toBe('هل لديكم توصيل؟')
        ->and($in->status)->toBe('received')
        ->and($out)->not->toBeNull()
        ->and($out->direction)->toBe('out')
        ->and($out->body)->toBe(FallbackReplyService::DEFAULT_GREETING)
        ->and($out->status)->toBe('sent');

    // Reply actually dispatched to the Cloud API for this account/recipient.
    Http::assertSent(function ($request) use ($account) {
        return str_contains($request->url(), "/{$account->phone_number_id}/messages")
            && $request['to'] === '966511111111'
            && $request['type'] === 'text'
            && $request['text']['body'] === FallbackReplyService::DEFAULT_GREETING;
    });

    // The conversation window was (re)opened for 24h.
    $conversation = Conversation::withoutGlobalScopes()->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->wa_contact_id)->toBe('966511111111')
        ->and($conversation->window_expires_at->greaterThan(now()->addHours(23)))->toBeTrue();
});

// 4) Duplicate wa_message_id → stored once (idempotency).
it('ignores a duplicate wa_message_id and stores the message only once', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_DUP']);
    app(TenantContext::class)->forget();

    [$raw, $signature] = signedPayload($account->phone_number_id, 'wamid.DUP_1');

    postWebhook($raw, $signature)->assertOk();
    app(TenantContext::class)->forget();
    postWebhook($raw, $signature)->assertOk();

    // First delivery: 1 in + 1 out. Duplicate must add nothing.
    expect(Message::withoutGlobalScopes()->where('wa_message_id', 'wamid.DUP_1')->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('direction', 'in')->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('direction', 'out')->count())->toBe(1);

    // Only the first delivery sent a reply; the duplicate sent nothing more.
    Http::assertSentCount(1);
});

// 5) Tenant routing: a message to tenant A's number creates no data for B.
it('routes the message to the owning tenant only', function () {
    $accountA = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_A']);
    $accountB = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_B']);
    app(TenantContext::class)->forget();

    expect($accountA->tenant_id)->not->toBe($accountB->tenant_id);

    [$raw, $signature] = signedPayload($accountA->phone_number_id, 'wamid.TENANT_A', '966522222222');

    postWebhook($raw, $signature)->assertOk();

    // All data created belongs to tenant A; tenant B is untouched.
    $tenantIds = Message::withoutGlobalScopes()->pluck('tenant_id')->unique();
    expect($tenantIds->all())->toBe([$accountA->tenant_id]);

    expect(Conversation::withoutGlobalScopes()->where('tenant_id', $accountB->tenant_id)->count())->toBe(0)
        ->and(Conversation::withoutGlobalScopes()->where('tenant_id', $accountA->tenant_id)->count())->toBe(1);
});

// 6) Empty app secret → reject (fail closed). M-1.
it('rejects a POST when the app secret is not configured', function () {
    config()->set('services.whatsapp.app_secret', '');

    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_NOSECRET']);
    app(TenantContext::class)->forget();

    // An attacker who knows the empty secret can compute a "valid" HMAC.
    $raw = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
    $forged = 'sha256='.hash_hmac('sha256', $raw, '');

    postWebhook($raw, $forged)->assertForbidden();
    expect(Message::withoutGlobalScopes()->count())->toBe(0);
});

// 7) Tenant context is cleared after handling — no state leak (B-1).
it('clears the tenant context after handling so no state leaks', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_CTX']);
    app(TenantContext::class)->forget();

    [$raw, $signature] = signedPayload($account->phone_number_id, 'wamid.CTX_1');

    postWebhook($raw, $signature)->assertOk();

    expect(app(TenantContext::class)->has())->toBeFalse();
});

// 8) Race-safe idempotency: an already-stored inbound id is not re-processed (H-1).
it('does not reply again when the inbound message already exists', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_RACE']);
    app(TenantContext::class)->set($account->tenant_id);

    $conversation = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000000',
        'window_expires_at' => now()->addHours(24),
    ]);

    Message::factory()->create([
        'tenant_id' => $account->tenant_id,
        'conversation_id' => $conversation->id,
        'wa_message_id' => 'wamid.RACE_1',
        'direction' => 'in',
    ]);

    app(TenantContext::class)->forget();

    [$raw, $signature] = signedPayload($account->phone_number_id, 'wamid.RACE_1');
    postWebhook($raw, $signature)->assertOk();

    expect(Message::withoutGlobalScopes()->where('wa_message_id', 'wamid.RACE_1')->count())->toBe(1);
    Http::assertNothingSent();
});

// 9) 24h window guard reflects window_expires_at (H-2, §11).
it('treats the conversation as outside the window once it has expired', function () {
    $account = WhatsappAccount::factory()->create();
    app(TenantContext::class)->set($account->tenant_id);

    $open = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'window_expires_at' => now()->addHour(),
    ]);

    $expired = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'window_expires_at' => now()->subMinute(),
    ]);

    expect($open->isWindowOpen())->toBeTrue()
        ->and($expired->isWindowOpen())->toBeFalse();

    app(TenantContext::class)->forget();
});
