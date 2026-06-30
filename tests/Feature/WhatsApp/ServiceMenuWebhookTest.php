<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceMenu;
use App\Models\ServiceMenuRow;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use App\Services\AI\FallbackReplyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Self-contained signing so this file does not depend on constants from other
// webhook test files (file-scoped `const`, load order not guaranteed).
const MENU_APP_SECRET = 'menu-test-secret';

/**
 * Build a signed TEXT webhook payload.
 *
 * @return array{0: string, 1: string}
 */
function menuTextPayload(string $phoneNumberId, string $waMessageId, string $from, string $text): array
{
    $payload = menuEnvelope($phoneNumberId, $from, [
        'from' => $from,
        'id' => $waMessageId,
        'timestamp' => (string) now()->timestamp,
        'type' => 'text',
        'text' => ['body' => $text],
    ]);

    return menuSign($payload);
}

/**
 * Build a signed INTERACTIVE list_reply webhook payload (a tapped row).
 *
 * @return array{0: string, 1: string}
 */
function menuListReplyPayload(string $phoneNumberId, string $waMessageId, string $from, string $rowKey, string $title = 'خيار'): array
{
    $payload = menuEnvelope($phoneNumberId, $from, [
        'from' => $from,
        'id' => $waMessageId,
        'timestamp' => (string) now()->timestamp,
        'type' => 'interactive',
        'interactive' => [
            'type' => 'list_reply',
            'list_reply' => ['id' => $rowKey, 'title' => $title],
        ],
    ]);

    return menuSign($payload);
}

/**
 * @param  array<string, mixed>  $message
 * @return array<string, mixed>
 */
function menuEnvelope(string $phoneNumberId, string $from, array $message): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA_ID',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '966500000000', 'phone_number_id' => $phoneNumberId],
                    'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Tester']]],
                    'messages' => [$message],
                ],
            ]],
        ]],
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array{0: string, 1: string}
 */
function menuSign(array $payload): array
{
    $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $raw, MENU_APP_SECRET);

    return [$raw, $signature];
}

function postMenuWebhook(string $raw, string $signature)
{
    return test()->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $raw);
}

/** Create an enabled menu with a reply row + a handoff row for $account's tenant. */
function seedMenu(WhatsappAccount $account, bool $enabled = true, bool $triggerOnWelcome = true): ServiceMenu
{
    $menu = ServiceMenu::factory()->create([
        'tenant_id' => $account->tenant_id,
        'enabled' => $enabled,
        'trigger_on_welcome' => $triggerOnWelcome,
        'header' => 'مرحباً',
        'body' => 'اختر خدمة',
        'button_label' => 'الخدمات',
    ]);

    ServiceMenuRow::factory()->reply('هذه ساعات العمل: 9-5.')->create([
        'tenant_id' => $account->tenant_id,
        'service_menu_id' => $menu->id,
        'row_key' => 'row_0',
        'title' => 'ساعات العمل',
        'sort_order' => 0,
    ]);

    ServiceMenuRow::factory()->handoff()->create([
        'tenant_id' => $account->tenant_id,
        'service_menu_id' => $menu->id,
        'row_key' => 'row_1',
        'title' => 'تحدث مع موظف',
        'sort_order' => 1,
    ]);

    return $menu;
}

beforeEach(function () {
    config()->set('services.whatsapp.app_secret', MENU_APP_SECRET);
    config()->set('services.whatsapp.verify_token', 'menu-verify-token');
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.OUT_'.uniqid()]],
        ], 200),
    ]);

    // Deterministic zero-cost reply — asserts the AI path is NOT taken when the
    // menu handles the message.
    app()->bind(BotReplyService::class, FallbackReplyService::class);

    app(TenantContext::class)->forget();
});

// 2) A "menu" keyword in AI mode sends the interactive List and skips the AI.
it('sends the interactive list when the customer asks for the menu and skips the AI', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_MENU_KW']);
    seedMenu($account);
    app(TenantContext::class)->forget();

    [$raw, $sig] = menuTextPayload($account->phone_number_id, 'wamid.KW_1', '966500000001', 'القائمة');
    postMenuWebhook($raw, $sig)->assertOk();

    // One interactive list went out, shaped correctly, with both rows.
    Http::assertSent(function ($request) {
        return $request['type'] === 'interactive'
            && $request['interactive']['type'] === 'list'
            && $request['interactive']['action']['button'] === 'الخدمات'
            && count($request['interactive']['action']['sections'][0]['rows']) === 2
            && $request['interactive']['action']['sections'][0]['rows'][0]['id'] === 'row_0';
    });
    Http::assertSentCount(1);

    // Stored as an outbound 'interactive' message; no AI text reply.
    $out = Message::withoutGlobalScopes()->where('direction', 'out')->get();
    expect($out)->toHaveCount(1)
        ->and($out->first()->type)->toBe('interactive');
});

// 3) First message of a new conversation + trigger_on_welcome => menu is sent.
it('sends the menu on the first message of a new conversation when welcome trigger is on', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_WELCOME']);
    seedMenu($account, enabled: true, triggerOnWelcome: true);
    app(TenantContext::class)->forget();

    [$raw, $sig] = menuTextPayload($account->phone_number_id, 'wamid.W_1', '966500000002', 'مرحبا');
    postMenuWebhook($raw, $sig)->assertOk();

    Http::assertSent(fn ($request) => $request['type'] === 'interactive');
    Http::assertSentCount(1);
});

it('does NOT send the welcome menu when trigger_on_welcome is off and no keyword', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_NOWELCOME']);
    seedMenu($account, enabled: true, triggerOnWelcome: false);
    app(TenantContext::class)->forget();

    [$raw, $sig] = menuTextPayload($account->phone_number_id, 'wamid.NW_1', '966500000003', 'مرحبا');
    postMenuWebhook($raw, $sig)->assertOk();

    // No interactive list — the AI (fallback) replies with text instead.
    Http::assertSent(fn ($request) => ($request['type'] ?? null) === 'text');
    $out = Message::withoutGlobalScopes()->where('direction', 'out')->get();
    expect($out)->toHaveCount(1)->and($out->first()->type)->toBe('text');
});

// 4) A list reply on a 'reply' row sends reply_text, skips AI, and does NOT
// re-send the menu (no loop, §9).
it('sends the canned reply for a reply row and never re-sends the menu', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_REPLY']);
    seedMenu($account);
    app(TenantContext::class)->forget();

    [$raw, $sig] = menuListReplyPayload($account->phone_number_id, 'wamid.R_1', '966500000004', 'row_0', 'ساعات العمل');
    postMenuWebhook($raw, $sig)->assertOk();

    // Exactly one outbound: the canned reply TEXT. No interactive list (no loop).
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['type'] === 'text'
        && str_contains((string) $request['text']['body'], 'ساعات العمل: 9-5'));

    $out = Message::withoutGlobalScopes()->where('direction', 'out')->get();
    expect($out)->toHaveCount(1)
        ->and($out->first()->type)->toBe('text')
        ->and($out->first()->body)->toContain('ساعات العمل: 9-5');
});

// 5) A list reply on a 'handoff' row flips to human + courtesy, skips AI.
it('hands off to a human when a handoff row is tapped', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_HOFF']);
    seedMenu($account);
    app(TenantContext::class)->forget();

    [$raw, $sig] = menuListReplyPayload($account->phone_number_id, 'wamid.H_1', '966500000005', 'row_1', 'تحدث مع موظف');
    postMenuWebhook($raw, $sig)->assertOk();

    $conversation = Conversation::withoutGlobalScopes()->firstOrFail();
    expect($conversation->mode)->toBe('human')
        ->and($conversation->handoff_at)->not->toBeNull();

    // One courtesy message; no interactive list, no AI reply.
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['type'] === 'text'
        && str_contains((string) $request['text']['body'], 'خدمة العملاء'));
});

// 6) A disabled menu: "القائمة" takes the normal AI path, no list.
it('takes the AI path when the menu is disabled', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_DISABLED']);
    seedMenu($account, enabled: false);
    app(TenantContext::class)->forget();

    [$raw, $sig] = menuTextPayload($account->phone_number_id, 'wamid.D_1', '966500000006', 'القائمة');
    postMenuWebhook($raw, $sig)->assertOk();

    // The fallback AI replied with text; no interactive list at all.
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => ($request['type'] ?? null) === 'text');
});

// 7) Human mode: neither the menu nor the AI runs; the agent handles it.
it('stays silent in human mode for any message type', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_HUMAN_MENU']);
    seedMenu($account);
    app(TenantContext::class)->set($account->tenant_id);

    $conversation = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000007',
        'window_expires_at' => now()->addHours(24),
    ]);
    $conversation->handoffToHumans();
    app(TenantContext::class)->forget();

    [$raw, $sig] = menuTextPayload($account->phone_number_id, 'wamid.HM_1', '966500000007', 'القائمة');
    postMenuWebhook($raw, $sig)->assertOk();

    // Inbound stored; nothing sent.
    Http::assertNothingSent();
    expect(Message::withoutGlobalScopes()->where('direction', 'out')->count())->toBe(0);
});

// Isolation (§1): a list_reply id that belongs to ANOTHER tenant's row must not
// match — it falls through to the safe AI default, never another tenant's reply.
it('does not match a row_key from a different tenant', function () {
    $accountA = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_TENANT_A']);
    seedMenu($accountA);

    // Tenant B has its own menu with the SAME row_key 'row_0' but a different reply.
    $accountB = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_TENANT_B']);
    $menuB = ServiceMenu::factory()->create(['tenant_id' => $accountB->tenant_id]);
    ServiceMenuRow::factory()->reply('سر المستأجر ب')->create([
        'tenant_id' => $accountB->tenant_id,
        'service_menu_id' => $menuB->id,
        'row_key' => 'row_0',
        'title' => 'خاص',
        'sort_order' => 0,
    ]);
    app(TenantContext::class)->forget();

    // Customer of tenant A taps row_0 — must get TENANT A's reply, never B's.
    [$raw, $sig] = menuListReplyPayload($accountA->phone_number_id, 'wamid.ISO_1', '966500000008', 'row_0', 'ساعات العمل');
    postMenuWebhook($raw, $sig)->assertOk();

    Http::assertSent(fn ($request) => str_contains((string) ($request['text']['body'] ?? ''), 'ساعات العمل: 9-5'));
    Http::assertSent(fn ($request) => ! str_contains((string) ($request['text']['body'] ?? ''), 'سر المستأجر ب'));
});
