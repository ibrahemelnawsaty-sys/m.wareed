<?php

declare(strict_types=1);

namespace App\Services\Bulk;

use RuntimeException;

/**
 * Thrown when the number of variables supplied for a template campaign does not
 * match the template's `variable_count` (Phase 7c, §11). Meta rejects a template
 * send whose parameters do not match the registered body, so we reject up front —
 * loudly — rather than queue jobs that would all fail at Meta. Carries the expected
 * and given counts so the owner sees exactly what to fix (§3).
 */
class TemplateVariableMismatchException extends RuntimeException
{
    public function __construct(
        public readonly int $expected,
        public readonly int $given,
    ) {
        parent::__construct("Template expects {$expected} variable(s), {$given} given.");
    }
}
