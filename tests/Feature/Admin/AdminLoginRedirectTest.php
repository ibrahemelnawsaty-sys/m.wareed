<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Tenancy\TenantContext;

// An admin has no tenant; sending them to /dashboard would 403 via BindTenant.
// Login must route them to the admin panel instead.
test('an admin is redirected to the admin panel after login', function () {
    app(TenantContext::class)->forget();

    $admin = User::factory()->create([
        'tenant_id' => null,
        'email_verified_at' => now(),
    ]);
    $admin->forceFill(['is_admin' => true])->save();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
});

test('a tenant user is redirected to their dashboard after login', function () {
    app(TenantContext::class)->forget();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});
