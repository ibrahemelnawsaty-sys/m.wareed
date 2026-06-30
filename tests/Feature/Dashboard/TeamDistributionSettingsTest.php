<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/*
| Phase 6c — the owner-only distribution settings UI: the mode + default target,
| and per-agent target overrides. Only the owner reaches these (agent → 403); the
| settings are never mass-assignable (§13); and an owner can never touch another
| tenant's user (§1 isolation).
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

/**
 * An owner on a fresh active tenant, bound for the request body. Returns
 * [owner, tenant].
 *
 * @return array{0: User, 1: Tenant}
 */
function distributionOwner(): array
{
    $tenant = Tenant::factory()->create();
    $tenant->setMaxUsers(10);

    app(TenantContext::class)->set($tenant->id);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    return [$owner, $tenant];
}

// 7a) The owner sets the distribution mode + the default per-agent target.
test('an owner sets the distribution mode and default agent quota', function () {
    [$owner, $tenant] = distributionOwner();

    $this->actingAs($owner)->put(route('team.distribution'), [
        'distribution_mode' => 'balanced',
        'agent_conversation_quota' => 8,
    ])->assertRedirect(route('team.index'));

    $fresh = $tenant->fresh();
    expect($fresh->distribution_mode)->toBe('balanced')
        ->and($fresh->agent_conversation_quota)->toBe(8)
        ->and($fresh->isBalancedMode())->toBeTrue();
});

// 7a-validation) An invalid mode or out-of-range quota is rejected; nothing changes.
test('an invalid distribution mode or quota is rejected', function () {
    [$owner, $tenant] = distributionOwner(); // defaults: claim / 5

    $this->actingAs($owner)->put(route('team.distribution'), [
        'distribution_mode' => 'round-robin', // not allowed
        'agent_conversation_quota' => 8,
    ])->assertSessionHasErrors('distribution_mode');

    $this->actingAs($owner)->put(route('team.distribution'), [
        'distribution_mode' => 'balanced',
        'agent_conversation_quota' => 0, // below 1
    ])->assertSessionHasErrors('agent_conversation_quota');

    $fresh = $tenant->fresh();
    expect($fresh->distribution_mode)->toBe('claim') // untouched
        ->and($fresh->agent_conversation_quota)->toBe(5);
});

// 7b) The owner sets a per-agent target override; a blank value clears it.
test('an owner sets and clears a per-agent conversation quota', function () {
    [$owner, $tenant] = distributionOwner();

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);

    // Set an override of 3.
    $this->actingAs($owner)->put(route('team.quota', $agent), [
        'conversation_quota' => 3,
    ])->assertRedirect(route('team.index'));

    expect($agent->fresh()->conversation_quota)->toBe(3);

    // Clear it (blank ⇒ inherit tenant default).
    $this->actingAs($owner)->put(route('team.quota', $agent), [
        'conversation_quota' => '',
    ])->assertRedirect(route('team.index'));

    expect($agent->fresh()->conversation_quota)->toBeNull();
});

// 7c) An agent cannot reach either distribution endpoint (owner-only, 403).
test('an agent cannot change distribution settings or an agent quota (403)', function () {
    [, $tenant] = distributionOwner();

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);
    $other = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);

    $this->actingAs($agent)->put(route('team.distribution'), [
        'distribution_mode' => 'balanced',
        'agent_conversation_quota' => 9,
    ])->assertForbidden();

    $this->actingAs($agent)->put(route('team.quota', $other), [
        'conversation_quota' => 9,
    ])->assertForbidden();

    $fresh = $tenant->fresh();
    expect($fresh->distribution_mode)->toBe('claim'); // unchanged
    expect($other->fresh()->conversation_quota)->toBeNull();
});

// 7d) distribution_mode / agent_conversation_quota / conversation_quota are NOT
// mass-assignable — a self-flipped mode or self-raised ceiling is a §13 violation.
test('distribution settings and agent quota are not mass-assignable', function () {
    $tenant = Tenant::factory()->create(); // defaults: claim / 5

    // Hostile fill on the tenant — guarded fields must be dropped.
    $tenant->fill([
        'distribution_mode' => 'balanced',
        'agent_conversation_quota' => 999,
    ]);
    expect($tenant->distribution_mode)->not->toBe('balanced');
    expect($tenant->agent_conversation_quota)->not->toBe(999);

    // Hostile fill on a user — conversation_quota must be dropped.
    app(TenantContext::class)->set($tenant->id);
    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);
    $agent->fill(['conversation_quota' => 999]);
    expect($agent->conversation_quota)->not->toBe(999);
    app(TenantContext::class)->forget();
});

// 8) Isolation: owner A can never set a target on tenant B's user (foreign → 404).
test('an owner cannot set a quota on another tenant user', function () {
    // Tenant B with an agent.
    $tenantB = Tenant::factory()->create();
    $agentB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'agent']);

    // Owner A, bound to tenant A.
    [$ownerA] = distributionOwner();

    $this->actingAs($ownerA)->put(route('team.quota', $agentB->id), [
        'conversation_quota' => 7,
    ])->assertNotFound();

    // B's agent is untouched.
    expect(User::query()->withoutGlobalScopes()->whereKey($agentB->id)->first()->conversation_quota)->toBeNull();
});
