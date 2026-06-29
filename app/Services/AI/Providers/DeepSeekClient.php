<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

/**
 * DeepSeek provider (§12). DeepSeek is OpenAI-compatible, so the whole transport
 * is inherited from {@see OpenAiCompatibleClient}; this class only pins the
 * config block and default base URL. The key rides in the Authorization header
 * and is never logged (§13).
 */
final class DeepSeekClient extends OpenAiCompatibleClient
{
    protected function configKey(): string
    {
        return 'deepseek';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.deepseek.com';
    }
}
