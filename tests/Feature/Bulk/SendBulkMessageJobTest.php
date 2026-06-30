<?php

declare(strict_types=1);

use App\Jobs\SendBulkMessageJob;
use App\Models\BulkCampaign;
use App\Models\BulkCampaignRecipient;
use App\Models\Conversation;
use App\Models\DailySendCounter;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\Bulk\SendQuota;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/*
| Phase 6d — the per-recipient send job. It binds the tenant (jobs don't inherit
| context, §1), and gates each send: stopped → opt-out → window → cap → send.
| No free-form send outside the 24h window (§11). $tries=1 (no storm, §12).
*/

beforeEach(function () {
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.BULK_'.uniqid()]],
        ], 200),
    ]);

    app(TenantContext::class)->forget();
});

/**
 * Build a campaign with ONE recipient on a fresh tenant, all server-side. The
 * tenant context is FORGOTTEN on return, so the job must bind it itself.
 *
 * @param  array<string, mixed>  $conversationAttrs
 * @return array{0: BulkCampaign, 1: BulkCampaignRecipient, 2: Conversation, 3: WhatsappAccount}
 */
function bulkSetup(array $conversationAttrs = [], int $cap = 250): array
{
    $tenant = Tenant::factory()->create();
    $tenant->setBulkCap($cap);

    app(TenantContext::class)->set($tenant->id);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $account = WhatsappAccount::factory()->create(['tenant_id' => $tenant->id]);

    $conversation = Conversation::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000000',
        'window_expires_at' => now()->addHours(24),
    ], $conversationAttrs));

    $campaign = BulkCampaign::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_account_id' => $account->id,
        'user_id' => $owner->id,
        'body' => 'عرض اليوم!',
        'recipients_total' => 1,
    ]);

    $recipient = BulkCampaignRecipient::factory()->create([
        'tenant_id' => $tenant->id,
        'bulk_campaign_id' => $campaign->id,
        'conversation_id' => $conversation->id,
        'wa_contact_id' => $conversation->wa_contact_id,
    ]);

    app(TenantContext::class)->forget();

    return [$campaign, $recipient, $conversation, $account];
}

function runBulkJob(BulkCampaign $campaign, BulkCampaignRecipient $recipient): void
{
    (new SendBulkMessageJob($campaign->tenant_id, $recipient->id))->handle(
        app(WhatsAppClient::class),
        app(SendQuota::class),
        app(TenantContext::class),
    );
}

// 3) Sends to an in-window contact: marks sent, records wa_message_id, stores the
// outbound Message authored by the campaign owner, and bumps the cap counter.
it('sends to an in-window contact and records the outcome', function () {
    [$campaign, $recipient, $conversation, $account] = bulkSetup();

    runBulkJob($campaign, $recipient);

    Http::assertSent(fn ($request) => str_contains($request->url(), $account->phone_number_id)
        && $request['text']['body'] === 'عرض اليوم!');

    $recipient->refresh();
    expect($recipient->status)->toBe('sent')
        ->and($recipient->wa_message_id)->not->toBeNull();

    $campaign->refresh();
    expect($campaign->sent_count)->toBe(1)
        ->and($campaign->status)->toBe('completed');

    $out = Message::withoutGlobalScopes()->where('direction', 'out')->firstOrFail();
    expect($out->user_id)->toBe($campaign->user_id)
        ->and($out->body)->toBe('عرض اليوم!');

    $counter = DailySendCounter::withoutGlobalScopes()
        ->where('whatsapp_account_id', $account->id)->firstOrFail();
    expect($counter->sent_count)->toBe(1);
});

// 4) Skips an out-of-window contact: NO send (a free-form send outside the 24h
// window is a Meta violation — a template is required, §11).
it('skips an out-of-window contact without sending', function () {
    [$campaign, $recipient] = bulkSetup(['window_expires_at' => now()->subHour()]);

    runBulkJob($campaign, $recipient);

    Http::assertNothingSent();

    $recipient->refresh();
    expect($recipient->status)->toBe('skipped_window');

    $campaign->refresh();
    expect($campaign->skipped_count)->toBe(1)
        ->and($campaign->sent_count)->toBe(0)
        ->and($campaign->status)->toBe('completed');
});

// 5) Skips an opted-out contact: NO send.
it('skips an opted-out contact without sending', function () {
    [$campaign, $recipient] = bulkSetup(['opted_out_at' => now()->subDay()]);

    runBulkJob($campaign, $recipient);

    Http::assertNothingSent();

    $recipient->refresh();
    expect($recipient->status)->toBe('skipped_optout');
    expect($campaign->refresh()->skipped_count)->toBe(1);
});

// 6) Skips when the daily cap is already reached: NO send.
it('skips when the daily cap is reached', function () {
    [$campaign, $recipient, , $account] = bulkSetup([], 0); // cap 0 → no headroom

    // With a clamped-min cap of 1.. setBulkCap(0) stores 1, so pre-consume it.
    app(TenantContext::class)->run($campaign->tenant_id, function () use ($account) {
        app(SendQuota::class)->tryConsume($account); // exhaust the cap of 1
    });

    runBulkJob($campaign, $recipient);

    Http::assertNothingSent();

    $recipient->refresh();
    expect($recipient->status)->toBe('skipped_cap');
    expect($campaign->refresh()->skipped_count)->toBe(1);
});

// 7) A stopped campaign: the job exits without sending (kill switch, §9).
it('does not send for a stopped campaign', function () {
    [$campaign, $recipient] = bulkSetup();

    // Stop it (server-controlled status).
    app(TenantContext::class)->run($campaign->tenant_id, fn () => $campaign->forceFill(['status' => 'stopped'])->save());

    runBulkJob($campaign->fresh(), $recipient);

    Http::assertNothingSent();
    expect($recipient->refresh()->status)->toBe('pending'); // untouched
});

// 10) The job binds the tenant and sends as the tenant's own number. After the
// job, no tenant leaks into the ambient context.
it('binds the tenant and sends as the tenant number, leaving no context leak', function () {
    [$campaign, $recipient, , $account] = bulkSetup();

    expect(app(TenantContext::class)->has())->toBeFalse(); // not bound before

    runBulkJob($campaign, $recipient);

    expect(app(TenantContext::class)->has())->toBeFalse(); // still not bound after

    Http::assertSent(fn ($request) => str_contains($request->url(), $account->phone_number_id));
});

// $tries is 1 — a failed send is never retried into a storm (§12).
it('has a single attempt', function () {
    [$campaign, $recipient] = bulkSetup();
    expect((new SendBulkMessageJob($campaign->tenant_id, $recipient->id))->tries)->toBe(1);
});

// A Cloud API failure is reported and recorded as failed (no silent swallow §3).
it('records a failed send without retrying', function () {
    [$campaign, $recipient] = bulkSetup();

    // Bind a client that raises exactly as the real one does on a Cloud API
    // failure, so the job's catch path is exercised deterministically (the
    // beforeEach Http::fake stub is first-match-wins, so re-faking a 500 here is
    // unreliable). The job catches the throwable, report()s it, and records the
    // failure — report() does not rethrow in the test environment.
    app()->bind(WhatsAppClient::class, fn () => new class extends WhatsAppClient
    {
        public function sendText(WhatsappAccount $account, string $to, string $body): array
        {
            throw new RuntimeException('WhatsApp send failed (test).');
        }
    });

    runBulkJob($campaign, $recipient);

    $recipient->refresh();
    expect($recipient->status)->toBe('failed')
        ->and($recipient->failed_reason)->toBe('send_failed');
    expect($campaign->refresh()->failed_count)->toBe(1);
});
