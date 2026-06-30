<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/*
| The seat limit (max_users) is set ONLY by the admin through the admin console
| via Tenant::setMaxUsers (trusted save). It is never mass-assignable and never
| reachable by a tenant owner (§13).
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('updateSeats sets the customer max_users', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->create();

    $this->actingAs($admin)->put(route('admin.customers.seats', $customer->id), [
        'max_users' => 10,
    ])->assertRedirect(route('admin.customers.show', $customer->id));

    expect($customer->fresh()->max_users)->toBe(10);
});

test('updateSeats rejects a seat count outside 1..100 and leaves max_users unchanged', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->create();
    $customer->setMaxUsers(3);

    $this->actingAs($admin)->put(route('admin.customers.seats', $customer->id), [
        'max_users' => 101,
    ])->assertSessionHasErrors('max_users');

    expect($customer->fresh()->max_users)->toBe(3);
});

test('a non-admin (tenant owner) cannot update seats (403)', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    $this->actingAs($owner)->put(route('admin.customers.seats', $tenant->id), [
        'max_users' => 50,
    ])->assertForbidden();

    expect($tenant->fresh()->max_users)->toBe(3); // column default, untouched
});

test('max_users is not mass-assignable (self-raised limit is a §13 violation)', function () {
    $tenant = Tenant::factory()->create(); // column default max_users = 3

    // Hostile mass-assignment attempt — the guarded field must be dropped, never
    // applied (so the value is anything but the smuggled 999).
    $tenant->fill(['max_users' => 999]);
    expect($tenant->max_users)->not->toBe(999);

    // And via create() with a smuggled field: the persisted row falls back to the
    // column default (3), never the smuggled 999.
    $other = Tenant::create([
        'name' => 'Sneaky',
        'plan' => 'free',
        'status' => 'active',
        'max_users' => 999,
    ]);
    expect($other->fresh()->max_users)->toBe(3);
});

test('canAddUser reflects the admin-set ceiling', function () {
    $tenant = Tenant::factory()->create();
    $tenant->setMaxUsers(2);

    app(TenantContext::class)->set($tenant->id);

    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    expect($tenant->fresh()->canAddUser())->toBeTrue();   // 1 of 2

    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);
    expect($tenant->fresh()->canAddUser())->toBeFalse();  // 2 of 2 — full

    app(TenantContext::class)->forget();
});
