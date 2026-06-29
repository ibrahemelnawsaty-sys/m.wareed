<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\WhatsappAccount;
use App\Services\AI\Providers\ChatProvider;
use App\Services\AI\Providers\DeepSeekClient;
use App\Services\AI\Providers\OpenAiClient;
use App\Services\Settings\PlatformSettings;

/**
 * Resolves, from a {@see WhatsappAccount}, everything a chat call needs: the
 * concrete {@see ChatProvider}, the API key, the model id, and the per-Mtok
 * prices — all driven by admin-configured account fields, never a silent swap
 * (§12).
 *
 * Provider selection (§12 — no silent model/provider change): comes from
 * `ai_provider` on the account, set by the admin. An unknown value falls back to
 * gemini (the platform default) rather than failing the reply path.
 *
 * Key resolution order (first non-empty wins):
 *   1. the tenant's own encrypted `ai_api_key`,
 *   2. the admin-managed encrypted platform key (`<provider>_api_key` setting),
 *   3. the `.env` config key (`services.<provider>.api_key`).
 * All three sources are secrets and are NEVER logged here (§13) — the resolver
 * only returns the value into the call layer.
 */
class ProviderResolver
{
    /**
     * Supported providers → their concrete client class. The first entry is the
     * default used for any unknown `ai_provider`.
     *
     * @var array<string, class-string<ChatProvider>>
     */
    private const PROVIDERS = [
        'gemini' => GeminiClient::class,
        'openai' => OpenAiClient::class,
        'deepseek' => DeepSeekClient::class,
    ];

    private const DEFAULT_PROVIDER = 'gemini';

    public function __construct(
        private readonly PlatformSettings $platformSettings,
    ) {}

    /**
     * Resolve the provider, key, model and prices for this account.
     *
     * @return array{
     *     provider: ChatProvider,
     *     apiKey: string|null,
     *     model: string,
     *     inMicrosPerMtok: int,
     *     outMicrosPerMtok: int,
     * }
     */
    public function resolve(WhatsappAccount $account): array
    {
        $name = $this->resolveProviderName($account);

        /** @var ChatProvider $provider */
        $provider = app(self::PROVIDERS[$name]);

        return [
            'provider' => $provider,
            'apiKey' => $this->resolveApiKey($account, $name),
            'model' => $this->resolveModel($account, $name),
            'inMicrosPerMtok' => (int) config("services.{$name}.input_micros_per_mtok", 0),
            'outMicrosPerMtok' => (int) config("services.{$name}.output_micros_per_mtok", 0),
        ];
    }

    /**
     * The account's provider, normalised to a supported key. Unknown ⇒ gemini
     * (the platform default). Never a silent model swap: the value is exactly
     * what the admin stored on the account (§12).
     */
    private function resolveProviderName(WhatsappAccount $account): string
    {
        $name = strtolower(trim((string) $account->ai_provider));

        return array_key_exists($name, self::PROVIDERS) ? $name : self::DEFAULT_PROVIDER;
    }

    /**
     * Tenant key → platform setting → .env, first non-empty. Never logged (§13).
     */
    private function resolveApiKey(WhatsappAccount $account, string $provider): ?string
    {
        // 1) The tenant's own encrypted key (decrypted by the model cast).
        $tenantKey = $account->ai_api_key;

        if (is_string($tenantKey) && $tenantKey !== '') {
            return $tenantKey;
        }

        // 2) The admin-managed encrypted platform key for this provider.
        $platformKey = $this->platformSettings->get("{$provider}_api_key");

        if (is_string($platformKey) && $platformKey !== '') {
            return $platformKey;
        }

        // 3) The .env config fallback.
        $envKey = config("services.{$provider}.api_key");

        return is_string($envKey) && $envKey !== '' ? $envKey : null;
    }

    /**
     * The account's model, else the provider default from config. Never a silent
     * swap — the account value is admin-set (§12).
     */
    private function resolveModel(WhatsappAccount $account, string $provider): string
    {
        $accountModel = trim((string) $account->ai_model);

        if ($accountModel !== '') {
            return $accountModel;
        }

        $configModel = config("services.{$provider}.model");

        return is_string($configModel) && $configModel !== '' ? $configModel : 'gemini-2.5-flash-lite';
    }
}
