<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant to the {@see TenantContext} for the
 * duration of the request (ADR-02, §1). This is what makes the {@see
 * \App\Models\Scopes\TenantScope} global scope actually filter — without it the
 * scope is a no-op and every tenant-owned query would read across tenants.
 *
 * MUST run after `auth` so `auth()->user()` is populated.
 */
class BindTenant
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            // `(int) null === 0`; treat 0 / missing as "no tenant" and fail
            // closed. Binding tenant 0 would pool every tenant-less user into
            // one shared bucket — a direct cross-tenant leak (§1, ADR-02).
            $tenantId = (int) auth()->user()->tenant_id;

            if ($tenantId < 1) {
                abort(403, 'لا يوجد حساب مستأجر مرتبط بهذا المستخدم.');
            }

            $this->context->set($tenantId);
        }

        return $next($request);
    }
}
