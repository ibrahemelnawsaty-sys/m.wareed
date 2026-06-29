<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;

/*
| §1, §13 — the admin capability must NEVER leak to customer routes. A regular
| user cannot invoke any admin action (403) and cannot view another customer's
| data. Secrets (access_token / ai_api_key) never appear on any admin page.
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('a regular user cannot invoke any admin mutating action', function () {
    $owner = User::factory()->create();
    $victim = Tenant::factory()->pending()->create();

    $this->actingAs($owner)->post(route('admin.customers.approve', $victim->id))->assertForbidden();
    $this->actingAs($owner)->post(route('admin.customers.suspend', $victim->id))->assertForbidden();
    $this->actingAs($owner)->post(route('admin.customers.unsuspend', $victim->id))->assertForbidden();
    $this->actingAs($owner)->put(route('admin.customers.subscription', $victim->id), ['months' => 3])->assertForbidden();
    $this->actingAs($owner)->put(route('admin.customers.bot', $victim->id), [
        'ai_provider' => 'gemini', 'ai_model' => 'x',
    ])->assertForbidden();

    // Nothing changed — the victim is still pending with no subscription.
    $fresh = $victim->fresh();
    expect($fresh->status)->toBe('pending');
    expect($fresh->subscription_ends_at)->toBeNull();
});

test('a regular user cannot view another customer through the admin show page', function () {
    $owner = User::factory()->create();
    $other = Tenant::factory()->create(['name' => 'عميل آخر سرّي']);

    $this->actingAs($owner)->get(route('admin.customers.show', $other->id))->assertForbidden();
});

test('the customer show page never renders the whatsapp access token or ai api key', function () {
    $admin = makeAdmin();

    $secretToken = 'EAAG-admin-page-must-not-print-this-token-123';
    $secretKey = 'sk-admin-page-must-not-print-this-key-456';

    $customer = Tenant::factory()->create();
    app(TenantContext::class)->run($customer->id, fn () => WhatsappAccount::factory()->create([
        'tenant_id' => $customer->id,
        'access_token' => $secretToken,
        'ai_api_key' => $secretKey,
    ]));

    $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer->id));

    $response->assertOk();
    // Raw secret values must never appear (§13).
    $response->assertDontSee($secretToken);
    $response->assertDontSee($secretKey);
});

test('the customer index never renders any access token', function () {
    $admin = makeAdmin();

    $secretToken = 'EAAG-list-page-must-not-print-token-789';

    $customer = Tenant::factory()->create();
    app(TenantContext::class)->run($customer->id, fn () => WhatsappAccount::factory()->create([
        'tenant_id' => $customer->id,
        'access_token' => $secretToken,
    ]));

    $response = $this->actingAs($admin)->get(route('admin.customers.index'));

    $response->assertOk();
    $response->assertDontSee($secretToken);
});

test('admin show for a missing tenant returns 404', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->get(route('admin.customers.show', 999999))->assertNotFound();
});
