<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The super-admin gate. Every /admin/* route runs behind this (after `auth`).
 *
 * Tenant isolation is the platform's load-bearing security guarantee (§1, §13).
 * The admin area is the single, deliberate, audited exception: it crosses all
 * tenants — so access MUST be locked to genuine super-admins here, and the
 * cross-tenant reads (withoutGlobalScopes) live ONLY inside admin controllers,
 * never in tenant-facing code.
 *
 * Admins have no tenant_id, so this group must NOT also use the `tenant`
 * middleware (which fails closed on a tenant-less user).
 */
class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->user()?->is_admin) {
            abort(403, 'هذه الصفحة مخصّصة لمدير المنصة فقط.');
        }

        return $next($request);
    }
}
