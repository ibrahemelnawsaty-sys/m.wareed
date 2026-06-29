<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Immutable result of a bot reply generation (§12). Carries the reply text
 * plus token/cost accounting in integer units (no float for money, §3).
 * `costMicros` is the cost in micro-units of the billing currency.
 */
final readonly class ReplyResult
{
    public function __construct(
        public string $reply,
        public int $tokensIn = 0,
        public int $tokensOut = 0,
        public int $costMicros = 0,
    ) {}
}
