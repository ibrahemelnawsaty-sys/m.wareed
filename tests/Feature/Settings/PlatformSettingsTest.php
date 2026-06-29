<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// 1) The value is stored encrypted (ciphertext in the DB ≠ plaintext) and is
//    hidden from serialization (§13).
it('stores the value encrypted at rest and hides it from serialization', function () {
    $plaintext = 'sk-super-secret-platform-key';

    Setting::query()->create([
        'key' => 'openai_api_key',
        'value' => $plaintext,
    ]);

    // Raw column value (bypassing the cast) must NOT equal the plaintext.
    $raw = DB::table('settings')->where('key', 'openai_api_key')->value('value');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toBe($plaintext)
        ->and($raw)->not->toContain($plaintext);

    // The encrypted cast decrypts on read back to the original plaintext.
    $model = Setting::query()->where('key', 'openai_api_key')->first();
    expect($model->value)->toBe($plaintext);

    // $hidden: the secret never escapes through array/JSON serialization (§13).
    expect($model->toArray())->not->toHaveKey('value');
    expect(json_encode($model))->not->toContain($plaintext);
});

// 2) PlatformSettings::set/get round-trips through the encrypted store.
it('round-trips a value through set and get', function () {
    $settings = app(PlatformSettings::class);

    expect($settings->get('gemini_api_key'))->toBeNull();

    $settings->set('gemini_api_key', 'gem-key-123');

    expect($settings->get('gemini_api_key'))->toBe('gem-key-123');

    // Overwrite updates in place (non-destructive by key, §3).
    $settings->set('gemini_api_key', 'gem-key-456');

    expect($settings->get('gemini_api_key'))->toBe('gem-key-456')
        ->and(Setting::query()->where('key', 'gemini_api_key')->count())->toBe(1);

    // Clearing stores null and reads back as null.
    $settings->set('gemini_api_key', null);
    expect($settings->get('gemini_api_key'))->toBeNull();
});

// 3) An unset key returns null (no exception, no empty-string surprise).
it('returns null for an unset key', function () {
    expect(app(PlatformSettings::class)->get('deepseek_api_key'))->toBeNull();
});
