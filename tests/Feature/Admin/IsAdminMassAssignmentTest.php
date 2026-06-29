<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/*
| §13 privilege-escalation defence: `is_admin` must never be reachable through
| mass assignment. If a malicious request ever smuggled is_admin=true into a
| create/update payload, the flag must stay false.
*/

test('User::create with is_admin=true does NOT make the user an admin', function () {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant->id);

    $user = User::create([
        'name' => 'Attacker',
        'email' => 'attacker@example.com',
        'password' => 'password',
        'tenant_id' => $tenant->id,
        'role' => 'owner',
        // Hostile extra field simulating tampered form input:
        'is_admin' => true,
    ]);

    // The guarded flag was never set, so the authorization check is false.
    // (The in-memory attribute is unset/null because mass assignment dropped
    // it — the point is it is NOT true.)
    expect($user->isAdmin())->toBeFalse();
    expect($user->is_admin)->not->toBeTrue();

    // And the persisted row falls back to the column default (false), never
    // the smuggled true.
    $fresh = User::query()->withoutGlobalScopes()->findOrFail($user->id);
    expect($fresh->is_admin)->toBeFalse();
    expect($fresh->isAdmin())->toBeFalse();

    app(TenantContext::class)->forget();
});

test('mass-assigning is_admin via fill() is ignored', function () {
    $user = User::factory()->make(['tenant_id' => null]);

    $user->fill(['is_admin' => true]);

    expect($user->isAdmin())->toBeFalse();
    expect($user->is_admin)->not->toBeTrue();
});
