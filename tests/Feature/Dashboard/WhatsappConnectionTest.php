<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

/*
 * Phase 7a — WhatsApp connection wizard (verify + test send). Owner-only,
 * tenant-scoped, and the access token must NEVER leak into a response or log
 * (§13). The test send uses a pre-approved template so it works with no open
 * 24h window (§11).
 */

beforeEach(function () {
    config()->set('services.whatsapp.api_version', 'v21.0');
    config()->set('services.whatsapp.graph_base', 'https://graph.facebook.com');

    app(TenantContext::class)->forget();
});

/** An agent (role=agent) inside the provisioned owner's tenant. */
function connectionAgent(): User
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

// 1) verify success → live status flashed; the GET hits Meta with the token.
test('verify probes Meta and flashes the live connection status', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'verified_name' => 'Wareed Store',
            'display_phone_number' => '+966 50 000 0001',
            'quality_rating' => 'GREEN',
            'code_verification_status' => 'VERIFIED',
            'id' => '111222333444555',
        ], 200),
    ]);

    $owner = provisionTenant([
        'phone_number_id' => '111222333444555',
        'access_token' => 'EAAG-verify-token-secret',
    ]);

    $response = $this->actingAs($owner)
        ->from(route('whatsapp.edit'))
        ->post(route('whatsapp.verify'));

    $response->assertRedirect(route('whatsapp.edit'));
    $response->assertSessionHas('connection_status', fn ($status) => is_array($status)
        && $status['verified_name'] === 'Wareed Store'
        && $status['quality_rating'] === 'GREEN'
        && $status['code_verification_status'] === 'VERIFIED');
    $response->assertSessionHasNoErrors();

    // Correct GET to the phone_number_id node, carrying the bearer token.
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_contains($request->url(), '/v21.0/111222333444555')
        && str_contains($request->url(), 'fields=verified_name')
        && $request->hasHeader('Authorization', 'Bearer EAAG-verify-token-secret'));
});

// 2) verify with no token → gentle error, no HTTP call.
test('verify without a token shows a gentle error and never calls Meta', function () {
    Http::fake();

    $owner = provisionTenant([
        'phone_number_id' => '111222333444555',
        'access_token' => null,
    ]);

    $response = $this->actingAs($owner)
        ->from(route('whatsapp.edit'))
        ->post(route('whatsapp.verify'));

    $response->assertRedirect(route('whatsapp.edit'));
    $response->assertSessionHasErrors('connection');

    Http::assertNothingSent();
});

// 3) verify when Meta rejects (401) → gentle error, no 500, token not leaked.
test('verify surfaces a gentle error and never leaks the token when Meta fails', function () {
    $secret = 'EAAG-must-not-leak-401';

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth token']], 401),
    ]);

    $owner = provisionTenant([
        'phone_number_id' => '111222333444555',
        'access_token' => $secret,
    ]);

    $response = $this->actingAs($owner)
        ->from(route('whatsapp.edit'))
        ->post(route('whatsapp.verify'));

    $response->assertRedirect(route('whatsapp.edit')); // not a 500
    $response->assertSessionHasErrors('connection');

    // The token must never appear in the response body or the flashed errors.
    expect($response->getContent())->not->toContain($secret);
    $errors = session('errors')->getBag('default')->get('connection');
    foreach ($errors as $message) {
        expect($message)->not->toContain($secret);
    }
});

// 4) test send → a hello_world template goes to the right number; success banner.
test('test send dispatches a hello_world template to the destination number', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.TEST123']],
        ], 200),
    ]);

    $owner = provisionTenant([
        'phone_number_id' => '111222333444555',
        'access_token' => 'EAAG-test-token-secret',
    ]);

    $response = $this->actingAs($owner)
        ->from(route('whatsapp.edit'))
        ->post(route('whatsapp.test'), ['to' => '966500000001']);

    $response->assertRedirect(route('whatsapp.edit'));
    $response->assertSessionHas('status', 'whatsapp-test-sent');
    $response->assertSessionHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/v21.0/111222333444555/messages')
        && $request['type'] === 'template'
        && $request['to'] === '966500000001'
        && $request['template']['name'] === 'hello_world'
        && $request['template']['language']['code'] === 'en_US');
});

// 5) test send with an invalid number → validation error, nothing sent.
test('test send with an invalid number is rejected and nothing is sent', function () {
    Http::fake();

    $owner = provisionTenant([
        'phone_number_id' => '111222333444555',
        'access_token' => 'EAAG-test-token-secret',
    ]);

    $response = $this->actingAs($owner)
        ->from(route('whatsapp.edit'))
        ->post(route('whatsapp.test'), ['to' => '+966 50 abc']);

    $response->assertRedirect(route('whatsapp.edit'));
    $response->assertSessionHasErrors('to');

    Http::assertNothingSent();
});

// 6) owner-only: an agent is forbidden from both endpoints.
test('an agent is forbidden from verify and test', function () {
    Http::fake();

    $agent = connectionAgent();

    $this->actingAs($agent)->post(route('whatsapp.verify'))->assertForbidden();
    $this->actingAs($agent)->post(route('whatsapp.test'), ['to' => '966500000001'])->assertForbidden();

    Http::assertNothingSent();
});

// Isolation guard: verify/test only ever touch THIS tenant's account.
test('verify resolves only the active tenant account', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'verified_name' => 'Tenant A',
            'display_phone_number' => '+966 50 000 0001',
            'quality_rating' => 'GREEN',
            'code_verification_status' => 'VERIFIED',
        ], 200),
    ]);

    // Tenant B owns an account with a recognisable phone_number_id + token.
    $tenantB = Tenant::factory()->create();
    app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        WhatsappAccount::factory()->create([
            'tenant_id' => $tenantB->id,
            'phone_number_id' => 'BBB000000000000',
            'access_token' => 'tenant-b-secret',
        ]);
    });

    app(TenantContext::class)->forget();
    $ownerA = provisionTenant([
        'phone_number_id' => 'AAA000000000000',
        'access_token' => 'tenant-a-secret',
    ]);

    $this->actingAs($ownerA)
        ->from(route('whatsapp.edit'))
        ->post(route('whatsapp.verify'))
        ->assertSessionHasNoErrors();

    // The probe must hit tenant A's number, never tenant B's.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'AAA000000000000'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'BBB000000000000'));
});
