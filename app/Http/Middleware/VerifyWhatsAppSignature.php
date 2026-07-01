<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\WhatsappAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the Meta `X-Hub-Signature-256` HMAC over the raw request body BEFORE
 * any processing (§11). A missing or mismatching signature is rejected with 403
 * — no tenant resolution, no AI call, no storage happens past here.
 *
 * Multi-app (§1, ADR-01): each tenant runs its OWN Meta app, so the signing
 * secret differs per number. We read the `phone_number_id` out of the (still
 * UNTRUSTED) body ONLY to SELECT which secret to verify against — the tenant's
 * own `app_secret` when set, otherwise the platform-wide secret (the platform's
 * own number / not-yet-onboarded tenants). Selecting a secret proves nothing:
 * an attacker who controls `phone_number_id` still cannot produce a valid HMAC
 * without knowing that secret, so a forged body is rejected either way. The
 * secret is never logged (§13); an empty chosen secret fails closed.
 */
class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->getContent();

        $secret = $this->secretFor($body);

        // Fail closed on a missing secret: an empty key makes the HMAC forgeable
        // by anyone who knows the algorithm (§11, §13). A misconfigured/absent
        // secret must reject, never accept.
        if ($secret === '') {
            abort(403);
        }

        $expected = 'sha256='.hash_hmac('sha256', $body, $secret);
        $provided = (string) $request->header('X-Hub-Signature-256', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * Choose the signing secret to verify against: the tenant's own Meta app
     * secret (looked up by the payload's phone_number_id) when set, otherwise
     * the platform secret. The phone_number_id is read from the UNVERIFIED body
     * only to pick the key — it is never trusted for anything else here, and
     * picking the wrong/attacker-supplied key still cannot yield a valid HMAC.
     */
    private function secretFor(string $body): string
    {
        $platformSecret = (string) config('services.whatsapp.app_secret');

        $phoneNumberId = $this->phoneNumberId($body);

        if ($phoneNumberId === null) {
            return $platformSecret;
        }

        // No tenant is bound yet (this runs before resolution), so query across
        // all tenants. Accessing `app_secret` decrypts it via the model cast.
        $account = WhatsappAccount::query()
            ->withoutGlobalScopes()
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        $tenantSecret = $account?->app_secret;

        return is_string($tenantSecret) && $tenantSecret !== ''
            ? $tenantSecret
            : $platformSecret;
    }

    /**
     * Extract `phone_number_id` from the raw JSON body without trusting it.
     * Malformed JSON or an unexpected shape yields null (never an exception),
     * so a junk body simply falls back to the platform secret and 403s.
     */
    private function phoneNumberId(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            return null;
        }

        $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');

        return is_string($phoneNumberId) && $phoneNumberId !== '' ? $phoneNumberId : null;
    }
}
