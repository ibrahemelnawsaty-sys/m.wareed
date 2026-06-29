<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Providers\ChatProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin transport over the Gemini `generateContent` REST endpoint (ADR-04, §12).
 *
 * Responsibilities are deliberately narrow: build the request body, perform a
 * single synchronous HTTP call (no internal retry loop that would drain tokens,
 * §12), and return the reply text plus token usage. It does not decide on
 * fallbacks or pricing — that belongs to the reply service.
 *
 * The API key is passed in the `x-goog-api-key` header and is NEVER logged (§13).
 */
class GeminiClient implements ChatProvider
{
    /**
     * Call Gemini and return the generated text with token accounting.
     *
     * @param  string  $systemInstruction  The hardened system prompt (§12).
     * @param  list<array{role: 'user'|'model', text: string}>  $turns  Ordered
     *                                                                  conversation turns (last N only, oldest first).
     * @param  float  $temperature  Sampling temperature in [0.0, 2.0].
     * @param  string|null  $apiKey  Tenant or platform key (never logged, §13).
     * @param  string  $model  The approved model id (§12 — no silent swap).
     * @return array{text: string, tokensIn: int, tokensOut: int}
     *
     * @throws GeminiException On HTTP error, transport failure, or no candidate.
     */
    public function generate(
        string $systemInstruction,
        array $turns,
        float $temperature,
        ?string $apiKey,
        string $model,
    ): array {
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $timeout = (int) config('services.gemini.timeout', 20);

        $url = "{$baseUrl}/models/{$model}:generateContent";

        $contents = [];
        foreach ($turns as $turn) {
            $contents[] = [
                'role' => $turn['role'],
                'parts' => [['text' => $turn['text']]],
            ];
        }

        $body = [
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
            ],
        ];

        try {
            // The key travels in the `x-goog-api-key` header — NOT the URL — so
            // it never lands in a request URL that Guzzle would echo into a
            // transport-failure exception message or log (§13).
            $response = Http::timeout($timeout)
                ->withHeaders(['x-goog-api-key' => (string) $apiKey])
                ->asJson()
                ->acceptJson()
                ->post($url, $body);
        } catch (ConnectionException) {
            // Transport failure (timeout, DNS, refused). Surface explicitly with
            // a clean message and NO `previous`: the underlying exception could
            // carry transport details we never want serialized into a log (§13).
            throw new GeminiException(
                "Gemini transport failure for model [{$model}].",
            );
        }

        if ($response->failed()) {
            // Explicit failure, no swallowing (§3). Status + model only — never
            // the raw body (which can echo request context) (§13).
            throw new GeminiException(sprintf(
                'Gemini HTTP error: status %d for model [%s].',
                $response->status(),
                $model,
            ));
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        $text = $this->extractText($json);

        if ($text === null) {
            throw new GeminiException(
                "Gemini returned no usable candidate for model [{$model}].",
            );
        }

        $promptTokens = (int) (data_get($json, 'usageMetadata.promptTokenCount') ?? 0);
        $candidateTokens = (int) (data_get($json, 'usageMetadata.candidatesTokenCount') ?? 0);

        return [
            'text' => $text,
            'tokensIn' => max(0, $promptTokens),
            'tokensOut' => max(0, $candidateTokens),
        ];
    }

    /**
     * Concatenate the text parts of the first candidate, if present.
     *
     * @param  array<string, mixed>  $json
     */
    private function extractText(array $json): ?string
    {
        $parts = data_get($json, 'candidates.0.content.parts');

        if (! is_array($parts)) {
            return null;
        }

        $text = '';
        foreach ($parts as $part) {
            $chunk = data_get($part, 'text');

            if (is_string($chunk)) {
                $text .= $chunk;
            }
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
