<?php

declare(strict_types=1);

use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

/*
 * Phase 7b: sendInteractiveList builds the correct Cloud API List Message body,
 * omits empty optional fields, and — like every other client method — surfaces a
 * Meta failure as a clean RuntimeException that never leaks the bearer token (§13).
 */

beforeEach(function () {
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    app(TenantContext::class)->forget();
});

function listClientAccount(array $attributes = []): WhatsappAccount
{
    provisionTenant(array_merge([
        'phone_number_id' => '111222333444555',
        'access_token' => 'EAAG-list-secret',
    ], $attributes));

    return WhatsappAccount::query()->firstOrFail();
}

test('sendInteractiveList posts a correctly shaped list body', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.LIST1']],
        ], 200),
    ]);

    $account = listClientAccount();

    $result = app(WhatsAppClient::class)->sendInteractiveList(
        $account,
        '966500000001',
        'مرحباً',
        'اختر خدمة',
        'الخدمات',
        'نسعد بخدمتك',
        [
            ['id' => 'row_0', 'title' => 'ساعات العمل', 'description' => 'من 9 إلى 5'],
            ['id' => 'row_1', 'title' => 'تحدث مع موظف', 'description' => null],
        ],
    );

    expect($result['wa_message_id'])->toBe('wamid.LIST1');

    Http::assertSent(function ($request) {
        $i = $request['interactive'];

        return $request->method() === 'POST'
            && str_contains($request->url(), '/v21.0/111222333444555/messages')
            && $request['type'] === 'interactive'
            && $i['type'] === 'list'
            && $i['header'] === ['type' => 'text', 'text' => 'مرحباً']
            && $i['body'] === ['text' => 'اختر خدمة']
            && $i['footer'] === ['text' => 'نسعد بخدمتك']
            && $i['action']['button'] === 'الخدمات'
            && $i['action']['sections'][0]['title'] === 'خدماتنا'
            && count($i['action']['sections'][0]['rows']) === 2
            && $i['action']['sections'][0]['rows'][0] === ['id' => 'row_0', 'title' => 'ساعات العمل', 'description' => 'من 9 إلى 5']
            // null description is OMITTED, not sent as an empty key.
            && $i['action']['sections'][0]['rows'][1] === ['id' => 'row_1', 'title' => 'تحدث مع موظف']
            && $request->hasHeader('Authorization', 'Bearer EAAG-list-secret');
    });
});

test('sendInteractiveList omits header and footer when empty', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.LIST2']],
        ], 200),
    ]);

    $account = listClientAccount();

    app(WhatsAppClient::class)->sendInteractiveList(
        $account,
        '966500000002',
        null,
        'اختر',
        'القائمة',
        null,
        [['id' => 'row_0', 'title' => 'خيار']],
    );

    Http::assertSent(function ($request) {
        $i = $request['interactive'];

        return ! array_key_exists('header', $i)
            && ! array_key_exists('footer', $i)
            && $i['action']['sections'][0]['rows'][0] === ['id' => 'row_0', 'title' => 'خيار'];
    });
});

test('sendInteractiveList never leaks the token on failure (no previous)', function () {
    $secret = 'EAAG-list-leak-check';

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad']], 400),
    ]);

    $account = listClientAccount(['access_token' => $secret]);

    try {
        app(WhatsAppClient::class)->sendInteractiveList(
            $account,
            '966500000001',
            null,
            'اختر',
            'القائمة',
            null,
            [['id' => 'row_0', 'title' => 'خيار']],
        );
        $this->fail('Expected a RuntimeException.');
    } catch (RuntimeException $e) {
        expect($e->getPrevious())->toBeNull()
            ->and($e->getMessage())->not->toContain($secret)
            ->and($e->getMessage())->toContain('111222333444555');
    }
});
