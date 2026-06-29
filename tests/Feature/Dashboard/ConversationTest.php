<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('a tenant sees only its own conversations on the index', function () {
    $owner = provisionTenant();
    $account = WhatsappAccount::query()->firstOrFail();

    $mine = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000001',
    ]);
    Message::factory()->create([
        'tenant_id' => $account->tenant_id,
        'conversation_id' => $mine->id,
        'direction' => 'in',
        'body' => 'رسالة مرئية',
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

    app(TenantContext::class)->set($account->tenant_id);

    $this->actingAs($owner)->get(route('conversations.index'))
        ->assertOk()
        ->assertSee('966500000001')
        ->assertDontSee('966599999999');
});

test('a tenant cannot open another tenant conversation (404 IDOR)', function () {
    // Tenant B owns a conversation.
    $tenantB = Tenant::factory()->create();
    $convB = app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);

        return Conversation::factory()->create([
            'tenant_id' => $tenantB->id,
            'whatsapp_account_id' => $accountB->id,
            'wa_contact_id' => 'SECRET_CONTACT_B',
        ]);
    });

    // Tenant A signs in.
    app(TenantContext::class)->forget();
    $ownerA = provisionTenant();

    // Route-model binding resolves through TenantScope → 404, never a read.
    $this->actingAs($ownerA)->get(route('conversations.show', $convB->id))->assertNotFound();
});

test('the conversation thread renders inbound and outbound bubbles', function () {
    $owner = provisionTenant();
    $account = WhatsappAccount::query()->firstOrFail();

    $conversation = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
    ]);

    Message::factory()->create([
        'tenant_id' => $account->tenant_id,
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'body' => 'سؤال العميل',
    ]);
    Message::factory()->create([
        'tenant_id' => $account->tenant_id,
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'body' => 'رد البوت',
        'tokens_in' => 120,
        'tokens_out' => 45,
    ]);

    $this->actingAs($owner)->get(route('conversations.show', $conversation))
        ->assertOk()
        ->assertSee('سؤال العميل')
        ->assertSee('رد البوت');
});
