<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBotRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Models\Tenant;
use App\Models\UsageCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super-admin customer (tenant) management (§1, §13).
 *
 * EVERY query in this controller crosses tenants and therefore calls
 * withoutGlobalScopes() EXPLICITLY — the one audited exception to isolation.
 * The admin never binds TenantContext, so resolving a tenant by id MUST bypass
 * the (would-be) scope deliberately, and we do so by id only, never trusting the
 * route to scope it for us.
 *
 * State transitions (approve/suspend/...) go through the trusted Tenant methods
 * (save() on server-set values), never mass assignment of status /
 * subscription_ends_at (self-upgrade defence, §13).
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        // Cross-tenant list. Eager-load owner + account (both tenant-scoped
        // models) with the scope bypassed so the relation rows actually load.
        $customers = Tenant::query()
            ->withoutGlobalScopes()
            ->with([
                'users' => fn ($q) => $q->withoutGlobalScopes(),
                'whatsappAccounts' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                // Match tenant name OR any of its owners' emails. The owner
                // subquery also bypasses the scope (cross-tenant lookup).
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('users', fn ($u) => $u->withoutGlobalScopes()->where('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Per-tenant usage rollups keyed by tenant_id. Two grouped queries (not
        // one per row) attached in PHP — no N+1 (§14). Both are explicit
        // cross-tenant reads (withoutGlobalScopes, §1); money stays int (§3).
        $usageByTenant = UsageCounter::query()
            ->withoutGlobalScopes()
            ->selectRaw('tenant_id')
            ->selectRaw('COALESCE(SUM(messages),0) as messages')
            ->selectRaw('COALESCE(SUM(cost_micros),0) as cost_micros')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        return view('admin.customers.index', [
            'customers' => $customers,
            'usageByTenant' => $usageByTenant,
            'search' => $search,
        ]);
    }

    public function show(int $tenant): View
    {
        $customer = $this->resolveTenant($tenant);

        $owner = $customer->users()->withoutGlobalScopes()->oldest()->first();
        $account = $customer->whatsappAccounts()->withoutGlobalScopes()->first();

        // Usage rollup for this tenant only (still an explicit cross-tenant read:
        // the admin has no bound tenant). Integers throughout (§3).
        /** @var object{messages: int|null, tokens_in: int|null, tokens_out: int|null, cost_micros: int|null}|null $totals */
        $totals = UsageCounter::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->id)
            ->selectRaw('COALESCE(SUM(messages),0) as messages')
            ->selectRaw('COALESCE(SUM(tokens_in),0) as tokens_in')
            ->selectRaw('COALESCE(SUM(tokens_out),0) as tokens_out')
            ->selectRaw('COALESCE(SUM(cost_micros),0) as cost_micros')
            ->first();

        return view('admin.customers.show', [
            'customer' => $customer,
            'owner' => $owner,
            'account' => $account,
            'messagesTotal' => (int) ($totals->messages ?? 0),
            'tokensTotal' => (int) ($totals->tokens_in ?? 0) + (int) ($totals->tokens_out ?? 0),
            'costMicros' => (int) ($totals->cost_micros ?? 0),
            'providers' => UpdateBotRequest::PROVIDERS,
        ]);
    }

    public function approve(int $tenant): RedirectResponse
    {
        $this->resolveTenant($tenant)->approve();

        return $this->backToCustomer($tenant, 'customer-approved');
    }

    public function suspend(int $tenant): RedirectResponse
    {
        $this->resolveTenant($tenant)->suspend();

        return $this->backToCustomer($tenant, 'customer-suspended');
    }

    public function unsuspend(int $tenant): RedirectResponse
    {
        $this->resolveTenant($tenant)->unsuspend();

        return $this->backToCustomer($tenant, 'customer-unsuspended');
    }

    public function updateSubscription(UpdateSubscriptionRequest $request, int $tenant): RedirectResponse
    {
        // Trusted path: setSubscriptionMonths writes subscription_ends_at via
        // save() on a server-computed value — never mass assignment (§13).
        $this->resolveTenant($tenant)->setSubscriptionMonths((int) $request->validated('months'));

        return $this->backToCustomer($tenant, 'customer-subscription-updated');
    }

    public function updateBot(UpdateBotRequest $request, int $tenant): RedirectResponse
    {
        $customer = $this->resolveTenant($tenant);

        $account = $customer->whatsappAccounts()->withoutGlobalScopes()->first();

        if ($account === null) {
            // Surface this loudly instead of silently doing nothing (§3).
            return redirect()
                ->route('admin.customers.show', $tenant)
                ->withErrors(['ai_provider' => 'لا يوجد حساب واتساب لهذا العميل لضبط البوت عليه.']);
        }

        // Only the two non-secret provider/model fields. access_token and
        // ai_api_key are NEVER touched here and never echoed (§13).
        $account->forceFill([
            'ai_provider' => $request->validated('ai_provider'),
            'ai_model' => $request->validated('ai_model'),
        ])->save();

        return $this->backToCustomer($tenant, 'customer-bot-updated');
    }

    /**
     * Resolve a tenant by id across all tenants (explicit isolation bypass, §1).
     * 404s on a missing id; the admin operates globally so there is no scoping
     * to lean on — we look it up deliberately and only by primary key.
     */
    private function resolveTenant(int $tenant): Tenant
    {
        return Tenant::query()->withoutGlobalScopes()->findOrFail($tenant);
    }

    private function backToCustomer(int $tenant, string $status): RedirectResponse
    {
        return redirect()
            ->route('admin.customers.show', $tenant)
            ->with('status', $status);
    }
}
