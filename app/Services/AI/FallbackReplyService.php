<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;

/**
 * Temporary, zero-cost reply provider used until the Gemini agent rebinds
 * {@see BotReplyService}. Returns a decent, deterministic reply without any
 * external call, so the webhook critical path always has something to send
 * (§3 — no silent failure, no crash). Reports tokens=0 / cost=0.
 */
final class FallbackReplyService implements BotReplyService
{
    public const DEFAULT_GREETING = 'شكراً لتواصلك معنا. سيتم الرد على رسالتك في أقرب وقت.';

    public function generateReply(
        WhatsappAccount $account,
        Conversation $conversation,
        string $incomingText,
    ): ReplyResult {
        // Never expose the account's internal system prompt to the end user
        // (§12 — the bot must not reveal its instructions). On any AI-failure
        // path a neutral, fixed greeting is sent instead.
        return new ReplyResult(
            reply: self::DEFAULT_GREETING,
            tokensIn: 0,
            tokensOut: 0,
            costMicros: 0,
        );
    }
}
