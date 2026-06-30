<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/*
| Tenant team management — OWNER ONLY (§13). The owner adds/removes agents within
| their own tenant; the admin-set max_users ceiling can never be exceeded, an
| agent is gated out, and isolation between tenants is absolute.
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

/**
 * An owner on a fresh tenant with a given seat limit. Binds the tenant for the
 * request body so assertions read through TenantScope. Returns [owner, tenant].
 *
 * @return array{0: User, 1: Tenant}
 */
function ownerWithSeats(int $maxUsers = 3): array
{
    $tenant = Tenant::factory()->create();
    $tenant->setMaxUsers($maxUsers); // admin-set, trusted save (§13)

    app(TenantContext::class)->set($tenant->id);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    return [$owner, $tenant];
}

test('an owner adds an agent within the seat limit', function () {
    [$owner, $tenant] = ownerWithSeats(maxUsers: 3);

    $response = $this->actingAs($owner)->post(route('team.store'), [
        'name' => 'موظف الدعم',
        'email' => 'agent@example.test',
        'password' => 'password-strong-1',
        'password_confirmation' => 'password-strong-1',
    ]);

    $response->assertRedirect(route('team.index'));

    $agent = User::query()->withoutGlobalScopes()->where('email', 'agent@example.test')->first();
    expect($agent)->not->toBeNull();
    expect($agent->role)->toBe('agent');
    expect($agent->tenant_id)->toBe($tenant->id);
    expect($agent->is_admin)->toBeFalse();          // never escalated (§13)
    expect($agent->email_verified_at)->not->toBeNull(); // can log in immediately
});

test('adding past max_users is rejected and creates no user', function () {
    // Limit of 1: the owner already fills the only seat.
    [$owner, $tenant] = ownerWithSeats(maxUsers: 1);

    $response = $this->actingAs($owner)->post(route('team.store'), [
        'name' => 'موظف زائد',
        'email' => 'overflow@example.test',
        'password' => 'password-strong-1',
        'password_confirmation' => 'password-strong-1',
    ]);

    $response->assertSessionHasErrors('email');

    expect(User::query()->withoutGlobalScopes()->where('email', 'overflow@example.test')->exists())->toBeFalse();
    // Still exactly one seat used (the owner) — ceiling never breached.
    expect($tenant->fresh()->seatsUsed())->toBe(1);
});

test('an agent cannot reach the team panel (owner only, 403)', function () {
    [$owner, $tenant] = ownerWithSeats(maxUsers: 5);

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'agent',
    ]);

    $this->actingAs($agent)->get(route('team.index'))->assertForbidden();
    $this->actingAs($agent)->post(route('team.store'), [
        'name' => 'x',
        'email' => 'blocked@example.test',
        'password' => 'password-strong-1',
        'password_confirmation' => 'password-strong-1',
    ])->assertForbidden();

    expect(User::query()->withoutGlobalScopes()->where('email', 'blocked@example.test')->exists())->toBeFalse();
});

test('owner of tenant A never sees or removes users of tenant B', function () {
    // Tenant B with an owner + an agent.
    $tenantB = Tenant::factory()->create();
    $ownerB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'owner']);
    $agentB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'agent', 'name' => 'موظف بيتا']);

    // Tenant A's owner, bound for the request.
    [$ownerA] = ownerWithSeats(maxUsers: 5);

    // The list shows only tenant A — B's agent name is absent.
    $this->actingAs($ownerA)->get(route('team.index'))
        ->assertOk()
        ->assertDontSee('موظف بيتا');

    // Destroying B's agent through A's session 404s (resolved via TenantScope).
    $this->actingAs($ownerA)->delete(route('team.destroy', $agentB->id))->assertNotFound();

    // B's agent is untouched.
    expect(User::query()->withoutGlobalScopes()->whereKey($agentB->id)->exists())->toBeTrue();
});

test('the owner cannot be removed and a user cannot remove themselves', function () {
    [$owner, $tenant] = ownerWithSeats(maxUsers: 5);

    // Cannot remove the owner.
    $this->actingAs($owner)->delete(route('team.destroy', $owner->id))
        ->assertRedirect(route('team.index'))
        ->assertSessionHasErrors('team');
    expect(User::query()->withoutGlobalScopes()->whereKey($owner->id)->exists())->toBeTrue();

    // A second owner-role user removing themselves is blocked by the self-guard.
    // (Using a non-owner would already be caught by the owner guard, so we make a
    // distinct owner-role account to isolate the self-removal branch.)
    $self = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $this->actingAs($self)->delete(route('team.destroy', $self->id))
        ->assertRedirect(route('team.index'))
        ->assertSessionHasErrors('team');
    expect(User::query()->withoutGlobalScopes()->whereKey($self->id)->exists())->toBeTrue();
});

test('an owner removes an agent within their own tenant', function () {
    [$owner, $tenant] = ownerWithSeats(maxUsers: 5);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);

    $this->actingAs($owner)->delete(route('team.destroy', $agent->id))
        ->assertRedirect(route('team.index'));

    expect(User::query()->withoutGlobalScopes()->whereKey($agent->id)->exists())->toBeFalse();
});
