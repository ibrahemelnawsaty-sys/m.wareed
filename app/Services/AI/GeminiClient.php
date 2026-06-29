<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin transport over the Gemini `generateContent` REST endpoint (ADR-04, §12).
 *
 * Responsibilities are deliberately narrow: build the request body, perform a
 * single synchronous HTTP call (no internal retry loop that would drain tokens,
 * §12), and return the reply text plus token usage. It does not decide on
 * fallbacks or pricing — that belongs to {@see GeminiReplyService}.
 *
 * The API key is passed as a query parameter and is NEVER logged (§13).
 */
class GeminiClient
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
            // Key travels as a query param so it never lands in a header/log (§13).
            $response = Http::timeout($timeout)
                ->withQueryParameters(['key' => (string) $apiKey])
                ->asJson()
                ->acceptJson()
                ->post($url, $body);
        } catch (ConnectionException $e) {
            // Transport failure (timeout, DNS, refused). Surface explicitly —
            // the message carries no key (URL has no query string here, §13).
            throw new GeminiException(
                "Gemini transport failure for model [{$model}].",
                previous: $e,
            );
        }

        if ($response->failed()) {
            // Explicit failure, no swallowing (§3). Do NOT include the URL
            // (it carries the key in the query string) nor the raw body.
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
            try {
                $chunk = data_get($part, 'text');
            } catch (Throwable) {
                continue;
            }

            if (is_string($chunk)) {
                $text .= $chunk;
            }
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
