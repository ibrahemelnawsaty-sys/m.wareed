<?php

declare(strict_types=1);

namespace App\Services\Bulk;

use RuntimeException;

/**
 * Thrown when a campaign is created with more recipients than the daily cap's
 * remaining headroom (Phase 6d, §11). Carries `remaining` so the controller can
 * tell the owner exactly how many slots are left, rather than failing opaquely.
 */
class BulkCapExceededException extends RuntimeException
{
    public function __construct(public readonly int $remaining)
    {
        parent::__construct("Recipient count exceeds the remaining daily cap ({$remaining}).");
    }
}
