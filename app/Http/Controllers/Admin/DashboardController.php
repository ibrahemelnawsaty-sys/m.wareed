<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\UsageCounter;
use Illuminate\View\View;

/**
 * Super-admin overview across EVERY tenant (§1, §13).
 *
 * The admin has no tenant_id and the route group is deliberately NOT behind the
 * `tenant` middleware, so TenantContext is never bound here. Even so, every
 * cross-tenant read goes through withoutGlobalScopes() EXPLICITLY — the single
 * audited exception to tenant isolation. We never rely on "the scope happens to
 * be inert"; we state the cross-tenant intent in code so it is reviewable.
 *
 * Money stays integer micro-USD end to end; only <x-cost> divides for display
 * (§3). Counts are scalar aggregates (no model hydration, no N+1).
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        // Cross-tenant counts by status. withoutGlobalScopes() is the explicit,
        // audited bypass (§1); without it the (inert) scope would still leave
        // these unfiltered, but stating the bypass keeps the intent honest.
        $totalCustomers = Tenant::query()->withoutGlobalScopes()->count();
        $pendingCount = Tenant::query()->withoutGlobalScopes()->where('status', 'pending')->count();
        $activeCount = Tenant::query()->withoutGlobalScopes()->where('status', 'active')->count();
        $suspendedCount = Tenant::query()->withoutGlobalScopes()->where('status', 'suspended')->count();

        // Platform-wide usage totals (all tenants). cost stays micro-USD int.
        $totalMessages = (int) UsageCounter::query()->withoutGlobalScopes()->sum('messages');
        $totalCostMicros = (int) UsageCounter::query()->withoutGlobalScopes()->sum('cost_micros');

        // Latest signups with a one-click approve. Eager-load the owner so the
        // list does not fire one query per row (N+1, §14). withoutGlobalScopes()
        // on BOTH the parent and the eager-loaded relation: the User relation
        // also carries TenantScope, so it must be bypassed too.
        $recentTenants = Tenant::query()
            ->withoutGlobalScopes()
            ->with(['users' => fn ($q) => $q->withoutGlobalScopes()])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'totalCustomers' => $totalCustomers,
            'pendingCount' => $pendingCount,
            'activeCount' => $activeCount,
            'suspendedCount' => $suspendedCount,
            'totalMessages' => $totalMessages,
            'totalCostMicros' => $totalCostMicros,
            'recentTenants' => $recentTenants,
        ]);
    }
}
