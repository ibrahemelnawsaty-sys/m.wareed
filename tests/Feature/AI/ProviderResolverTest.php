<?php

declare(strict_types=1);

use App\Models\WhatsappAccount;
use App\Services\AI\GeminiClient;
use App\Services\AI\ProviderResolver;
use App\Services\AI\Providers\DeepSeekClient;
use App\Services\AI\Providers\OpenAiClient;
use App\Services\Settings\PlatformSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.gemini.api_key', null);
    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');
    config()->set('services.gemini.input_micros_per_mtok', 100000);
    config()->set('services.gemini.output_micros_per_mtok', 400000);

    config()->set('services.openai.api_key', null);
    config()->set('services.openai.model', 'gpt-4o-mini');
    config()->set('services.openai.input_micros_per_mtok', 150000);
    config()->set('services.openai.output_micros_per_mtok', 600000);

    config()->set('services.deepseek.api_key', null);
    config()->set('services.deepseek.model', 'deepseek-chat');

    app(TenantContext::class)->forget();
});

/**
 * Create a bound account with the given attributes.
 */
function resolverAccount(array $attributes = []): WhatsappAccount
{
    // ai_model is NOT NULL (it has a DB default); an "unset" model is the empty
    // string, exactly as the admin would leave the field blank.
    $account = WhatsappAccount::factory()->create(array_merge([
        'ai_provider' => 'gemini',
        'ai_model' => '',
        'ai_api_key' => null,
    ], $attributes));

    app(TenantContext::class)->set($account->tenant_id);

    return $account;
}

// --- Provider selection -----------------------------------------------------

it('resolves each known provider to its client, unknown falls back to gemini', function () {
    $resolver = app(ProviderResolver::class);

    expect($resolver->resolve(resolverAccount(['ai_provider' => 'gemini']))['provider'])
        ->toBeInstanceOf(GeminiClient::class);
    expect($resolver->resolve(resolverAccount(['ai_provider' => 'openai']))['provider'])
        ->toBeInstanceOf(OpenAiClient::class);
    expect($resolver->resolve(resolverAccount(['ai_provider' => 'deepseek']))['provider'])
        ->toBeInstanceOf(DeepSeekClient::class);

    // Unknown provider ⇒ gemini default (no silent failure on the reply path).
    expect($resolver->resolve(resolverAccount(['ai_provider' => 'mystery']))['provider'])
        ->toBeInstanceOf(GeminiClient::class);
});

// --- Model & pricing --------------------------------------------------------

it('prefers the account model and reads provider prices from config', function () {
    $resolver = app(ProviderResolver::class);

    // Account model wins over config default.
    $withModel = $resolver->resolve(resolverAccount(['ai_provider' => 'openai', 'ai_model' => 'gpt-4o']));
    expect($withModel['model'])->toBe('gpt-4o')
        ->and($withModel['inMicrosPerMtok'])->toBe(150000)
        ->and($withModel['outMicrosPerMtok'])->toBe(600000);

    // No account model (blank) ⇒ provider config default.
    $noModel = $resolver->resolve(resolverAccount(['ai_provider' => 'openai', 'ai_model' => '']));
    expect($noModel['model'])->toBe('gpt-4o-mini');
});

// --- Key resolution order: tenant → platform → env --------------------------

it('uses the tenant key first when present', function () {
    config()->set('services.gemini.api_key', 'ENV-KEY');
    app(PlatformSettings::class)->set('gemini_api_key', 'PLATFORM-KEY');

    $account = resolverAccount(['ai_provider' => 'gemini', 'ai_api_key' => 'TENANT-KEY']);

    expect(app(ProviderResolver::class)->resolve($account)['apiKey'])->toBe('TENANT-KEY');
});

it('falls back to the platform setting when the tenant key is empty', function () {
    config()->set('services.gemini.api_key', 'ENV-KEY');
    app(PlatformSettings::class)->set('gemini_api_key', 'PLATFORM-KEY');

    $account = resolverAccount(['ai_provider' => 'gemini', 'ai_api_key' => null]);

    expect(app(ProviderResolver::class)->resolve($account)['apiKey'])->toBe('PLATFORM-KEY');
});

it('falls back to the env key when neither tenant nor platform key is set', function () {
    config()->set('services.gemini.api_key', 'ENV-KEY');
    // No platform setting stored.

    $account = resolverAccount(['ai_provider' => 'gemini', 'ai_api_key' => null]);

    expect(app(ProviderResolver::class)->resolve($account)['apiKey'])->toBe('ENV-KEY');
});

it('resolves the per-provider platform key for openai', function () {
    app(PlatformSettings::class)->set('openai_api_key', 'OPENAI-PLATFORM-KEY');

    $account = resolverAccount(['ai_provider' => 'openai', 'ai_api_key' => null]);

    expect(app(ProviderResolver::class)->resolve($account)['apiKey'])->toBe('OPENAI-PLATFORM-KEY');
});

it('returns a null key when no source provides one', function () {
    $account = resolverAccount(['ai_provider' => 'gemini', 'ai_api_key' => null]);

    expect(app(ProviderResolver::class)->resolve($account)['apiKey'])->toBeNull();
});
