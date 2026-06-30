<?php

declare(strict_types=1);

use App\Jobs\SendBulkMessageJob;
use App\Models\BulkCampaign;
use App\Models\BulkCampaignRecipient;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\Bulk\BulkCampaignService;
use App\Services\Bulk\BulkCapExceededException;
use App\Services\Bulk\SendQuota;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;

/*
| Phase 6d — campaign creation/service/controller. Owner-only (§13), strict
| isolation (§1), cap pre-flight (§11), opt-in/opt-out, and the stop kill switch.
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

/**
 * A bound active tenant with an owner + account + $count eligible conversations.
 *
 * @return array{0: User, 1: WhatsappAccount, 2: Tenant}
 */
function bulkTenant(int $count = 3, int $cap = 250): array
{
    $tenant = Tenant::factory()->create();
    $tenant->setBulkCap($cap);
    $tenant->setMaxUsers(10);

    app(TenantContext::class)->set($tenant->id);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $account = WhatsappAccount::factory()->create(['tenant_id' => $tenant->id]);

    Conversation::factory()->count($count)->create([
        'tenant_id' => $tenant->id,
        'whatsapp_account_id' => $account->id,
        'window_expires_at' => now()->addHours(24),
    ]);

    return [$owner, $account, $tenant];
}

// 8b) eligibleRecipients excludes opted-out conversations (opt-out respected).
test('eligibleRecipients excludes opted-out contacts', function () {
    [, $account] = bulkTenant(2);

    // Opt one of the two out.
    $optedOut = Conversation::query()->first();
    $optedOut->optOut();

    $eligible = app(BulkCampaignService::class)->eligibleRecipients($account);

    expect($eligible)->toHaveCount(1)
        ->and($eligible->contains('id', $optedOut->id))->toBeFalse();
});

// 8c) Reversibility (§9): the owner can re-subscribe a contact opted out by
// mistake, bringing them back into the eligible audience.
test('the owner can resubscribe an opted-out contact', function () {
    [$owner, $account] = bulkTenant(1);

    $contact = Conversation::query()->firstOrFail();
    $contact->optOut();
    expect($contact->fresh()->isOptedOut())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('bulk.resubscribe', $contact))
        ->assertRedirect(route('bulk.index'));

    expect($contact->fresh()->isOptedOut())->toBeFalse()
        ->and(app(BulkCampaignService::class)->eligibleRecipients($account)->contains('id', $contact->id))->toBeTrue();
});

// Resubscribe is owner-only (it is inside the owner route group).
test('a non-owner cannot resubscribe a contact', function () {
    [, , $tenant] = bulkTenant(1);
    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);

    $contact = Conversation::query()->firstOrFail();
    $contact->optOut();

    $this->actingAs($agent)
        ->post(route('bulk.resubscribe', $contact))
        ->assertForbidden();

    expect($contact->fresh()->isOptedOut())->toBeTrue();
});

// Isolation (§1): a foreign tenant's contact 404s through the TenantScope.
test('resubscribe 404s for a foreign tenant contact', function () {
    [$owner] = bulkTenant(1);

    $tenantB = Tenant::factory()->create();
    $foreign = app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenantB->id,
            'whatsapp_account_id' => $accountB->id,
        ]);
        $conversation->optOut();

        return $conversation;
    });

    app(TenantContext::class)->set($owner->tenant_id);

    $this->actingAs($owner)
        ->post(route('bulk.resubscribe', $foreign->id))
        ->assertNotFound();

    expect($foreign->fresh()->isOptedOut())->toBeTrue();
});

// 2) Creating a campaign with more recipients than the remaining cap is rejected
// (no campaign, no jobs).
test('a campaign exceeding the remaining daily cap is rejected', function () {
    [$owner, $account] = bulkTenant(count: 3, cap: 2); // 3 eligible, cap 2

    Queue::fake();

    $recipients = app(BulkCampaignService::class)->eligibleRecipients($account);

    expect(fn () => app(BulkCampaignService::class)->create($account, $owner, 'مرحبا', $recipients))
        ->toThrow(BulkCapExceededException::class);

    expect(BulkCampaign::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

// 2-controller) The same through the HTTP layer: validation error, no campaign.
test('the store endpoint rejects a campaign over the cap with an error', function () {
    [$owner, $account] = bulkTenant(count: 3, cap: 2);

    Queue::fake();

    $this->actingAs($owner)->post(route('bulk.store'), ['body' => 'مرحبا'])
        ->assertSessionHasErrors('body');

    expect(BulkCampaign::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

// A valid campaign queues one job per recipient and records pending rows.
test('a valid campaign queues one job per eligible recipient', function () {
    [$owner, $account] = bulkTenant(3);

    Queue::fake();

    $this->actingAs($owner)->post(route('bulk.store'), ['body' => 'عرض جديد'])
        ->assertRedirect(route('bulk.index'));

    $campaign = BulkCampaign::query()->firstOrFail();
    expect($campaign->recipients_total)->toBe(3)
        ->and($campaign->recipients()->where('status', 'pending')->count())->toBe(3);

    Queue::assertPushed(SendBulkMessageJob::class, 3);
});

// 7) Stopping a campaign flags it stopped; subsequent jobs won't send (covered in
// the job test). Here we assert the controller stops it.
test('the owner can stop a running campaign', function () {
    [$owner, $account] = bulkTenant(1);

    $campaign = BulkCampaign::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
        'user_id' => $owner->id,
        'status' => 'sending',
    ]);

    $this->actingAs($owner)->post(route('bulk.stop', $campaign))
        ->assertRedirect(route('bulk.index'));

    expect($campaign->fresh()->status)->toBe('stopped');
});

// 9a) Owner-only: an agent gets 403 on every bulk route.
test('an agent cannot reach the bulk routes (403)', function () {
    [, $account, $tenant] = bulkTenant(1);

    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);

    $campaign = BulkCampaign::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_account_id' => $account->id,
        'user_id' => $tenant->users()->where('role', 'owner')->first()->id,
    ]);

    $this->actingAs($agent)->get(route('bulk.index'))->assertForbidden();
    $this->actingAs($agent)->post(route('bulk.store'), ['body' => 'x'])->assertForbidden();
    $this->actingAs($agent)->get(route('bulk.show', $campaign))->assertForbidden();
    $this->actingAs($agent)->post(route('bulk.stop', $campaign))->assertForbidden();
});

// 9b) Isolation: an owner cannot see/stop another tenant's campaign (foreign →404).
test('an owner cannot access another tenant campaign', function () {
    // Tenant B with a campaign.
    $tenantB = Tenant::factory()->create();
    $ownerB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'owner']);
    $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);
    app(TenantContext::class)->set($tenantB->id);
    $campaignB = BulkCampaign::factory()->create([
        'tenant_id' => $tenantB->id,
        'whatsapp_account_id' => $accountB->id,
        'user_id' => $ownerB->id,
    ]);
    app(TenantContext::class)->forget();

    // Owner A.
    [$ownerA] = bulkTenant(1);

    $this->actingAs($ownerA)->get(route('bulk.show', $campaignB->id))->assertNotFound();
    $this->actingAs($ownerA)->post(route('bulk.stop', $campaignB->id))->assertNotFound();

    // B's campaign untouched.
    expect(BulkCampaign::withoutGlobalScopes()->whereKey($campaignB->id)->first()->status)->toBe('queued');
});

// 9c) The job is tenant-scoped: a recipient from another tenant is invisible to a
// job bound to tenant A, so it never sends (no cross-tenant mixing).
test('the job does not process a recipient from another tenant', function () {
    // Tenant B recipient.
    $tenantB = Tenant::factory()->create();
    $ownerB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'owner']);
    $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);
    app(TenantContext::class)->set($tenantB->id);
    $convB = Conversation::factory()->create([
        'tenant_id' => $tenantB->id,
        'whatsapp_account_id' => $accountB->id,
        'window_expires_at' => now()->addDay(),
    ]);
    $campaignB = BulkCampaign::factory()->create([
        'tenant_id' => $tenantB->id, 'whatsapp_account_id' => $accountB->id, 'user_id' => $ownerB->id,
    ]);
    $recipientB = BulkCampaignRecipient::factory()->create([
        'tenant_id' => $tenantB->id,
        'bulk_campaign_id' => $campaignB->id,
        'conversation_id' => $convB->id,
        'wa_contact_id' => $convB->wa_contact_id,
    ]);
    app(TenantContext::class)->forget();

    // Tenant A.
    [, , $tenantA] = bulkTenant(1);

    // Run the job bound to tenant A but pointed at tenant B's recipient id.
    (new SendBulkMessageJob($tenantA->id, $recipientB->id))->handle(
        app(WhatsAppClient::class),
        app(SendQuota::class),
        app(TenantContext::class),
    );

    // B's recipient is untouched — A's job could not see it through the scope.
    expect($recipientB->fresh()->status)->toBe('pending');
});
