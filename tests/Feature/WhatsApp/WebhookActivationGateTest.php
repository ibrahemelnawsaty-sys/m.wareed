<?php

declare(strict_types=1);

use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use App\Services\AI\FallbackReplyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| §9 bot activation gate: the webhook must stay completely silent (no AI, no
| outbound send, no stored reply) for any tenant that is not active — pending,
| suspended, or past its subscription — while still acking Meta with 200. An
| active tenant still gets a reply, proving the gate is not over-broad.
*/

const GATE_APP_SECRET = 'gate-test-secret';

beforeEach(function () {
    config()->set('services.whatsapp.app_secret', GATE_APP_SECRET);
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.OUT_'.uniqid()]],
        ], 200),
    ]);

    // Deterministic, zero-cost reply — no real Gemini call.
    app()->bind(BotReplyService::class, FallbackReplyService::class);

    app(TenantContext::class)->forget();
});

/**
 * @return array{0: string, 1: string}
 */
function gateSignedPayload(string $phoneNumberId, string $waMessageId): array
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
                    'contacts' => [['wa_id' => '966599999999', 'profile' => ['name' => 'Tester']]],
                    'messages' => [[
                        'from' => '966599999999',
                        'id' => $waMessageId,
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'مرحبا'],
                    ]],
                ],
            ]],
        ]],
    ];

    $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $raw, GATE_APP_SECRET);

    return [$raw, $signature];
}

function gatePostWebhook(string $raw, string $signature)
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

/**
 * Create an account whose owning tenant is in the given state, with a number
 * linked so the webhook can route to it.
 */
function accountForTenantState(string $phoneNumberId, callable $mutateTenant): WhatsappAccount
{
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $mutateTenant($tenant);

    $account = WhatsappAccount::factory()->create([
        'tenant_id' => $tenant->id,
        'phone_number_id' => $phoneNumberId,
    ]);

    app(TenantContext::class)->forget();

    return $account;
}

it('stays silent for a pending tenant', function () {
    $account = accountForTenantState('PNID_PENDING', fn (Tenant $t) => $t->forceFill(['status' => 'pending'])->save());

    [$raw, $sig] = gateSignedPayload($account->phone_number_id, 'wamid.PENDING_1');
    gatePostWebhook($raw, $sig)->assertOk();

    expect(Message::withoutGlobalScopes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('stays silent for a suspended tenant', function () {
    $account = accountForTenantState('PNID_SUSPENDED', fn (Tenant $t) => $t->suspend());

    [$raw, $sig] = gateSignedPayload($account->phone_number_id, 'wamid.SUSPENDED_1');
    gatePostWebhook($raw, $sig)->assertOk();

    expect(Message::withoutGlobalScopes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('stays silent for a tenant with an expired subscription', function () {
    $account = accountForTenantState(
        'PNID_EXPIRED',
        fn (Tenant $t) => $t->forceFill(['subscription_ends_at' => now()->subDay()])->save(),
    );

    [$raw, $sig] = gateSignedPayload($account->phone_number_id, 'wamid.EXPIRED_1');
    gatePostWebhook($raw, $sig)->assertOk();

    expect(Message::withoutGlobalScopes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('replies for an active tenant within its subscription', function () {
    $account = accountForTenantState(
        'PNID_ACTIVE',
        fn (Tenant $t) => $t->setSubscriptionMonths(1),
    );

    [$raw, $sig] = gateSignedPayload($account->phone_number_id, 'wamid.ACTIVE_1');
    gatePostWebhook($raw, $sig)->assertOk();

    // Inbound stored + outbound reply sent.
    expect(Message::withoutGlobalScopes()->where('direction', 'in')->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('direction', 'out')->count())->toBe(1);
    Http::assertSentCount(1);
});
