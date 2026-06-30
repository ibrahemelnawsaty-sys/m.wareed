<?php

declare(strict_types=1);

use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

/*
 * Direct service-level coverage for the Phase 7a additions: sendTemplate builds
 * the correct Cloud API body, and verifyConnection issues the right GET. The
 * bearer token is sent in the header but never logged (§13).
 */

beforeEach(function () {
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    app(TenantContext::class)->forget();
});

/** A bound tenant + its single account (so factory create passes TenantScope). */
function clientAccount(array $attributes = []): WhatsappAccount
{
    provisionTenant(array_merge([
        'phone_number_id' => '111222333444555',
        'access_token' => 'EAAG-client-secret',
    ], $attributes));

    return WhatsappAccount::query()->firstOrFail();
}

test('sendTemplate posts a correctly shaped template body', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.TPL999']],
        ], 200),
    ]);

    $account = clientAccount();

    $result = app(WhatsAppClient::class)
        ->sendTemplate($account, '966500000001', 'hello_world', 'en_US');

    expect($result['wa_message_id'])->toBe('wamid.TPL999');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/v21.0/111222333444555/messages')
        && $request['messaging_product'] === 'whatsapp'
        && $request['to'] === '966500000001'
        && $request['type'] === 'template'
        && $request['template']['name'] === 'hello_world'
        && $request['template']['language']['code'] === 'en_US'
        // No empty components array on a parameter-less template.
        && ! array_key_exists('components', $request['template'])
        && $request->hasHeader('Authorization', 'Bearer EAAG-client-secret'));
});

test('sendTemplate includes components when provided', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.TPL_C']],
        ], 200),
    ]);

    $account = clientAccount();
    $components = [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Wareed']]]];

    app(WhatsAppClient::class)
        ->sendTemplate($account, '966500000002', 'welcome', 'ar', $components);

    Http::assertSent(fn ($request) => $request['template']['components'] === $components);
});

test('sendTemplate throws a RuntimeException without leaking the token on failure', function () {
    $secret = 'EAAG-leak-check-tpl';

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad']], 400),
    ]);

    $account = clientAccount(['access_token' => $secret]);

    try {
        app(WhatsAppClient::class)->sendTemplate($account, '966500000001', 'hello_world', 'en_US');
        $this->fail('Expected a RuntimeException.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toContain($secret);
        expect($e->getMessage())->toContain('111222333444555'); // identified by phone_number_id only
    }
});

test('verifyConnection issues the right GET and returns safe string fields', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'verified_name' => 'Wareed',
            'display_phone_number' => '+966 50 000 0001',
            'quality_rating' => 'GREEN',
            'code_verification_status' => 'VERIFIED',
        ], 200),
    ]);

    $account = clientAccount();

    $info = app(WhatsAppClient::class)->verifyConnection($account);

    expect($info)->toBe([
        'verified_name' => 'Wareed',
        'display_phone_number' => '+966 50 000 0001',
        'quality_rating' => 'GREEN',
        'code_verification_status' => 'VERIFIED',
    ]);

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_contains($request->url(), '/v21.0/111222333444555')
        && str_contains($request->url(), 'fields=verified_name%2Cdisplay_phone_number%2Cquality_rating%2Ccode_verification_status')
        && $request->hasHeader('Authorization', 'Bearer EAAG-client-secret'));
});

test('verifyConnection coerces missing fields to empty strings', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'verified_name' => 'Only Name',
            // other fields omitted by Meta
        ], 200),
    ]);

    $account = clientAccount();

    $info = app(WhatsAppClient::class)->verifyConnection($account);

    expect($info['verified_name'])->toBe('Only Name')
        ->and($info['display_phone_number'])->toBe('')
        ->and($info['quality_rating'])->toBe('')
        ->and($info['code_verification_status'])->toBe('');
});

// Review fix (§13): a Meta error must surface a CLEAN RuntimeException with no
// `previous`, so report()/Sentry can never walk the chain down to the Guzzle
// request that still holds `Authorization: Bearer {token}`.
test('a Cloud API failure never chains the token-bearing exception (previous is null)', function () {
    $secret = 'EAAG-must-not-chain';

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'invalid token']], 401),
    ]);

    $account = clientAccount(['access_token' => $secret]);
    $client = app(WhatsAppClient::class);

    $calls = [
        fn () => $client->sendText($account, '966500000001', 'hi'),
        fn () => $client->sendTemplate($account, '966500000001', 'hello_world', 'en_US'),
        fn () => $client->verifyConnection($account),
    ];

    foreach ($calls as $call) {
        try {
            $call();
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            expect($e->getPrevious())->toBeNull()
                ->and($e->getMessage())->not->toContain($secret);
        }
    }
});
