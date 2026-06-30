<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Tenancy\TenantContext;

/*
 * 6a hardening (review finding, §1/§13 least privilege): an agent (role=agent)
 * must NOT reach owner-only account-administration surfaces — the WhatsApp
 * connection (encrypted token), the bot prompt, the knowledge base, the metered
 * playground, or team management. They keep the shared surfaces (conversations,
 * analytics) so they can do their job.
 */

function agentInOwnersTenant(): User
{
    app(TenantContext::class)->forget();
    $owner = provisionTenant();

    $agent = User::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'role' => 'agent',
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->forget();

    return $agent;
}

test('an agent is blocked from owner-only account settings', function (string $routeName) {
    $this->actingAs(agentInOwnersTenant())
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'whatsapp.edit',
    'bot.edit',
    'menu.edit',
    'knowledge.index',
    'knowledge.create',
    'playground.index',
    'team.index',
]);

test('an agent cannot mutate owner-only settings', function () {
    $agent = agentInOwnersTenant();

    $this->actingAs($agent)->put(route('whatsapp.update'), [])->assertForbidden();
    $this->actingAs($agent)->put(route('bot.update'), [])->assertForbidden();
    $this->actingAs($agent)->post(route('playground.send'), ['message' => 'hi'])->assertForbidden();
});

test('an agent can still reach the shared tenant pages', function () {
    $agent = agentInOwnersTenant();

    $this->actingAs($agent)->get(route('conversations.index'))->assertOk();
    $this->actingAs($agent)->get(route('analytics.index'))->assertOk();
});

test('the owner still reaches every account setting', function (string $routeName) {
    app(TenantContext::class)->forget();
    $owner = provisionTenant();
    app(TenantContext::class)->forget();

    $this->actingAs($owner)->get(route($routeName))->assertOk();
})->with([
    'whatsapp.edit',
    'bot.edit',
    'menu.edit',
    'knowledge.index',
    'playground.index',
    'team.index',
]);
