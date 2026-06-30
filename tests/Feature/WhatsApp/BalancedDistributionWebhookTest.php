<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use App\Services\AI\FallbackReplyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Phase 6c — conversation distribution modes, exercised end-to-end through the
| live webhook (§10: verify the customer-facing outcome, not just the tables).
| In BALANCED mode a handoff auto-assigns to the least-loaded agent under target;
| in CLAIM mode the thread stays unassigned for any agent to pick up.
*/

// Self-contained signing (file-scoped const, independent of other webhook files).
const BALANCED_APP_SECRET = 'balanced-test-secret';

/**
 * @return array{0: string, 1: string}
 */
function balancedSignedPayload(string $phoneNumberId, string $waMessageId, string $from, string $text): array
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
    $signature = 'sha256='.hash_hmac('sha256', $raw, BALANCED_APP_SECRET);

    return [$raw, $signature];
}

function postBalancedWebhook(string $raw, string $signature)
{
    return test()->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $raw);
}

beforeEach(function () {
    config()->set('services.whatsapp.app_secret', BALANCED_APP_SECRET);
    config()->set('services.whatsapp.verify_token', 'balanced-verify-token');
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.OUT_'.uniqid()]],
        ], 200),
    ]);

    // Deterministic, zero-cost reply (the AI path is never taken on handoff).
    app()->bind(BotReplyService::class, FallbackReplyService::class);

    app(TenantContext::class)->forget();
});

/**
 * An active tenant in balanced mode with $agentCount agents (default quota), plus
 * the WhatsApp account. Returns [account, agents (ordered by id)].
 *
 * @return array{0: WhatsappAccount, 1: array<int, User>}
 */
function balancedTenant(int $agentCount = 2, int $defaultQuota = 5): array
{
    $account = WhatsappAccount::factory()->create(['status' => 'active']);
    $tenant = $account->tenant;
    $tenant->activate();
    $tenant->setDistribution('balanced', $defaultQuota);

    app(TenantContext::class)->set($tenant->id);

    $agents = [];
    for ($i = 0; $i < $agentCount; $i++) {
        $agents[] = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'agent',
        ]);
    }

    app(TenantContext::class)->forget();

    return [$account, $agents];
}

// 1) Balanced: a handoff keyword auto-assigns to the least-loaded agent.
it('auto-assigns a handed-off conversation to the least-loaded agent in balanced mode', function () {
    [$account, $agents] = balancedTenant(agentCount: 2, defaultQuota: 5);
    [$agentA, $agentB] = $agents;

    // Give agent A one open conversation so agent B is the lighter one.
    app(TenantContext::class)->set($account->tenant_id);
    $existing = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000111',
    ]);
    $existing->claimBy($agentA); // A now has load 1, B has 0
    app(TenantContext::class)->forget();

    [$raw, $sig] = balancedSignedPayload($account->phone_number_id, 'wamid.B1', '966500000222', 'أريد موظف');
    postBalancedWebhook($raw, $sig)->assertOk();

    $convo = Conversation::withoutGlobalScopes()
        ->where('wa_contact_id', '966500000222')
        ->firstOrFail();

    expect($convo->mode)->toBe('human')
        ->and($convo->assigned_to_user_id)->toBe($agentB->id) // the lighter agent
        ->and($convo->handoff_at)->not->toBeNull();
});

// 2) Balanced: every agent is already at their target → stays unassigned (queue),
// no 500, courtesy message still sent.
it('leaves the conversation unassigned when every agent is at capacity in balanced mode', function () {
    [$account, $agents] = balancedTenant(agentCount: 1, defaultQuota: 1);
    [$agentA] = $agents;

    // Fill agent A to their quota of 1.
    app(TenantContext::class)->set($account->tenant_id);
    $full = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000333',
    ]);
    $full->claimBy($agentA); // A now at 1/1 — at capacity
    app(TenantContext::class)->forget();

    [$raw, $sig] = balancedSignedPayload($account->phone_number_id, 'wamid.B2', '966500000444', 'تحويل');
    postBalancedWebhook($raw, $sig)->assertOk();

    $convo = Conversation::withoutGlobalScopes()
        ->where('wa_contact_id', '966500000444')
        ->firstOrFail();

    expect($convo->mode)->toBe('human')
        ->and($convo->assigned_to_user_id)->toBeNull(); // queued, not forced onto a full agent

    // Courtesy message still went out (handoff is not blocked by full agents).
    Http::assertSent(fn ($request) => str_contains((string) $request['text']['body'], 'خدمة العملاء'));
});

// 3) Claim mode (default): a handoff stays unassigned even with idle agents.
it('keeps a handed-off conversation unassigned in claim mode', function () {
    $account = WhatsappAccount::factory()->create(['status' => 'active']);
    $tenant = $account->tenant;
    $tenant->activate();
    // Tenant defaults to 'claim'; assert that explicitly.
    expect($tenant->isBalancedMode())->toBeFalse();

    app(TenantContext::class)->set($tenant->id);
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);
    app(TenantContext::class)->forget();

    [$raw, $sig] = balancedSignedPayload($account->phone_number_id, 'wamid.B3', '966500000555', 'مندوب');
    postBalancedWebhook($raw, $sig)->assertOk();

    $convo = Conversation::withoutGlobalScopes()
        ->where('wa_contact_id', '966500000555')
        ->firstOrFail();

    expect($convo->mode)->toBe('human')
        ->and($convo->assigned_to_user_id)->toBeNull(); // agents claim manually
});
