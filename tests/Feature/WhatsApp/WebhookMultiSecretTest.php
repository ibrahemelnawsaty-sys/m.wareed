<?php

declare(strict_types=1);

use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use App\Services\AI\FallbackReplyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/*
 * Phase 8 — multi-app webhook verification. Each tenant runs its OWN Meta app,
 * so the X-Hub-Signature-256 is signed with that tenant's app secret. The
 * middleware selects the secret by the payload's phone_number_id (untrusted,
 * used only to pick the key), falling back to the platform secret when the
 * tenant has none. A wrong secret — including an attacker's — is rejected (§11).
 */

uses(RefreshDatabase::class);

const PLATFORM_SECRET = 'platform-app-secret-shhh';

beforeEach(function () {
    config()->set('services.whatsapp.app_secret', PLATFORM_SECRET);
    config()->set('services.whatsapp.verify_token', 'verify-token');
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.OUT_'.uniqid()]],
        ], 200),
    ]);

    // Deterministic, zero-cost reply — these tests exercise signature routing.
    app()->bind(BotReplyService::class, FallbackReplyService::class);
    app(TenantContext::class)->forget();
});

function rawPayload(string $phoneNumberId, string $waMessageId): string
{
    return json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA_ID',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '966500000000', 'phone_number_id' => $phoneNumberId],
                    'contacts' => [['wa_id' => '966511111111', 'profile' => ['name' => 'Tester']]],
                    'messages' => [[
                        'from' => '966511111111',
                        'id' => $waMessageId,
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'مرحبا'],
                    ]],
                ],
            ]],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function postSigned(string $raw, string $secret)
{
    return test()->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, $secret),
    ], $raw);
}

// 1) A tenant with its OWN app secret: signing with that secret is accepted.
it('verifies a tenant message against the tenant app secret', function () {
    WhatsappAccount::factory()->create([
        'phone_number_id' => 'PNID_TENANT',
        'app_secret' => 'tenant-own-secret',
    ]);
    app(TenantContext::class)->forget();

    $raw = rawPayload('PNID_TENANT', 'wamid.T1');

    postSigned($raw, 'tenant-own-secret')->assertOk();
    expect(Message::withoutGlobalScopes()->where('wa_message_id', 'wamid.T1')->exists())->toBeTrue();
});

// 1b) The SAME tenant message signed with the platform secret is rejected —
// the platform secret must NOT verify a tenant that has its own.
it('rejects a tenant message signed with the platform secret when the tenant has its own', function () {
    WhatsappAccount::factory()->create([
        'phone_number_id' => 'PNID_TENANT2',
        'app_secret' => 'tenant-own-secret',
    ]);
    app(TenantContext::class)->forget();

    $raw = rawPayload('PNID_TENANT2', 'wamid.T2');

    postSigned($raw, PLATFORM_SECRET)->assertForbidden();
    expect(Message::withoutGlobalScopes()->count())->toBe(0);
});

// 2) Fallback: a tenant WITHOUT its own secret verifies against the platform
// secret (backward compatible with pre-Phase-8 accounts).
it('falls back to the platform secret when the tenant has no app secret', function () {
    WhatsappAccount::factory()->create([
        'phone_number_id' => 'PNID_FALLBACK',
        'app_secret' => null,
    ]);
    app(TenantContext::class)->forget();

    $raw = rawPayload('PNID_FALLBACK', 'wamid.F1');

    postSigned($raw, PLATFORM_SECRET)->assertOk();
    expect(Message::withoutGlobalScopes()->where('wa_message_id', 'wamid.F1')->exists())->toBeTrue();
});

// 3) Unknown phone_number_id → the platform secret is used; a forged secret is
// still rejected (an attacker choosing a phone_number_id proves nothing).
it('uses the platform secret for an unknown phone_number_id and rejects a forged one', function () {
    $raw = rawPayload('PNID_UNKNOWN', 'wamid.U1');

    postSigned($raw, PLATFORM_SECRET)->assertOk();          // passes the guard (controller then ignores the unknown number)
    postSigned($raw, 'attacker-secret')->assertForbidden(); // wrong secret → rejected
});

// 4) Fail closed: neither a tenant nor a platform secret → 403, nothing stored.
it('fails closed when the chosen secret is empty', function () {
    config()->set('services.whatsapp.app_secret', '');

    WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_NOSECRET', 'app_secret' => null]);
    app(TenantContext::class)->forget();

    $raw = rawPayload('PNID_NOSECRET', 'wamid.N1');

    postSigned($raw, '')->assertForbidden();
    expect(Message::withoutGlobalScopes()->count())->toBe(0);
});
