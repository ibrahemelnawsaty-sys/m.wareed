<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/*
| Phase 4b — the super-admin panel crosses every tenant and is the one
| deliberate exception to isolation (§1, §13). These tests lock every /admin/*
| surface: non-admins are forbidden (403), genuine super-admins get 200.
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

dataset('adminGetRoutes', function () {
    return [
        'dashboard' => fn () => route('admin.dashboard'),
        'customers index' => fn () => route('admin.customers.index'),
        'analytics' => fn () => route('admin.analytics.index'),
    ];
});

test('a regular (non-admin) user is forbidden from every admin GET page', function (Closure $url) {
    $owner = User::factory()->create(); // ordinary owner with a tenant
    expect($owner->isAdmin())->toBeFalse();

    $this->actingAs($owner)->get($url())->assertForbidden();
})->with('adminGetRoutes');

test('a super-admin can reach every admin GET page', function (Closure $url) {
    $admin = makeAdmin();

    $this->actingAs($admin)->get($url())->assertOk();
})->with('adminGetRoutes');

test('a regular user is forbidden from a customer show page', function () {
    $owner = User::factory()->create();
    $customer = Tenant::factory()->create();

    $this->actingAs($owner)->get(route('admin.customers.show', $customer->id))->assertForbidden();
});

test('a super-admin can open a customer show page', function () {
    $admin = makeAdmin();
    $customer = Tenant::factory()->create();

    $this->actingAs($admin)->get(route('admin.customers.show', $customer->id))->assertOk();
});

test('a guest is redirected to login, not shown the admin panel', function () {
    $this->get(route('admin.customers.index'))->assertRedirect('/login');
});
