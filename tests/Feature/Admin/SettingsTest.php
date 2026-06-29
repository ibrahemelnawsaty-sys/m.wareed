<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use App\Support\Tenancy\TenantContext;

/*
| Phase 4c — admin management of the platform AI keys (§13). These are platform
| secrets: admin-only, masked on render (presence only, never the value), and a
| blank field on save keeps the stored key (non-destructive, §3).
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
    app(PlatformSettings::class)->forget();
});

test('a regular (non-admin) user is forbidden from the settings page (GET and PUT)', function () {
    $owner = User::factory()->create(); // ordinary owner with a tenant
    expect($owner->isAdmin())->toBeFalse();

    $this->actingAs($owner)->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs($owner)->put(route('admin.settings.update'), [
        'openai_api_key' => 'sk-should-never-be-saved',
    ])->assertForbidden();

    // Nothing was written by the forbidden request.
    expect(app(PlatformSettings::class)->get('openai_api_key'))->toBeNull();
});

test('a super-admin can open the settings page', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk();
});

test('the admin saves a key and it is stored encrypted (not plaintext)', function () {
    $admin = makeAdmin();
    $plaintext = 'sk-openai-secret-1234567890';

    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'openai_api_key' => $plaintext,
    ])->assertRedirect(route('admin.settings.edit'));

    // The service round-trips the decrypted value back to its caller.
    app(PlatformSettings::class)->forget();
    expect(app(PlatformSettings::class)->get('openai_api_key'))->toBe($plaintext);

    // The raw column is ciphertext, never the plaintext key (§3, §13).
    $raw = Setting::query()->where('key', 'openai_api_key')->value('value');
    expect($raw)->not->toBeNull();
    // `value` is the encrypted cast — fetched via Eloquent it decrypts; assert
    // the ATTRIBUTE round-trips while the persisted ciphertext differs. Read the
    // stored column straight from the DB to prove it is not the plaintext.
    $rawDb = Setting::query()->where('key', 'openai_api_key')->getQuery()->value('value');
    expect($rawDb)->not->toBe($plaintext);
    expect($rawDb)->not->toContain($plaintext);
});

test('submitting a blank field keeps the previously stored key (non-destructive, §3)', function () {
    $admin = makeAdmin();

    // Seed an existing key.
    app(PlatformSettings::class)->set('openai_api_key', 'sk-existing-key-keep-me');
    app(PlatformSettings::class)->forget();

    // Submit the form with the field blank (and another provider filled).
    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'openai_api_key' => '',
        'gemini_api_key' => 'gm-new-gemini-key',
    ])->assertRedirect(route('admin.settings.edit'));

    app(PlatformSettings::class)->forget();
    // The blank field did NOT erase the stored key.
    expect(app(PlatformSettings::class)->get('openai_api_key'))->toBe('sk-existing-key-keep-me');
    // The filled field was written.
    expect(app(PlatformSettings::class)->get('gemini_api_key'))->toBe('gm-new-gemini-key');
});

test('omitting a field entirely keeps the previously stored key (non-destructive, §3)', function () {
    $admin = makeAdmin();

    app(PlatformSettings::class)->set('deepseek_api_key', 'ds-existing-key');
    app(PlatformSettings::class)->forget();

    // The deepseek field is not present in the payload at all.
    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'gemini_api_key' => 'gm-key',
    ])->assertRedirect(route('admin.settings.edit'));

    app(PlatformSettings::class)->forget();
    expect(app(PlatformSettings::class)->get('deepseek_api_key'))->toBe('ds-existing-key');
});

test('the real key value never appears in the settings page HTML (masking, §13)', function () {
    $admin = makeAdmin();
    $secret = 'sk-super-secret-value-do-not-leak-XYZ';

    app(PlatformSettings::class)->set('openai_api_key', $secret);

    $response = $this->actingAs($admin)->get(route('admin.settings.edit'));

    $response->assertOk();
    // The page states presence ("مُخزّن") but never prints the secret itself.
    $response->assertDontSee($secret);
    $response->assertSee('مُخزّن');
});

test('the settings page shows "not set" for providers without a key', function () {
    $admin = makeAdmin();

    $response = $this->actingAs($admin)->get(route('admin.settings.edit'));

    $response->assertOk();
    $response->assertSee('غير مُدخل');
    // View receives presence flags, never the values.
    $response->assertViewHas('hasGemini', false);
    $response->assertViewHas('hasOpenai', false);
    $response->assertViewHas('hasDeepseek', false);
});
