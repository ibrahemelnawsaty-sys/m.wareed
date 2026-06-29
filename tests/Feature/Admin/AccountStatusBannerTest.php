<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;

/*
| §5, §10 — the customer dashboard shows a status banner reflecting the signed-
| in customer's OWN tenant only. Pending/suspended/expired show a warning;
| active+valid shows no warning. A customer never sees admin data or another
| customer's status.
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

/**
 * Provision a verified owner on a tenant in the given status, bind the tenant,
 * and return the owner. Mirrors the onboarding shape the dashboard expects.
 */
function ownerForTenant(Tenant $tenant): User
{
    app(TenantContext::class)->set($tenant->id);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    WhatsappAccount::factory()->create(['tenant_id' => $tenant->id]);

    return $owner;
}

test('a pending customer sees the under-review banner', function () {
    $owner = ownerForTenant(Tenant::factory()->pending()->create());

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('حسابك قيد المراجعة');
});

test('a suspended customer sees the suspended banner', function () {
    $owner = ownerForTenant(Tenant::factory()->suspended()->create());

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('حسابك موقوف مؤقتاً');
});

test('a customer with an expired subscription sees the renew banner', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $tenant->forceFill(['subscription_ends_at' => now()->subDay()])->save();
    $owner = ownerForTenant($tenant);

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('انتهى اشتراكك');
});

test('an active customer with a valid subscription sees no warning banner', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $owner = ownerForTenant($tenant);
    $tenant->setSubscriptionMonths(1);

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertOk();
    // No warning copy from the other states.
    $response->assertDontSee('حسابك قيد المراجعة');
    $response->assertDontSee('حسابك موقوف مؤقتاً');
    $response->assertDontSee('انتهى اشتراكك');
    // The reassuring active banner is present instead.
    $response->assertSee('حسابك نشط');
});

test('a customer only ever sees their own status, never another tenant', function () {
    // Tenant A is suspended; tenant B (separate) is pending.
    Tenant::factory()->suspended()->create(['name' => 'مستأجر موقوف']);
    $ownerB = ownerForTenant(Tenant::factory()->pending()->create(['name' => 'مستأجر قيد المراجعة']));

    $response = $this->actingAs($ownerB)->get(route('dashboard'));

    $response->assertOk();
    // B sees its own pending state, never A's suspended copy.
    $response->assertSee('حسابك قيد المراجعة');
    $response->assertDontSee('حسابك موقوف مؤقتاً');
});
