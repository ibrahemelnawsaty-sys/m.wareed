<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;

// B-1 follow-up: a tenant-less authenticated user must never be bound to
// "tenant 0" — BindTenant fails closed with 403 (§1, ADR-02).
test('an authenticated user with no tenant is refused the dashboard', function () {
    app(TenantContext::class)->forget();

    $user = User::factory()->create([
        'tenant_id' => null,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)->get('/whatsapp')->assertForbidden();
});

// TenantContext defence-in-depth: 0 is never a valid tenant id.
test('TenantContext rejects a non-positive tenant id', function () {
    expect(fn () => app(TenantContext::class)->set(0))
        ->toThrow(InvalidArgumentException::class);
});

// MEDIUM: a tenant cannot claim a phone_number_id already owned by another
// tenant — blocked at validation, never a raw 500, never a routing hijack
// (§1, ADR-01, §3).
test('a tenant cannot claim another tenant phone_number_id', function () {
    app(TenantContext::class)->forget();
    $tenantA = Tenant::factory()->create();
    WhatsappAccount::factory()->create([
        'tenant_id' => $tenantA->id,
        'phone_number_id' => 'PNID_SHARED',
    ]);

    app(TenantContext::class)->forget();
    $tenantB = Tenant::factory()->create();
    WhatsappAccount::factory()->create([
        'tenant_id' => $tenantB->id,
        'phone_number_id' => null,
    ]);
    $ownerB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->forget();

    $this->actingAs($ownerB)
        ->from('/whatsapp')
        ->put('/whatsapp', ['phone_number_id' => 'PNID_SHARED'])
        ->assertSessionHasErrors('phone_number_id');

    // Tenant B's number was not overwritten.
    expect(WhatsappAccount::query()->withoutGlobalScopes()
        ->where('tenant_id', $tenantB->id)->value('phone_number_id'))->toBeNull();
});

// A tenant CAN re-save its own account (own phone_number_id is ignored by the
// uniqueness rule) — the guard must not block legitimate self-updates.
test('a tenant can re-save its own phone_number_id', function () {
    app(TenantContext::class)->forget();
    $owner = provisionTenant(['phone_number_id' => 'PNID_OWN']);

    $this->actingAs($owner)
        ->from('/whatsapp')
        ->put('/whatsapp', ['phone_number_id' => 'PNID_OWN', 'display_name' => 'متجري'])
        ->assertSessionHasNoErrors();
});
