<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

/**
 * OpenAI Chat Completions provider (§12). All transport, request build and
 * response parsing are inherited from {@see OpenAiCompatibleClient}; this class
 * only pins the config block and default base URL. The key rides in the
 * Authorization header and is never logged (§13).
 */
final class OpenAiClient extends OpenAiCompatibleClient
{
    protected function configKey(): string
    {
        return 'openai';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }
}
