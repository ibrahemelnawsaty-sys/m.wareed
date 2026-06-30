<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\Inbox\ConversationRouter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

/*
| Phase 6c — balanced distribution at the service + inbox layer: least-loaded
| selection, atomic assignment, the per-agent capacity guard, and the owner's
| exemption. Tenant isolation is enforced by TenantScope throughout (§1).
*/

beforeEach(function () {
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.AGENT_'.uniqid()]],
        ], 200),
    ]);

    app(TenantContext::class)->forget();
});

/**
 * An active tenant bound for the test, in balanced mode, with $agentCount agents
 * and a WhatsApp account. Returns [tenant, agents (ordered by id), account].
 *
 * @return array{0: Tenant, 1: array<int, User>, 2: WhatsappAccount}
 */
function distributionTenant(int $agentCount = 2, int $defaultQuota = 5): array
{
    $tenant = Tenant::factory()->create();
    $tenant->setDistribution('balanced', $defaultQuota);

    app(TenantContext::class)->set($tenant->id);

    $account = WhatsappAccount::factory()->create(['tenant_id' => $tenant->id]);

    $agents = [];
    for ($i = 0; $i < $agentCount; $i++) {
        $agents[] = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'agent',
        ]);
    }

    return [$tenant, $agents, $account];
}

function makeConversation(WhatsappAccount $account, string $contactId): Conversation
{
    return Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => $contactId,
        'window_expires_at' => now()->addHours(24),
    ]);
}

// 5) Least-loaded selection: an agent with one open conversation is preferred
// over one with two.
test('the router assigns to the agent with the fewest open conversations', function () {
    [, $agents, $account] = distributionTenant(agentCount: 2, defaultQuota: 5);
    [$agentA, $agentB] = $agents;

    // Agent A: 2 open. Agent B: 1 open.
    makeConversation($account, '111')->claimBy($agentA);
    makeConversation($account, '112')->claimBy($agentA);
    makeConversation($account, '113')->claimBy($agentB);

    $fresh = makeConversation($account, '114');
    $fresh->handoffToHumans(); // human, unassigned

    $assigned = app(ConversationRouter::class)->assignBestAgent($fresh);

    expect($assigned?->id)->toBe($agentB->id);
    $fresh->refresh();
    expect($fresh->assigned_to_user_id)->toBe($agentB->id);
});

// 6c review fix: claimFor centralises the capacity guard + atomic assignment
// for manual claims/replies. It returns 'claimed' / 'full' / 'taken' and never
// lets an agent past their target — check + assign are one row-locked step (§13).
test('claimFor reports full at target, claimed under it, and taken when already held', function () {
    [, $agents, $account] = distributionTenant(agentCount: 2, defaultQuota: 2);
    [$agentA, $agentB] = $agents;
    $router = app(ConversationRouter::class);

    // Agent A fills their target of 2.
    makeConversation($account, '301')->claimBy($agentA);
    makeConversation($account, '302')->claimBy($agentA);

    // At target → 'full', nothing assigned.
    $conversation = makeConversation($account, '303');
    $conversation->handoffToHumans();
    expect($router->claimFor($conversation, $agentA))->toBe('full');
    $conversation->refresh();
    expect($conversation->assigned_to_user_id)->toBeNull();

    // Agent B is under target → 'claimed'.
    expect($router->claimFor($conversation, $agentB))->toBe('claimed');
    $conversation->refresh();
    expect($conversation->assigned_to_user_id)->toBe($agentB->id);

    // A now tries the same (B's) conversation → 'taken'.
    expect($router->claimFor($conversation, $agentA))->toBe('taken');
});

// The owner is exempt from the per-agent ceiling (supervisor / overflow).
test('claimFor never reports full for the owner', function () {
    [$tenant, , $account] = distributionTenant(agentCount: 1, defaultQuota: 1);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $router = app(ConversationRouter::class);

    // Owner is already holding a full quota's worth of open conversations.
    makeConversation($account, '401')->claimBy($owner);

    $extra = makeConversation($account, '402');
    $extra->handoffToHumans();

    expect($router->claimFor($extra, $owner))->toBe('claimed');
});

// 1b) Tie-break: equal load → the oldest (smallest id) agent wins, deterministically.
test('on a load tie the router prefers the oldest agent', function () {
    [, $agents, $account] = distributionTenant(agentCount: 2, defaultQuota: 5);
    [$agentA, $agentB] = $agents; // both at load 0

    $fresh = makeConversation($account, '200');
    $fresh->handoffToHumans();

    $assigned = app(ConversationRouter::class)->assignBestAgent($fresh);

    expect($assigned?->id)->toBe($agentA->id); // smaller id
});

// 2b) All agents at capacity → router returns null, conversation stays unassigned.
test('the router returns null and leaves the conversation queued when all agents are full', function () {
    [, $agents, $account] = distributionTenant(agentCount: 2, defaultQuota: 1);
    [$agentA, $agentB] = $agents;

    makeConversation($account, '301')->claimBy($agentA); // A: 1/1 full
    makeConversation($account, '302')->claimBy($agentB); // B: 1/1 full

    $fresh = makeConversation($account, '303');
    $fresh->handoffToHumans();

    $assigned = app(ConversationRouter::class)->assignBestAgent($fresh);

    expect($assigned)->toBeNull();
    $fresh->refresh();
    expect($fresh->assigned_to_user_id)->toBeNull();
});

// 6) Atomic assignment: a conversation already assigned is never overwritten.
test('the router never reassigns an already-assigned conversation', function () {
    [, $agents, $account] = distributionTenant(agentCount: 2, defaultQuota: 5);
    [$agentA, $agentB] = $agents;

    $convo = makeConversation($account, '400');
    $convo->claimBy($agentA); // already owned by A

    // Even though B is lighter, the router must not steal an assigned thread.
    $assigned = app(ConversationRouter::class)->assignBestAgent($convo->fresh());

    expect($assigned)->toBeNull();
    $convo->refresh();
    expect($convo->assigned_to_user_id)->toBe($agentA->id);
});

// 4) Capacity guard at the inbox: an agent at their target cannot claim or reply
// to a new conversation; the owner is exempt and succeeds.
test('an agent at capacity cannot claim a new conversation but the owner can', function () {
    [$tenant, $agents, $account] = distributionTenant(agentCount: 1, defaultQuota: 1);
    [$agent] = $agents;

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    // Fill the agent to 1/1.
    makeConversation($account, '500')->claimBy($agent);
    expect($agent->isAtConversationCapacity())->toBeTrue();

    // A fresh unassigned human conversation.
    $queued = makeConversation($account, '501');
    $queued->handoffToHumans();

    // Agent at capacity → claim rejected, stays unassigned.
    $this->actingAs($agent)
        ->from(route('inbox.show', $queued))
        ->post(route('inbox.claim', $queued))
        ->assertSessionHasErrors('reply');

    $queued->refresh();
    expect($queued->assigned_to_user_id)->toBeNull();

    // Agent at capacity → reply to the same unassigned thread also rejected, nothing sent.
    $this->actingAs($agent)
        ->from(route('inbox.show', $queued))
        ->post(route('inbox.reply', $queued), ['body' => 'لن تُرسل'])
        ->assertSessionHasErrors('reply');

    expect(Message::withoutGlobalScopes()->where('direction', 'out')->where('body', 'لن تُرسل')->count())->toBe(0);

    // Owner is exempt (supervisor/overflow): claim succeeds.
    $this->actingAs($owner)
        ->post(route('inbox.claim', $queued))
        ->assertRedirect();

    $queued->refresh();
    expect($queued->assigned_to_user_id)->toBe($owner->id);
});

// 4b) Capacity guard does NOT block an agent from replying to a conversation
// they already own (only new pickups are gated).
test('an agent at capacity can still reply to a conversation they already own', function () {
    [, $agents, $account] = distributionTenant(agentCount: 1, defaultQuota: 1);
    [$agent] = $agents;

    // The single open conversation IS the one they own — they are at 1/1.
    $mine = makeConversation($account, '600');
    $mine->claimBy($agent);
    expect($agent->isAtConversationCapacity())->toBeTrue();

    $this->actingAs($agent)
        ->post(route('inbox.reply', $mine), ['body' => 'رد على محادثتي'])
        ->assertRedirect();

    expect(Message::withoutGlobalScopes()->where('direction', 'out')->where('body', 'رد على محادثتي')->count())->toBe(1);
    Http::assertSentCount(1);
});

// Model unit: an agent's effective quota falls back to the tenant default; an
// override wins; the owner is always exempt regardless of load.
test('conversation quota inherits the tenant default unless overridden, and the owner is exempt', function () {
    [$tenant, $agents, $account] = distributionTenant(agentCount: 1, defaultQuota: 5);
    [$agent] = $agents;

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    // Inherits the tenant default of 5.
    expect($agent->conversationQuota())->toBe(5);

    // Override to 2 via the trusted setter.
    $agent->setConversationQuota(2);
    expect($agent->fresh()->conversationQuota())->toBe(2);

    // Clearing the override returns to inheritance.
    $agent->fresh()->setConversationQuota(null);
    expect($agent->fresh()->conversationQuota())->toBe(5);

    // Pile open conversations on the owner — still never "at capacity".
    foreach (['701', '702', '703', '704', '705', '706'] as $cid) {
        makeConversation($account, $cid)->claimBy($owner);
    }
    expect($owner->isAtConversationCapacity())->toBeFalse();
});
