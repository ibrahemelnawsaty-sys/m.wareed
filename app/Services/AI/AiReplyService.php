<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Multi-provider {@see BotReplyService} (§12). Resolves the account's provider,
 * key, model and prices via {@see ProviderResolver}, builds a hardened prompt,
 * calls the provider, computes an integer token cost, records per-tenant usage,
 * and returns a {@see ReplyResult}.
 *
 * The default tenant (ai_provider = 'gemini', no override) behaves exactly as the
 * previous Gemini-only path: same key resolution, same model, same prices, same
 * cost arithmetic — so existing behaviour is preserved while OpenAI/DeepSeek
 * become selectable per account by the admin (§12 — documented choice, no silent
 * swap).
 *
 * On any failure or timeout the error is reported and a graceful
 * {@see FallbackReplyService} reply is returned — the webhook never crashes and
 * there is no token-draining retry loop (§12, §3).
 *
 * Secrets: the resolved key (tenant → platform setting → .env) is never logged
 * (§13).
 */
class AiReplyService implements BotReplyService
{
    public function __construct(
        private readonly ProviderResolver $resolver,
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
        // reply with the neutral fallback instead of calling — and billing — a
        // provider. Disabled by default (cap <= 0), so no extra query on the hot
        // path unless an operator opts in.
        if (! $this->withinDailyCap($account->tenant_id)) {
            return $this->fallback->generateReply($account, $conversation, $incomingText);
        }

        try {
            // Provider, key, model and prices all come from the account's
            // admin-set fields (§12 — no silent provider/model swap).
            $resolved = $this->resolver->resolve($account);

            $systemInstruction = $this->promptBuilder->buildSystemInstruction($account);
            $turns = $this->promptBuilder->buildTurns($conversation, $incomingText);

            // Account temperature is an int 0..100; providers want a float clamped
            // to a sane 0.0..2.0 range (§12 — no out-of-range value).
            $temperature = max(0.0, min(2.0, $account->temperature / 100));

            $result = $resolved['provider']->generate(
                systemInstruction: $systemInstruction,
                turns: $turns,
                temperature: $temperature,
                apiKey: $resolved['apiKey'],
                model: $resolved['model'],
            );

            $tokensIn = $result['tokensIn'];
            $tokensOut = $result['tokensOut'];
            $costMicros = $this->costMicros(
                $tokensIn,
                $tokensOut,
                $resolved['inMicrosPerMtok'],
                $resolved['outMicrosPerMtok'],
            );

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
     * Integer micro-USD cost from token counts at the resolved per-Mtok prices
     * (§3 — no float for money). Prices are per 1,000,000 tokens in micro-USD.
     */
    private function costMicros(int $tokensIn, int $tokensOut, int $inPerMtok, int $outPerMtok): int
    {
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
}
