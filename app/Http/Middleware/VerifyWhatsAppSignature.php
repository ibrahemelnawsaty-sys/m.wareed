<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the Meta `X-Hub-Signature-256` HMAC over the raw request body
 * BEFORE any processing (§11). A missing or mismatching signature is rejected
 * with 403 — no tenant resolution, no AI call, no storage happens past here.
 */
class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.whatsapp.app_secret');

        // Fail closed on a missing secret: an empty key makes the HMAC
        // forgeable by anyone who knows the algorithm (§11, §13). A
        // misconfigured deployment must reject, not accept.
        if ($secret === '') {
            abort(403);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        $provided = (string) $request->header('X-Hub-Signature-256', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}
