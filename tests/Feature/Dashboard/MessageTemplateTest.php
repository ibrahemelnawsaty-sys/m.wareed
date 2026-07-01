<?php

declare(strict_types=1);

use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

/*
| Phase 7c — the templates management surface. Owner-only (§13), TenantScope
| isolation (§1), the Meta sync, and the manual-add fallback (status server-
| controlled, variable_count derived from the body, §13).
*/

beforeEach(function () {
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    app(TenantContext::class)->forget();
});

test('the owner can open the templates page', function () {
    $owner = provisionTenant(['waba_id' => '123123123123123']);

    $this->actingAs($owner)->get(route('templates.index'))->assertOk();
});

// Owner-only: an agent gets 403 on every template route.
test('an agent is forbidden from the templates routes', function () {
    $owner = provisionTenant();
    $agent = User::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'role' => 'agent',
        'email_verified_at' => now(),
    ]);
    app(TenantContext::class)->forget();

    $this->actingAs($agent)->get(route('templates.index'))->assertForbidden();
    $this->actingAs($agent)->post(route('templates.sync'))->assertForbidden();
    $this->actingAs($agent)->post(route('templates.store'), [])->assertForbidden();
});

// Sync from Meta upserts the templates and flashes the count.
test('the owner can sync templates from Meta', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [
                ['name' => 'order_update', 'language' => 'ar', 'category' => 'UTILITY', 'status' => 'APPROVED',
                    'components' => [['type' => 'BODY', 'text' => 'مرحباً {{1}}']]],
                ['name' => 'promo', 'language' => 'en_US', 'category' => 'MARKETING', 'status' => 'PENDING',
                    'components' => [['type' => 'BODY', 'text' => 'Sale!']]],
            ],
        ], 200),
    ]);

    $owner = provisionTenant(['waba_id' => '123123123123123', 'access_token' => 'EAAG-x']);

    $this->actingAs($owner)
        ->post(route('templates.sync'))
        ->assertRedirect();

    expect(MessageTemplate::query()->count())->toBe(2)
        ->and(MessageTemplate::query()->where('name', 'order_update')->firstOrFail()->status)->toBe('approved');
});

// A manual add is server-controlled: status is unknown (NOT sendable) and the
// variable count is derived from the body — never from input (§13).
test('a manual template add is unknown status with a derived variable count', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->post(route('templates.store'), [
        'name' => 'order_update',
        'language' => 'ar',
        'category' => 'utility',
        'body_text' => 'مرحباً {{1}}، طلبك {{2}} جاهز.',
        // Attempt to smuggle a status/variable_count — must be ignored (§13).
        'status' => 'approved',
        'variable_count' => 99,
    ])->assertRedirect(route('templates.index'));

    $template = MessageTemplate::query()->firstOrFail();
    expect($template->status)->toBe('unknown')        // never request-controlled
        ->and($template->isApproved())->toBeFalse()
        ->and($template->variable_count)->toBe(2)      // derived from the body
        ->and($template->category)->toBe('utility');
});

// Isolation (§1): the index lists only THIS tenant's templates.
test('the templates index is tenant-scoped', function () {
    // Tenant B with a template.
    $tenantB = Tenant::factory()->create();
    app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);
        MessageTemplate::factory()->create([
            'tenant_id' => $tenantB->id,
            'whatsapp_account_id' => $accountB->id,
            'name' => 'b_secret',
            'language' => 'ar',
        ]);
    });
    app(TenantContext::class)->forget();

    $owner = provisionTenant();
    MessageTemplate::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'whatsapp_account_id' => WhatsappAccount::query()->firstOrFail()->id,
        'name' => 'a_visible',
        'language' => 'ar',
    ]);

    $response = $this->actingAs($owner)->get(route('templates.index'));
    $response->assertOk()
        ->assertSee('a_visible')
        ->assertDontSee('b_secret');
});
