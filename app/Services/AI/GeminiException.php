<?php

declare(strict_types=1);

namespace App\Services\AI;

use RuntimeException;

/**
 * Thrown when a Gemini call fails explicitly (HTTP error, transport failure,
 * or a malformed response). Carries no secrets — only safe diagnostic context
 * (§12, §13). Callers on the webhook critical path catch this, `report()` it,
 * and fall back to {@see FallbackReplyService} rather than crashing.
 */
final class GeminiException extends RuntimeException {}
