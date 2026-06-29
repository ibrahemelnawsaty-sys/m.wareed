<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Shared transport for OpenAI-style `/chat/completions` providers (§12).
 *
 * OpenAI and DeepSeek speak the same wire format, so the request build, the
 * single synchronous HTTP call (no token-draining retry loop, §12) and the
 * response parsing live here once. Concrete subclasses only declare which
 * `config('services.*')` block supplies the base URL, timeout and default model.
 *
 * Security (§13): the key travels ONLY in the `Authorization` header — never in
 * the URL, a log, or an exception message. The thrown {@see ChatProviderException}
 * carries just the model id and HTTP status; the request body (which contains the
 * prompt) and the key are deliberately excluded.
 */
abstract class OpenAiCompatibleClient implements ChatProvider
{
    /**
     * The `config('services.<key>')` block name backing this provider.
     */
    abstract protected function configKey(): string;

    /**
     * Default base URL when none is configured.
     */
    abstract protected function defaultBaseUrl(): string;

    public function generate(
        string $systemInstruction,
        array $turns,
        float $temperature,
        ?string $apiKey,
        string $model,
    ): array {
        $configKey = $this->configKey();

        $baseUrl = rtrim((string) config("services.{$configKey}.base_url", $this->defaultBaseUrl()), '/');

        if ($baseUrl === '') {
            $baseUrl = rtrim($this->defaultBaseUrl(), '/');
        }

        $timeout = (int) config("services.{$configKey}.timeout", 20);

        $url = "{$baseUrl}/chat/completions";

        // System instruction first, then the bounded conversation turns. The
        // provider expects 'assistant' where our turns use 'model' (§12 — the
        // bot's prior replies); customer text stays a 'user' turn.
        $messages = [
            ['role' => 'system', 'content' => $systemInstruction],
        ];

        foreach ($turns as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'model' ? 'assistant' : 'user',
                'content' => $turn['text'],
            ];
        }

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
        ];

        try {
            $response = Http::timeout($timeout)
                // Key in the header only — never the URL/query (§13).
                ->withToken((string) $apiKey)
                ->asJson()
                ->acceptJson()
                ->post($url, $body);
        } catch (ConnectionException $e) {
            // Transport failure (timeout, DNS, refused). Surface explicitly (§3);
            // the message carries no key and no body (§13).
            throw new ChatProviderException(
                sprintf('%s transport failure for model [%s].', $configKey, $model),
                previous: $e,
            );
        }

        if ($response->failed()) {
            // Explicit failure, no swallowing (§3). No key, no URL, no body —
            // only the safe status + model (§13).
            throw new ChatProviderException(sprintf(
                '%s HTTP error: status %d for model [%s].',
                $configKey,
                $response->status(),
                $model,
            ));
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        $text = data_get($json, 'choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new ChatProviderException(
                sprintf('%s returned no usable content for model [%s].', $configKey, $model),
            );
        }

        $promptTokens = (int) (data_get($json, 'usage.prompt_tokens') ?? 0);
        $completionTokens = (int) (data_get($json, 'usage.completion_tokens') ?? 0);

        return [
            'text' => trim($text),
            'tokensIn' => max(0, $promptTokens),
            'tokensOut' => max(0, $completionTokens),
        ];
    }
}
