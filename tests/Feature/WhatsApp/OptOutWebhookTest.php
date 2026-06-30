<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use App\Services\AI\FallbackReplyService;
use App\Services\Bulk\BulkCampaignService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Phase 6d — opt-out keyword detection in the webhook (§11). An unsubscribe word
| marks the conversation opted out, and it is then excluded from bulk eligibility.
| This must not break idempotency / handoff / AI for the same message.
*/

const OPTOUT_APP_SECRET = 'optout-test-secret';

/**
 * @return array{0: string, 1: string}
 */
function optOutSignedPayload(string $phoneNumberId, string $waMessageId, string $from, string $text): array
{
    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA_ID',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '966500000000', 'phone_number_id' => $phoneNumberId],
                    'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Tester']]],
                    'messages' => [[
                        'from' => $from,
                        'id' => $waMessageId,
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]],
                ],
            ]],
        ]],
    ];

    $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $raw, OPTOUT_APP_SECRET);

    return [$raw, $signature];
}

function postOptOutWebhook(string $raw, string $signature)
{
    return test()->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $raw);
}

beforeEach(function () {
    config()->set('services.whatsapp.app_secret', OPTOUT_APP_SECRET);
    config()->set('services.whatsapp.verify_token', 'optout-verify-token');
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.OUT_'.uniqid()]],
        ], 200),
    ]);

    app()->bind(BotReplyService::class, FallbackReplyService::class);
    app(TenantContext::class)->forget();
});

// 8) An unsubscribe keyword stamps opted_out_at and removes the contact from
// bulk eligibility.
it('marks a conversation opted out when the customer sends an unsubscribe word', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_OPTOUT']);

    [$raw, $signature] = optOutSignedPayload($account->phone_number_id, 'wamid.OPTOUT_1', '966599999999', 'إيقاف');
    postOptOutWebhook($raw, $signature)->assertOk();

    $conversation = Conversation::withoutGlobalScopes()->firstOrFail();
    expect($conversation->opted_out_at)->not->toBeNull()
        ->and($conversation->isOptedOut())->toBeTrue();

    // Excluded from eligible recipients.
    app(TenantContext::class)->set($account->tenant_id);
    $eligible = app(BulkCampaignService::class)->eligibleRecipients($account->fresh());
    expect($eligible)->toHaveCount(0);
    app(TenantContext::class)->forget();
});

// English 'stop' works too.
it('recognises the English stop keyword', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_STOP']);

    [$raw, $signature] = optOutSignedPayload($account->phone_number_id, 'wamid.STOP_1', '966588888888', 'STOP');
    postOptOutWebhook($raw, $signature)->assertOk();

    expect(Conversation::withoutGlobalScopes()->firstOrFail()->isOptedOut())->toBeTrue();
});

// A normal message does NOT opt the contact out.
it('does not opt out a normal message', function () {
    $account = WhatsappAccount::factory()->create(['phone_number_id' => 'PNID_NORMAL']);

    [$raw, $signature] = optOutSignedPayload($account->phone_number_id, 'wamid.NORMAL_1', '966577777777', 'هل لديكم توصيل؟');
    postOptOutWebhook($raw, $signature)->assertOk();

    expect(Conversation::withoutGlobalScopes()->firstOrFail()->isOptedOut())->toBeFalse();
});
