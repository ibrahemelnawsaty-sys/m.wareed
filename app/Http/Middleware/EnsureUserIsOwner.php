<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "owner only" gate for tenant team management (§13).
 *
 * Within a tenant only its OWNER manages the team — adding or removing agents.
 * An agent (role='agent') must never reach these routes, so this runs after
 * `auth` + `tenant` and aborts 403 for anyone who is not the tenant owner. It
 * is the team's authorization boundary; isolation between tenants is still
 * enforced separately by TenantScope.
 */
class EnsureUserIsOwner
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->user()?->isOwner()) {
            abort(403, 'إدارة الفريق متاحة لمالك الحساب فقط.');
        }

        return $next($request);
    }
}
