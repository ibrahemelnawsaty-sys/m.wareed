<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Real {@see BotReplyService} backed by Gemini 2.5 Flash-Lite (ADR-04, §12).
 *
 * Flow: build a hardened prompt → call Gemini → compute integer token cost →
 * record per-tenant usage → return a {@see ReplyResult}. On any failure or
 * timeout the error is reported and a graceful {@see FallbackReplyService}
 * reply is returned instead — the webhook never crashes and there is no
 * token-draining retry loop (§12, §3).
 *
 * Secrets: the tenant's encrypted `ai_api_key` is preferred over the platform
 * key, and neither is ever logged (§13).
 */
class GeminiReplyService implements BotReplyService
{
    public function __construct(
        private readonly GeminiClient $client,
        private readonly PromptBuilder $promptBuilder,
        private readonly UsageRecorder $usageRecorder,
        private readonly FallbackReplyService $fallback,
    ) {}

    public function generateReply(
        WhatsappAccount $account,
        Conversation $conversation,
        string $incomingText,
    ): ReplyResult {
        // Daily per-tenant message cap (§12). When the tenant has reached it we
        // reply with the neutral fallback instead of calling — and billing —
        // Gemini. Disabled by default (cap <= 0), so no extra query on the hot
        // path unless an operator opts in.
        if (! $this->withinDailyCap($account->tenant_id)) {
            return $this->fallback->generateReply($account, $conversation, $incomingText);
        }

        try {
            $systemInstruction = $this->promptBuilder->buildSystemInstruction($account);
            $turns = $this->promptBuilder->buildTurns($conversation, $incomingText);

            // Account temperature is an int 0..100; Gemini wants a float clamped
            // to its supported 0.0..2.0 range (§12 — no out-of-range value).
            $temperature = max(0.0, min(2.0, $account->temperature / 100));

            $result = $this->client->generate(
                systemInstruction: $systemInstruction,
                turns: $turns,
                temperature: $temperature,
                apiKey: $this->resolveApiKey($account),
                model: $this->resolveModel(),
            );

            $tokensIn = $result['tokensIn'];
            $tokensOut = $result['tokensOut'];
            $costMicros = $this->costMicros($tokensIn, $tokensOut);

            // Record per-tenant usage after a successful generation (§12).
            $this->usageRecorder->record(
                $account->tenant_id,
                $tokensIn,
                $tokensOut,
                $costMicros,
            );

            return new ReplyResult(
                reply: $result['text'],
                tokensIn: $tokensIn,
                tokensOut: $tokensOut,
                costMicros: $costMicros,
            );
        } catch (Throwable $e) {
            // Explicit failure handling (§3): report, then a graceful fallback
            // reply — no silent swallow, no retry loop that drains tokens (§12).
            report($e);

            // The customer still receives a reply, so count the message
            // (tokens/cost = 0) — the counter must not drift from what was
            // actually sent (§4).
            $this->usageRecorder->record($account->tenant_id, 0, 0, 0);

            return $this->fallback->generateReply($account, $conversation, $incomingText);
        }
    }

    /**
     * Integer micro-USD cost from token counts (§3 — no float for money).
     * Prices are per 1,000,000 tokens, expressed in micro-USD in config.
     */
    private function costMicros(int $tokensIn, int $tokensOut): int
    {
        $inPerMtok = (int) config('services.gemini.input_micros_per_mtok', 100000);
        $outPerMtok = (int) config('services.gemini.output_micros_per_mtok', 400000);

        return intdiv($tokensIn * $inPerMtok, 1_000_000)
            + intdiv($tokensOut * $outPerMtok, 1_000_000);
    }

    /**
     * Whether the tenant is under its daily message cap (§12). Cap <= 0 means
     * unlimited (the default), in which case no query runs.
     */
    private function withinDailyCap(int $tenantId): bool
    {
        $cap = (int) config('services.gemini.daily_message_cap', 0);

        if ($cap <= 0) {
            return true;
        }

        $used = (int) DB::table('usage_counters')
            ->where('tenant_id', $tenantId)
            ->where('date', now()->toDateString())
            ->value('messages');

        return $used < $cap;
    }

    /**
     * Tenant key first (encrypted), else the platform key. Never logged (§13).
     */
    private function resolveApiKey(WhatsappAccount $account): ?string
    {
        $tenantKey = $account->ai_api_key;

        if (is_string($tenantKey) && $tenantKey !== '') {
            return $tenantKey;
        }

        $platformKey = config('services.gemini.api_key');

        return is_string($platformKey) && $platformKey !== '' ? $platformKey : null;
    }

    /**
     * The approved model id (§12 — no silent model swap).
     */
    private function resolveModel(): string
    {
        $model = config('services.gemini.model');

        return is_string($model) && $model !== '' ? $model : 'gemini-2.5-flash-lite';
    }
}
