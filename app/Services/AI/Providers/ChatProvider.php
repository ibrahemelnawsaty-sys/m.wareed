<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

/**
 * Transport seam for a chat-completion AI provider (§12). One synchronous call
 * that turns a hardened system instruction plus bounded conversation turns into
 * a reply, with token accounting.
 *
 * Implementations are deliberately narrow: build the request, perform a single
 * HTTP call (no internal retry loop that would drain tokens, §12), and return
 * the text plus usage. They do NOT decide fallbacks or pricing — that belongs to
 * the reply service. The API key MUST never appear in a log nor an exception
 * message (§13).
 */
interface ChatProvider
{
    /**
     * Call the provider and return the generated text with token accounting.
     *
     * @param  string  $systemInstruction  The hardened system prompt (§12).
     * @param  list<array{role: 'user'|'model', text: string}>  $turns  Ordered
     *                                                                  conversation turns (last N only, oldest first).
     * @param  float  $temperature  Sampling temperature.
     * @param  string|null  $apiKey  Tenant or platform key (never logged, §13).
     * @param  string  $model  The configured model id (§12 — no silent swap).
     * @return array{text: string, tokensIn: int, tokensOut: int}
     */
    public function generate(
        string $systemInstruction,
        array $turns,
        float $temperature,
        ?string $apiKey,
        string $model,
    ): array;
}
