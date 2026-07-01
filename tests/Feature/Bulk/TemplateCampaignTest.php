<?php

declare(strict_types=1);

use App\Jobs\SendBulkMessageJob;
use App\Models\BulkCampaign;
use App\Models\BulkCampaignRecipient;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\Bulk\BulkCampaignService;
use App\Services\Bulk\SendQuota;
use App\Services\Bulk\TemplateNotApprovedException;
use App\Services\Bulk\TemplateVariableMismatchException;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
| Phase 7c — bulk broadcast WITH a Meta-approved template. A template campaign
| reaches contacts OUTSIDE the 24h window (window check skipped) while still
| honouring opt-out + the atomic 250 cap (§11). Only APPROVED templates may be
| sent, and the variable count must match the template (§13).
*/

beforeEach(function () {
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.TPL_'.uniqid()]],
        ], 200),
    ]);

    app(TenantContext::class)->forget();
});

/**
 * A bound tenant + owner + account + ONE template + ONE conversation. The
 * conversation window can be set via $conversationAttrs; the context is FORGOTTEN
 * on return so a job must bind it itself.
 *
 * @param  array<string, mixed>  $templateAttrs
 * @param  array<string, mixed>  $conversationAttrs
 * @return array{0: User, 1: WhatsappAccount, 2: MessageTemplate, 3: Conversation, 4: Tenant}
 */
function templateSetup(array $templateAttrs = [], array $conversationAttrs = [], int $cap = 250): array
{
    $tenant = Tenant::factory()->create();
    $tenant->setBulkCap($cap);

    app(TenantContext::class)->set($tenant->id);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $account = WhatsappAccount::factory()->create(['tenant_id' => $tenant->id]);

    $template = MessageTemplate::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'whatsapp_account_id' => $account->id,
        'name' => 'order_update',
        'language' => 'ar',
        'status' => 'approved',
        'variable_count' => 0,
        'body_text' => 'مرحباً، عرضنا متاح.',
    ], $templateAttrs));

    $conversation = Conversation::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'whatsapp_account_id' => $account->id,
        'wa_contact_id' => '966500000000',
        'window_expires_at' => now()->addHours(24),
    ], $conversationAttrs));

    app(TenantContext::class)->forget();

    return [$owner, $account, $template, $conversation, $tenant];
}

function runTemplateJob(BulkCampaign $campaign, BulkCampaignRecipient $recipient): void
{
    (new SendBulkMessageJob($campaign->tenant_id, $recipient->id))->handle(
        app(WhatsAppClient::class),
        app(SendQuota::class),
        app(TenantContext::class),
    );
}

// 3) A template campaign sends a TEMPLATE even when the window is CLOSED, with the
// right name/language, and stores a Message of type=template.
test('a template campaign sends outside the window and stores a template message', function () {
    // Window is CLOSED — a free-form send would be skipped; a template must still go.
    [$owner, $account, $template, $conversation] = templateSetup(
        ['variable_count' => 1, 'body_text' => 'مرحباً {{1}}'],
        ['window_expires_at' => now()->subHour()],
    );

    [$campaign, $recipient] = app(TenantContext::class)->run($owner->tenant_id, function () use ($account, $owner, $template, $conversation) {
        $campaign = app(BulkCampaignService::class)->create(
            $account,
            $owner,
            '',
            collect([$conversation]),
            $template,
            ['أحمد'],
        );

        return [$campaign, $campaign->recipients()->firstOrFail()];
    });

    runTemplateJob($campaign, $recipient);

    // The send was a TEMPLATE with the right name/language and the variable param.
    Http::assertSent(fn ($request) => str_contains($request->url(), $account->phone_number_id)
        && $request['type'] === 'template'
        && $request['template']['name'] === 'order_update'
        && $request['template']['language']['code'] === 'ar'
        && $request['template']['components'][0]['type'] === 'body'
        && $request['template']['components'][0]['parameters'][0]['text'] === 'أحمد');

    $recipient->refresh();
    expect($recipient->status)->toBe('sent')               // NOT skipped_window
        ->and($recipient->wa_message_id)->not->toBeNull();

    $out = Message::withoutGlobalScopes()->where('direction', 'out')->firstOrFail();
    expect($out->type)->toBe('template')
        ->and($out->body)->toBe('مرحباً أحمد');           // rendered display body
});

// 3b) A parameter-less approved template sends with NO components block.
test('a template with no variables sends without a components block', function () {
    [$owner, $account, $template, $conversation] = templateSetup(
        ['variable_count' => 0, 'body_text' => 'عرضنا متاح الآن.'],
        ['window_expires_at' => now()->subDay()], // window closed
    );

    [$campaign, $recipient] = app(TenantContext::class)->run($owner->tenant_id, function () use ($account, $owner, $template, $conversation) {
        $campaign = app(BulkCampaignService::class)->create($account, $owner, '', collect([$conversation]), $template, []);

        return [$campaign, $campaign->recipients()->firstOrFail()];
    });

    runTemplateJob($campaign, $recipient);

    Http::assertSent(fn ($request) => $request['type'] === 'template'
        && ! array_key_exists('components', $request['template']));

    expect($recipient->refresh()->status)->toBe('sent');
});

// 4) A template campaign still honours opt-out (no send).
test('a template campaign skips an opted-out contact', function () {
    [$owner, $account, $template, $conversation] = templateSetup(
        [],
        ['opted_out_at' => now()->subDay(), 'window_expires_at' => now()->subHour()],
    );

    [$campaign, $recipient] = app(TenantContext::class)->run($owner->tenant_id, function () use ($account, $owner, $template, $conversation) {
        $campaign = app(BulkCampaignService::class)->create($account, $owner, '', collect([$conversation]), $template, []);

        return [$campaign, $campaign->recipients()->firstOrFail()];
    });

    runTemplateJob($campaign, $recipient);

    Http::assertNothingSent();
    expect($recipient->refresh()->status)->toBe('skipped_optout');
});

// 4b) A template campaign still honours the ATOMIC daily cap (no send when full).
test('a template campaign skips when the daily cap is reached', function () {
    // Fake the queue so create()'s auto-dispatched job does NOT run and consume
    // the slot itself — we want to exhaust the cap by hand and then run the job
    // manually with the cap already full, to prove it skips without sending.
    Queue::fake();

    [$owner, $account, $template, $conversation] = templateSetup(
        [],
        ['window_expires_at' => now()->subHour()],
        cap: 0, // clamped to 1
    );

    [$campaign, $recipient] = app(TenantContext::class)->run($owner->tenant_id, function () use ($account, $owner, $template, $conversation) {
        $campaign = app(BulkCampaignService::class)->create($account, $owner, '', collect([$conversation]), $template, []);
        // Exhaust the cap of 1 before the job runs.
        app(SendQuota::class)->tryConsume($account);

        return [$campaign, $campaign->recipients()->firstOrFail()];
    });

    runTemplateJob($campaign, $recipient);

    Http::assertNothingSent();
    expect($recipient->refresh()->status)->toBe('skipped_cap');
});

// 4c) BAN-CRITICAL (review B1): a template APPROVED at creation but de-approved by
// Meta before the async job runs must be re-checked at send time and skipped —
// never broadcast an unapproved template (outside the window) to a real number.
test('a template de-approved before the job runs is skipped, never sent', function () {
    // Fake the queue so the auto-dispatched job (while the template was still
    // approved) does not run; we de-approve, then run the job by hand.
    Queue::fake();

    [$owner, $account, $template, $conversation] = templateSetup(
        [],
        ['window_expires_at' => now()->subHour()], // outside window — a template would otherwise go
    );

    [$campaign, $recipient] = app(TenantContext::class)->run($owner->tenant_id, function () use ($account, $owner, $template, $conversation) {
        $campaign = app(BulkCampaignService::class)->create($account, $owner, '', collect([$conversation]), $template, []);

        // Meta rejects the template AFTER the campaign is queued (a re-sync updates it).
        $template->syncFromMeta('rejected', 'utility', 0, 'مرحباً، عرضنا متاح.');

        return [$campaign, $campaign->recipients()->firstOrFail()];
    });

    runTemplateJob($campaign, $recipient);

    Http::assertNothingSent();
    expect($recipient->refresh()->status)->toBe('skipped_template');
});

// 5) Choosing a NON-approved template is rejected at creation — no campaign queued.
test('a campaign with a pending template is rejected', function () {
    [$owner, $account, $template, $conversation] = templateSetup(['status' => 'pending']);

    Queue::fake();

    app(TenantContext::class)->run($owner->tenant_id, function () use ($account, $owner, $template, $conversation) {
        expect(fn () => app(BulkCampaignService::class)->create($account, $owner, '', collect([$conversation]), $template, []))
            ->toThrow(TemplateNotApprovedException::class);
    });

    expect(BulkCampaign::withoutGlobalScopes()->count())->toBe(0);
    Queue::assertNothingPushed();
});

// 5-controller) The same through HTTP: a pending template id is rejected with an error.
test('the store endpoint rejects a pending template', function () {
    [$owner, , $template, $conversation] = templateSetup(['status' => 'pending']);

    Queue::fake();

    app(TenantContext::class)->set($owner->tenant_id);

    $this->actingAs($owner)
        ->post(route('bulk.store'), ['message_template_id' => $template->id])
        ->assertSessionHasErrors('message_template_id');

    expect(BulkCampaign::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

// 6) A wrong variable count is rejected (Meta would reject the send).
test('a campaign with a mismatched variable count is rejected', function () {
    [$owner, $account, $template, $conversation] = templateSetup(['variable_count' => 2, 'body_text' => 'مرحباً {{1}} {{2}}']);

    Queue::fake();

    app(TenantContext::class)->run($owner->tenant_id, function () use ($account, $owner, $template, $conversation) {
        // Only one variable given for a 2-variable template.
        expect(fn () => app(BulkCampaignService::class)->create($account, $owner, '', collect([$conversation]), $template, ['only-one']))
            ->toThrow(TemplateVariableMismatchException::class);
    });

    expect(BulkCampaign::withoutGlobalScopes()->count())->toBe(0);
    Queue::assertNothingPushed();
});

// 7) The free-form path (no template) is UNCHANGED: a closed window is skipped.
test('the free-form path still skips a closed window', function () {
    [$owner, $account, , $conversation] = templateSetup([], ['window_expires_at' => now()->subHour()]);

    [$campaign, $recipient] = app(TenantContext::class)->run($owner->tenant_id, function () use ($account, $owner, $conversation) {
        // No template ⇒ free-form.
        $campaign = app(BulkCampaignService::class)->create($account, $owner, 'نص حر', collect([$conversation]));

        return [$campaign, $campaign->recipients()->firstOrFail()];
    });

    runTemplateJob($campaign, $recipient);

    Http::assertNothingSent();
    expect($recipient->refresh()->status)->toBe('skipped_window')
        ->and($campaign->usesTemplate())->toBeFalse();
});

// 8-isolation) A foreign tenant's template cannot be selected for a campaign — the
// store endpoint validates it does not exist (TenantScope on the exists rule, §1).
test('a foreign tenant template cannot be used in a campaign', function () {
    // Tenant B's approved template.
    $tenantB = Tenant::factory()->create();
    $foreignTemplate = app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);

        return MessageTemplate::factory()->create([
            'tenant_id' => $tenantB->id,
            'whatsapp_account_id' => $accountB->id,
            'status' => 'approved',
        ]);
    });
    app(TenantContext::class)->forget();

    // Tenant A owner tries to use B's template id.
    [$owner] = templateSetup();
    app(TenantContext::class)->set($owner->tenant_id);

    Queue::fake();

    $this->actingAs($owner)
        ->post(route('bulk.store'), ['message_template_id' => $foreignTemplate->id])
        ->assertSessionHasErrors('message_template_id');

    expect(BulkCampaign::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
