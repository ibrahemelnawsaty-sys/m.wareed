<?php

declare(strict_types=1);

use App\Models\Tenant;

/*
| Tenant::isActive() is the single gate the webhook consults before any bot
| work (§9). It must be true ONLY for an approved tenant whose subscription has
| not lapsed.
*/

test('isActive is true for an active tenant with no subscription end', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);

    expect($tenant->subscription_ends_at)->toBeNull();
    expect($tenant->isActive())->toBeTrue();
});

test('isActive is true for an active tenant with a future subscription', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $tenant->setSubscriptionMonths(1);

    expect($tenant->subscription_ends_at->isFuture())->toBeTrue();
    expect($tenant->isActive())->toBeTrue();
});

test('isActive is false for an active tenant whose subscription has expired', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $tenant->forceFill(['subscription_ends_at' => now()->subDay()])->save();

    expect($tenant->isActive())->toBeFalse();
});

test('isActive is false for a suspended tenant', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $tenant->suspend();

    expect($tenant->status)->toBe('suspended');
    expect($tenant->isActive())->toBeFalse();
});

test('isActive is false for a pending tenant', function () {
    $tenant = Tenant::factory()->create(['status' => 'pending']);

    expect($tenant->isActive())->toBeFalse();
});

test('admin management actions move a tenant through its lifecycle', function () {
    $tenant = Tenant::factory()->create(['status' => 'pending']);
    expect($tenant->isActive())->toBeFalse();

    $tenant->approve();
    expect($tenant->fresh()->status)->toBe('active');
    expect($tenant->isActive())->toBeTrue();

    $tenant->suspend();
    expect($tenant->fresh()->status)->toBe('suspended');
    expect($tenant->isActive())->toBeFalse();

    $tenant->unsuspend();
    expect($tenant->fresh()->status)->toBe('active');
    expect($tenant->isActive())->toBeTrue();

    $tenant->setSubscriptionMonths(3);
    expect($tenant->fresh()->subscription_ends_at->greaterThan(now()->addMonths(2)))->toBeTrue();
});
