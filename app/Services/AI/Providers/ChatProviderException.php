<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use RuntimeException;

/**
 * Thrown when a chat provider call fails explicitly (HTTP error, transport
 * failure, or a malformed response). Carries NO secrets — only a safe model id
 * and status (§12, §13). Callers on the webhook critical path catch this,
 * `report()` it, and fall back gracefully rather than crashing.
 */
final class ChatProviderException extends RuntimeException {}
