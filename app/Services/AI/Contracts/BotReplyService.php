<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Models\Conversation;
use App\Models\WhatsappAccount;
use App\Services\AI\FallbackReplyService;
use App\Services\AI\ReplyResult;

/**
 * Seam for generating a bot reply to an inbound WhatsApp message (§12).
 *
 * The Gemini agent will provide the real implementation later and rebind this
 * contract; until then {@see FallbackReplyService} answers.
 * Implementations MUST treat $incomingText (and any injected knowledge) as
 * untrusted input and harden the prompt against injection.
 */
interface BotReplyService
{
    /**
     * Generate a reply for the given inbound text within the conversation's
     * tenant/account context. Returns an accounting-bearing DTO; it must not
     * leak secrets nor throw for ordinary "model unavailable" cases — callers
     * on the webhook critical path rely on a graceful result.
     */
    public function generateReply(
        WhatsappAccount $account,
        Conversation $conversation,
        string $incomingText,
    ): ReplyResult;
}
