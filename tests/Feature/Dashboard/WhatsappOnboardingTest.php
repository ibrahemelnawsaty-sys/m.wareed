<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/*
 * Phase 8 — self-serve onboarding: the step-by-step connection guide renders
 * (this also guards against a Blade parse error in the guide view), is
 * owner-only, and surfaces the copy-ready webhook values. The per-tenant
 * app_secret is stored encrypted and non-destructively (§13, §3).
 */

test('the connection guide renders for the owner with the copy-ready webhook values', function () {
    config()->set('services.whatsapp.verify_token', 'my-verify-token');

    app(TenantContext::class)->forget();
    $owner = provisionTenant();
    app(TenantContext::class)->forget();

    $this->actingAs($owner)->get(route('whatsapp.guide'))
        ->assertOk()
        ->assertSee('my-verify-token')          // verify token, copy field
        ->assertSee('/api/whatsapp/webhook');   // callback URL
});

test('the connection guide is owner-only', function () {
    app(TenantContext::class)->forget();
    $owner = provisionTenant();
    $agent = User::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'role' => 'agent',
        'email_verified_at' => now(),
    ]);
    app(TenantContext::class)->forget();

    $this->actingAs($agent)->get(route('whatsapp.guide'))->assertForbidden();
});

test('app_secret is stored encrypted and kept when the field is left blank', function () {
    app(TenantContext::class)->forget();
    $owner = provisionTenant();
    app(TenantContext::class)->set($owner->tenant_id);

    // Save a per-tenant app secret.
    $this->actingAs($owner)->put(route('whatsapp.update'), [
        'phone_number_id' => 'PNID_ONBOARD',
        'app_secret' => 'tenant-app-secret',
    ])->assertRedirect();

    $account = WhatsappAccount::query()->firstOrFail();
    expect($account->app_secret)->toBe('tenant-app-secret'); // decrypted via cast

    // The stored column is ciphertext, never the plaintext secret (§13).
    $raw = (string) DB::table('whatsapp_accounts')->where('id', $account->id)->value('app_secret');
    expect($raw)->not->toBe('tenant-app-secret')->and($raw)->not->toBe('');

    // A later save with a blank secret keeps the stored one (non-destructive, §3).
    $this->actingAs($owner)->put(route('whatsapp.update'), [
        'phone_number_id' => 'PNID_ONBOARD',
        'app_secret' => '',
    ])->assertRedirect();

    expect(WhatsappAccount::query()->firstOrFail()->app_secret)->toBe('tenant-app-secret');
});
