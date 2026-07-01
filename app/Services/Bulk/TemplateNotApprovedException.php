<?php

declare(strict_types=1);

namespace App\Services\Bulk;

use RuntimeException;

/**
 * Thrown when a campaign is built with a template that is NOT approved (Phase 7c,
 * §11). Sending a non-approved template is a Meta violation that risks the
 * customer's number, so the campaign is rejected loudly before any job is queued.
 */
class TemplateNotApprovedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The selected template is not approved and cannot be sent.');
    }
}
