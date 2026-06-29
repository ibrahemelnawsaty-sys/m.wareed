<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'business_name' => 'متجر وريد',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration provisions a tenant, owner user and one pending whatsapp account', function () {
    // Look across all tenants (ignore the global scope) to assert provisioning.
    app(TenantContext::class)->forget();

    $this->post('/register', [
        'business_name' => 'متجر وريد',
        'name' => 'مالك المتجر',
        'email' => 'owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $tenant = Tenant::query()->where('name', 'متجر وريد')->firstOrFail();
    expect($tenant->plan)->toBe('free');
    expect($tenant->status)->toBe('active');

    $user = User::query()->withoutGlobalScopes()->where('email', 'owner@example.com')->firstOrFail();
    expect($user->tenant_id)->toBe($tenant->id);
    expect($user->role)->toBe('owner');

    $accounts = WhatsappAccount::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
    expect($accounts)->toHaveCount(1);

    $account = $accounts->first();
    expect($account->status)->toBe('pending');
    expect($account->ai_model)->toBe('gemini-2.5-flash-lite');
    expect($account->temperature)->toBe(30);
    expect($account->system_prompt)->not->toBeEmpty();
});

test('registration requires a business name', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'nobiz@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('business_name');
    $this->assertGuest();
    expect(User::query()->withoutGlobalScopes()->where('email', 'nobiz@example.com')->exists())->toBeFalse();
});
