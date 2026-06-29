<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;

/*
| The admin manages EVERY tenant through /admin/* only, via withoutGlobalScopes
| reads and the trusted Tenant/account methods (never mass assignment, §1, §13).
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('the admin customer list shows every tenant across the platform', function () {
    $admin = makeAdmin();

    $alpha = Tenant::factory()->create(['name' => 'شركة ألفا']);
    $beta = Tenant::factory()->create(['name' => 'شركة بيتا']);

    $response = $this->actingAs($admin)->get(route('admin.customers.index'));

    $response->assertOk();
    // Both tenants are visible — the admin crosses tenants (§1).
    $response->assertSee('شركة ألفا');
    $response->assertSee('شركة بيتا');
});

test('the admin dashboard counts tenants by status across all tenants', function () {
    $admin = makeAdmin();

    Tenant::factory()->count(2)->pending()->create();
    Tenant::factory()->count(3)->create(); // active (factory default)
    Tenant::factory()->suspended()->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertViewHas('totalCustomers', 6);
    $response->assertViewHas('pendingCount', 2);
    $response->assertViewHas('activeCount', 3);
    $response->assertViewHas('suspendedCount', 1);
});

test('approve moves a pending tenant to active', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->pending()->create();

    $response = $this->actingAs($admin)->post(route('admin.customers.approve', $customer->id));

    $response->assertRedirect(route('admin.customers.show', $customer->id));
    expect($customer->fresh()->status)->toBe('active');
});

test('suspend moves a tenant to suspended', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->create(['status' => 'active']);

    $this->actingAs($admin)->post(route('admin.customers.suspend', $customer->id))
        ->assertRedirect(route('admin.customers.show', $customer->id));

    expect($customer->fresh()->status)->toBe('suspended');
});

test('unsuspend returns a suspended tenant to active', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->suspended()->create();

    $this->actingAs($admin)->post(route('admin.customers.unsuspend', $customer->id));

    expect($customer->fresh()->status)->toBe('active');
});

test('updateSubscription sets subscription_ends_at about three months ahead', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->create(['status' => 'active']);

    $this->actingAs($admin)->put(route('admin.customers.subscription', $customer->id), [
        'months' => 3,
    ])->assertRedirect(route('admin.customers.show', $customer->id));

    $ends = $customer->fresh()->subscription_ends_at;
    expect($ends)->not->toBeNull();
    // ~ +3 months: comfortably after +2 months and not yet past +4 months.
    expect($ends->greaterThan(now()->addMonths(2)))->toBeTrue();
    expect($ends->lessThan(now()->addMonths(4)))->toBeTrue();
});

test('updateSubscription rejects months outside 1..60', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->create(['status' => 'active']);

    $this->actingAs($admin)->put(route('admin.customers.subscription', $customer->id), [
        'months' => 61,
    ])->assertSessionHasErrors('months');

    expect($customer->fresh()->subscription_ends_at)->toBeNull();
});

test('updateBot saves the ai_provider and ai_model on the customer account', function () {
    $admin = makeAdmin();

    $customer = Tenant::factory()->create();
    $account = app(TenantContext::class)->run($customer->id, fn () => WhatsappAccount::factory()->create([
        'tenant_id' => $customer->id,
        'ai_provider' => 'gemini',
        'ai_model' => 'gemini-2.5-flash-lite',
    ]));

    $this->actingAs($admin)->put(route('admin.customers.bot', $customer->id), [
        'ai_provider' => 'openai',
        'ai_model' => 'gpt-4o-mini',
    ])->assertRedirect(route('admin.customers.show', $customer->id));

    $fresh = WhatsappAccount::query()->withoutGlobalScopes()->findOrFail($account->id);
    expect($fresh->ai_provider)->toBe('openai');
    expect($fresh->ai_model)->toBe('gpt-4o-mini');
});

test('updateBot rejects an unsupported provider', function () {
    $admin = makeAdmin();

    $customer = Tenant::factory()->create();
    app(TenantContext::class)->run($customer->id, fn () => WhatsappAccount::factory()->create([
        'tenant_id' => $customer->id,
        'ai_provider' => 'gemini',
    ]));

    $this->actingAs($admin)->put(route('admin.customers.bot', $customer->id), [
        'ai_provider' => 'totally-made-up',
        'ai_model' => 'x',
    ])->assertSessionHasErrors('ai_provider');

    $fresh = WhatsappAccount::query()->withoutGlobalScopes()
        ->where('tenant_id', $customer->id)->firstOrFail();
    expect($fresh->ai_provider)->toBe('gemini'); // untouched
});

test('admin status actions are not reachable by GET (POST/PUT only)', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->pending()->create();

    // The action is POST-only; a GET is rejected as Method Not Allowed (405),
    // never silently executed. Nothing changes.
    $this->actingAs($admin)->get('/admin/customers/'.$customer->id.'/approve')
        ->assertStatus(405);
    expect($customer->fresh()->status)->toBe('pending');
});
