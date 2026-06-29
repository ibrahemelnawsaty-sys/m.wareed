<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('the whatsapp page shows the correct callback url and verify token', function () {
    config()->set('app.url', 'https://m.wareed.vip');
    config()->set('services.whatsapp.verify_token', 'verify-token-abc');

    $owner = provisionTenant();

    $response = $this->actingAs($owner)->get(route('whatsapp.edit'));

    $response->assertOk();
    $response->assertSee('https://m.wareed.vip/api/whatsapp/webhook');
    $response->assertSee('verify-token-abc');
});

test('updating the whatsapp link saves phone_number_id and encrypts the access token', function () {
    $owner = provisionTenant(['access_token' => null, 'phone_number_id' => null]);

    $account = WhatsappAccount::query()->firstOrFail();
    $plainToken = 'EAAG-super-secret-token-value-123456';

    $response = $this->actingAs($owner)->put(route('whatsapp.update'), [
        'display_name' => 'متجر وريد',
        'phone_number_id' => '111222333444555',
        'waba_id' => '999888777666555',
        'access_token' => $plainToken,
    ]);

    $response->assertRedirect(route('whatsapp.edit'));

    $account->refresh();
    expect($account->phone_number_id)->toBe('111222333444555');
    expect($account->access_token)->toBe($plainToken); // decrypted via cast

    // Ciphertext in the DB must NOT equal the plaintext (§13 — encrypted at rest).
    $rawToken = DB::table('whatsapp_accounts')->where('id', $account->id)->value('access_token');
    expect($rawToken)->not->toBe($plainToken);
    expect($rawToken)->not->toContain($plainToken);
});

test('the plaintext access token is never rendered in the html', function () {
    $plainToken = 'EAAG-never-render-me-token-987654';
    $owner = provisionTenant(['access_token' => $plainToken]);

    $response = $this->actingAs($owner)->get(route('whatsapp.edit'));

    $response->assertOk();
    $response->assertDontSee($plainToken);
});

test('an empty access token field keeps the existing saved token', function () {
    $plainToken = 'EAAG-keep-me-token-555';
    $owner = provisionTenant(['access_token' => $plainToken]);

    $this->actingAs($owner)->put(route('whatsapp.update'), [
        'phone_number_id' => '123123123123123',
        'access_token' => '',
    ]);

    $account = WhatsappAccount::query()->firstOrFail();
    expect($account->access_token)->toBe($plainToken);
    expect($account->phone_number_id)->toBe('123123123123123');
});

test('a tenant cannot read or modify another tenant whatsapp account', function () {
    // Tenant B owns an account with a known token.
    $tenantB = Tenant::factory()->create();
    app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        WhatsappAccount::factory()->create([
            'tenant_id' => $tenantB->id,
            'phone_number_id' => 'BBB000000000000',
            'access_token' => 'tenant-b-secret',
        ]);
    });

    // Tenant A signs in.
    app(TenantContext::class)->forget();
    $ownerA = provisionTenant(['phone_number_id' => 'AAA000000000000']);

    $response = $this->actingAs($ownerA)->get(route('whatsapp.edit'));
    $response->assertOk();
    $response->assertSee('AAA000000000000');
    $response->assertDontSee('BBB000000000000');
    $response->assertDontSee('tenant-b-secret');

    // Tenant A's update must only ever touch tenant A's row.
    $this->actingAs($ownerA)->put(route('whatsapp.update'), [
        'phone_number_id' => 'AAA111111111111',
    ]);

    app(TenantContext::class)->forget();
    $bRow = WhatsappAccount::query()->withoutGlobalScopes()
        ->where('tenant_id', $tenantB->id)->firstOrFail();
    expect($bRow->phone_number_id)->toBe('BBB000000000000'); // untouched
});
