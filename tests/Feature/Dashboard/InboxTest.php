<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

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
 * The owner returned by provisionTenant() plus a same-tenant agent and an open
 * conversation. Returns [owner, agent, account, conversation].
 *
 * @return array{0: User, 1: User, 2: WhatsappAccount, 3: Conversation}
 */
function inboxFixture(): array
{
    $owner = provisionTenant();
    $account = WhatsappAccount::query()->firstOrFail();

    $agent = User::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'role' => 'agent',
        'email_verified_at' => now(),
    ]);

    $conversation = Conversation::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000001',
        'window_expires_at' => now()->addHours(24),
    ]);

    return [$owner, $agent, $account, $conversation];
}

// 4) Index opens for owner and agent (200); shows only the tenant's
// conversations; foreign show → 404.
test('the inbox index opens for the owner and the agent and only shows the tenant conversations', function () {
    [$owner, $agent, $account] = inboxFixture();

    Conversation::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000099',
    ]);

    // Tenant B owns a conversation with a recognisable contact id.
    $tenantB = Tenant::factory()->create();
    app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);
        Conversation::factory()->create([
            'tenant_id' => $tenantB->id,
            'whatsapp_account_id' => $accountB->id,
            'wa_contact_id' => '966599999999',
        ]);
    });

    app(TenantContext::class)->set($owner->tenant_id);

    $this->actingAs($owner)->get(route('inbox.index'))
        ->assertOk()
        ->assertSee('966500000099')
        ->assertDontSee('966599999999');

    $this->actingAs($agent)->get(route('inbox.index'))->assertOk();
});

test('an agent cannot open another tenant conversation in the inbox (404 IDOR)', function () {
    $tenantB = Tenant::factory()->create();
    $convB = app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);

        return Conversation::factory()->create([
            'tenant_id' => $tenantB->id,
            'whatsapp_account_id' => $accountB->id,
        ]);
    });

    app(TenantContext::class)->forget();
    [, $agent] = inboxFixture();

    $this->actingAs($agent)->get(route('inbox.show', $convB->id))->assertNotFound();
});

// 5) Agent replies to an unassigned human conversation → claims it + sends +
// stores outbound with user_id.
test('an agent replying to an unassigned conversation claims it and sends the message', function () {
    [, $agent, , $conversation] = inboxFixture();
    $conversation->handoffToHumans(); // human, unassigned

    app(TenantContext::class)->set($agent->tenant_id);

    $this->actingAs($agent)
        ->post(route('inbox.reply', $conversation), ['body' => 'أهلاً، كيف أساعدك؟'])
        ->assertRedirect();

    $conversation->refresh();
    expect($conversation->mode)->toBe('human')
        ->and($conversation->assigned_to_user_id)->toBe($agent->id);

    $out = Message::withoutGlobalScopes()->where('direction', 'out')->firstOrFail();
    expect($out->user_id)->toBe($agent->id)
        ->and($out->status)->toBe('sent')
        ->and($out->body)->toBe('أهلاً، كيف أساعدك؟');

    Http::assertSent(fn ($request) => $request['to'] === '966500000001'
        && $request['text']['body'] === 'أهلاً، كيف أساعدك؟');
});

// 6) Reply blocked when the window is closed → no send, error returned.
test('a reply is rejected and nothing is sent when the 24h window is closed', function () {
    [, $agent, , $conversation] = inboxFixture();
    $conversation->forceFill(['window_expires_at' => now()->subMinute()])->save();

    app(TenantContext::class)->set($agent->tenant_id);

    $this->actingAs($agent)
        ->from(route('inbox.show', $conversation))
        ->post(route('inbox.reply', $conversation), ['body' => 'مرحبا'])
        ->assertRedirect(route('inbox.show', $conversation))
        ->assertSessionHasErrors('reply');

    expect(Message::withoutGlobalScopes()->where('direction', 'out')->count())->toBe(0);
    Http::assertNothingSent();
});

// 7) A reply is blocked for an agent when the conversation belongs to another
// agent; the owner can still reply.
test('an agent cannot reply to a conversation assigned to another agent, but the owner can', function () {
    [$owner, $agentA, , $conversation] = inboxFixture();

    $agentB = User::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'role' => 'agent',
        'email_verified_at' => now(),
    ]);

    // Agent A owns the conversation.
    app(TenantContext::class)->set($owner->tenant_id);
    $conversation->claimBy($agentA);

    // Agent B is blocked, nothing sent.
    $this->actingAs($agentB)
        ->from(route('inbox.show', $conversation))
        ->post(route('inbox.reply', $conversation), ['body' => 'تدخل غير مصرح'])
        ->assertSessionHasErrors('reply');

    expect(Message::withoutGlobalScopes()->where('direction', 'out')->count())->toBe(0);
    Http::assertNothingSent();

    // The owner overrides and can reply.
    $this->actingAs($owner)
        ->post(route('inbox.reply', $conversation), ['body' => 'رد المالك'])
        ->assertRedirect();

    expect(Message::withoutGlobalScopes()->where('direction', 'out')->where('body', 'رد المالك')->count())->toBe(1);
    Http::assertSentCount(1);
});

// 8) Atomic claim: A claims, then B tries → it stays A's.
test('a claim is atomic so a second agent cannot steal an already-claimed conversation', function () {
    [$owner, $agentA, , $conversation] = inboxFixture();

    $agentB = User::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'role' => 'agent',
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($owner->tenant_id);

    expect($conversation->claimBy($agentA))->toBeTrue();

    $fresh = Conversation::query()->findOrFail($conversation->id);
    expect($fresh->claimBy($agentB))->toBeFalse();

    $conversation->refresh();
    expect($conversation->assigned_to_user_id)->toBe($agentA->id);
});

// 9) Release: the assigned agent returns the conversation to the bot.
test('the assigned agent can release a conversation back to the bot', function () {
    [, $agent, , $conversation] = inboxFixture();

    app(TenantContext::class)->set($agent->tenant_id);
    $conversation->claimBy($agent);

    $this->actingAs($agent)
        ->post(route('inbox.release', $conversation))
        ->assertRedirect();

    $conversation->refresh();
    expect($conversation->mode)->toBe('ai')
        ->and($conversation->assigned_to_user_id)->toBeNull();
});

// 10) The messages JSON feed is tenant-scoped; a foreign id 404s.
test('the messages feed returns scoped messages and 404s for a foreign conversation', function () {
    [$owner, $agent, , $conversation] = inboxFixture();

    Message::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'body' => 'رسالة العميل',
    ]);

    app(TenantContext::class)->set($owner->tenant_id);

    $this->actingAs($agent)
        ->getJson(route('inbox.messages', $conversation))
        ->assertOk()
        ->assertJsonFragment(['body' => 'رسالة العميل', 'author' => 'العميل']);

    // Foreign conversation → 404.
    $tenantB = Tenant::factory()->create();
    $convB = app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);

        return Conversation::factory()->create([
            'tenant_id' => $tenantB->id,
            'whatsapp_account_id' => $accountB->id,
        ]);
    });

    app(TenantContext::class)->set($owner->tenant_id);
    $this->actingAs($agent)->getJson(route('inbox.messages', $convB->id))->assertNotFound();
});

// 8b) Race fix (review HIGH): once agent A's reply has claimed an unassigned
// conversation, agent B's reply to the same thread is rejected and sends
// nothing — two agents never both answer the same customer. The reply path
// now honours claimBy()'s atomic result instead of charging ahead.
test('a second agent replying to a just-claimed conversation is rejected and sends nothing', function () {
    [$owner, $agentA, , $conversation] = inboxFixture();

    $agentB = User::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'role' => 'agent',
        'email_verified_at' => now(),
    ]);

    $conversation->handoffToHumans(); // human, unassigned

    app(TenantContext::class)->set($owner->tenant_id);

    // Agent A replies first → atomically claims it and sends one message.
    $this->actingAs($agentA)
        ->post(route('inbox.reply', $conversation), ['body' => 'رد الموظف أ'])
        ->assertRedirect();

    // Agent B replies to the now-claimed conversation → rejected, nothing more sent.
    $this->actingAs($agentB)
        ->from(route('inbox.show', $conversation))
        ->post(route('inbox.reply', $conversation), ['body' => 'رد الموظف ب'])
        ->assertSessionHasErrors('reply');

    expect(Message::withoutGlobalScopes()->where('direction', 'out')->count())->toBe(1);
    Http::assertSentCount(1);

    $conversation->refresh();
    expect($conversation->assigned_to_user_id)->toBe($agentA->id);
});

// 11) XSS hardening: a script payload in the body is escaped in the thread (§5).
test('message bodies are escaped in the conversation thread', function () {
    [$owner, $agent, , $conversation] = inboxFixture();

    Message::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'body' => '<script>alert(1)</script>',
    ]);

    app(TenantContext::class)->set($owner->tenant_id);

    $this->actingAs($agent)->get(route('inbox.show', $conversation))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});
