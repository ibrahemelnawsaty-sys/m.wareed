<?php

declare(strict_types=1);

use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Services\Bulk\TemplateSync;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

/*
| Phase 7c — listTemplates builds the right GET (keyed by waba_id) and never leaks
| the token on failure (§13); TemplateSync upserts status/category/variable_count/
| body from Meta's payload, and re-syncing UPDATES in place rather than duplicating
| (§3 non-destructive).
*/

beforeEach(function () {
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    app(TenantContext::class)->forget();
});

/** A bound tenant + its single account with a known waba_id/token. */
function syncAccount(array $attributes = []): WhatsappAccount
{
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant->id);

    return WhatsappAccount::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'waba_id' => '998877665544332',
        'access_token' => 'EAAG-sync-secret',
    ], $attributes));
}

// 1) listTemplates issues the right GET (keyed by waba_id) and returns normalised rows.
test('listTemplates issues the right GET against the waba_id', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [
                [
                    'name' => 'order_update',
                    'language' => 'ar',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'مرحباً {{1}}، طلبك {{2}} جاهز.'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $account = syncAccount();

    $templates = app(WhatsAppClient::class)->listTemplates($account);

    expect($templates)->toHaveCount(1)
        ->and($templates[0]['name'])->toBe('order_update')
        ->and($templates[0]['status'])->toBe('APPROVED');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_contains($request->url(), '/v21.0/998877665544332/message_templates')
        && str_contains($request->url(), 'fields=name%2Clanguage%2Ccategory%2Cstatus%2Ccomponents')
        && $request->hasHeader('Authorization', 'Bearer EAAG-sync-secret'));
});

// 1b) listTemplates surfaces a clean RuntimeException without leaking the token,
// and with no `previous` chained (the token-bearing request never escapes, §13).
test('listTemplates never leaks the token on failure', function () {
    $secret = 'EAAG-templates-leak-check';

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad']], 400),
    ]);

    $account = syncAccount(['access_token' => $secret]);

    try {
        app(WhatsAppClient::class)->listTemplates($account);
        $this->fail('Expected a RuntimeException.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toContain($secret)
            ->and($e->getMessage())->toContain($account->phone_number_id)
            ->and($e->getPrevious())->toBeNull();
    }
});

// 2) TemplateSync upserts each template: status/category/variable_count/body.
test('TemplateSync upserts templates from Meta', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [
                [
                    'name' => 'order_update',
                    'language' => 'ar',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'HEADER', 'text' => 'إشعار'],
                        ['type' => 'BODY', 'text' => 'مرحباً {{1}}، طلبك {{2}} جاهز.'],
                        ['type' => 'FOOTER', 'text' => 'شكراً'],
                    ],
                ],
                [
                    'name' => 'promo',
                    'language' => 'en_US',
                    'category' => 'MARKETING',
                    'status' => 'PENDING',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Big sale today!'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $account = syncAccount();

    $count = app(TemplateSync::class)->sync($account);

    expect($count)->toBe(2)
        ->and(MessageTemplate::query()->count())->toBe(2);

    $order = MessageTemplate::query()->where('name', 'order_update')->firstOrFail();
    expect($order->status)->toBe('approved')
        ->and($order->category)->toBe('utility')
        ->and($order->variable_count)->toBe(2)
        ->and($order->body_text)->toBe('مرحباً {{1}}، طلبك {{2}} جاهز.')
        ->and($order->isApproved())->toBeTrue()
        ->and($order->last_synced_at)->not->toBeNull();

    $promo = MessageTemplate::query()->where('name', 'promo')->firstOrFail();
    expect($promo->status)->toBe('pending')
        ->and($promo->category)->toBe('marketing')
        ->and($promo->variable_count)->toBe(0)
        ->and($promo->isApproved())->toBeFalse();
});

// 2b) Re-syncing updates the SAME row (the unique key holds), never duplicates.
test('re-syncing updates rather than duplicating', function () {
    $account = syncAccount();

    // Meta returns PENDING first, then APPROVED on the next poll. A SEQUENCE is
    // required: calling Http::fake() again would NOT override the first stub
    // (the earliest matching stub wins), so both syncs would otherwise see
    // PENDING. fakeSequence hands out one response per call, in order.
    Http::fakeSequence('graph.facebook.com/*')
        ->push([
            'data' => [[
                'name' => 'order_update', 'language' => 'ar', 'category' => 'UTILITY',
                'status' => 'PENDING', 'components' => [['type' => 'BODY', 'text' => 'بانتظار {{1}}']],
            ]],
        ], 200)
        ->push([
            'data' => [[
                'name' => 'order_update', 'language' => 'ar', 'category' => 'UTILITY',
                'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'مرحباً {{1}}']],
            ]],
        ], 200);

    app(TemplateSync::class)->sync($account);
    expect(MessageTemplate::query()->count())->toBe(1);
    expect(MessageTemplate::query()->firstOrFail()->status)->toBe('pending');

    // The second sync sees APPROVED and updates the SAME row (no duplicate).
    app(TemplateSync::class)->sync($account);

    expect(MessageTemplate::query()->count())->toBe(1);
    $template = MessageTemplate::query()->firstOrFail();
    expect($template->status)->toBe('approved')
        ->and($template->body_text)->toBe('مرحباً {{1}}')
        ->and($template->variable_count)->toBe(1);
});

// 8-isolation) Sync for tenant A never touches tenant B's templates.
test('sync is isolated per tenant', function () {
    // Tenant B already has a synced template.
    $tenantB = Tenant::factory()->create();
    $accountB = app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        return WhatsappAccount::factory()->create([
            'tenant_id' => $tenantB->id,
            'waba_id' => '111111111111111',
        ]);
    });
    app(TenantContext::class)->run($tenantB->id, function () use ($accountB) {
        MessageTemplate::factory()->create([
            'tenant_id' => $accountB->tenant_id,
            'whatsapp_account_id' => $accountB->id,
            'name' => 'b_only',
            'language' => 'ar',
        ]);
    });

    app(TenantContext::class)->forget();

    // Tenant A syncs its own (different) templates.
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [[
                'name' => 'a_only', 'language' => 'ar', 'category' => 'UTILITY',
                'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'A {{1}}']],
            ]],
        ], 200),
    ]);

    $accountA = syncAccount();
    app(TemplateSync::class)->sync($accountA);

    // A sees only its own.
    expect(MessageTemplate::query()->pluck('name')->all())->toBe(['a_only']);

    // B's template is untouched and still exactly one.
    expect(MessageTemplate::withoutGlobalScopes()->where('name', 'b_only')->count())->toBe(1);
});
