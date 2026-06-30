<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendCustomerMessageRequest;
use App\Http\Requests\Admin\UpdateBotRequest;
use App\Http\Requests\Admin\UpdateSeatsRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Mail\CustomerNotification;
use App\Models\CustomerMessage;
use App\Models\Tenant;
use App\Models\UsageCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

        // Last 10 admin-to-customer messages for THIS tenant (newest first).
        // CustomerMessage has no global scope (admin-owned audit log), so we
        // filter by tenant_id explicitly; sentBy is eager-loaded for the list.
        $sentMessages = CustomerMessage::query()
            ->where('tenant_id', $customer->id)
            ->with(['sentBy' => fn ($q) => $q->withoutGlobalScopes()])
            ->latest()
            ->limit(10)
            ->get();

        // Current seat usage (cross-tenant read; the admin has no bound context).
        $seatsUsed = $customer->users()->withoutGlobalScopes()->count();

        return view('admin.customers.show', [
            'customer' => $customer,
            'owner' => $owner,
            'account' => $account,
            'messagesTotal' => (int) ($totals->messages ?? 0),
            'tokensTotal' => (int) ($totals->tokens_in ?? 0) + (int) ($totals->tokens_out ?? 0),
            'costMicros' => (int) ($totals->cost_micros ?? 0),
            'providers' => UpdateBotRequest::PROVIDERS,
            'sentMessages' => $sentMessages,
            'seatsUsed' => $seatsUsed,
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

    public function updateSeats(UpdateSeatsRequest $request, int $tenant): RedirectResponse
    {
        // Trusted path: setMaxUsers writes max_users via save() on a validated
        // value — never mass assignment, so the tenant owner cannot raise their
        // own seat limit (§13). ADMIN-ONLY by the route's `admin` gate.
        $this->resolveTenant($tenant)->setMaxUsers((int) $request->validated('max_users'));

        return $this->backToCustomer($tenant, 'customer-seats-updated');
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
     * Send an email to the customer's owner and record it in the audit log (§13).
     *
     * Channel is 'email' only for now (WhatsApp-to-customer needs a separate
     * platform number — out of scope). The send is wrapped: a mail failure is
     * report()ed and surfaced as a gentle error (no silent swallow, no 500, §3),
     * and the audit row is written only after the mail is dispatched.
     */
    public function sendMessage(SendCustomerMessageRequest $request, int $tenant): RedirectResponse
    {
        $customer = $this->resolveTenant($tenant);

        // Owner's email (cross-tenant read; the admin has no bound context, §1).
        // Prefer the 'owner' role, fall back to the oldest user on the tenant.
        $owner = $customer->users()->withoutGlobalScopes()->where('role', 'owner')->first()
            ?? $customer->users()->withoutGlobalScopes()->oldest()->first();

        $email = $owner?->email;

        if ($email === null || $email === '') {
            // No address to reach — surface it loudly, never a 500 (§3).
            return redirect()
                ->route('admin.customers.show', $tenant)
                ->withInput()
                ->withErrors(['subject' => 'لا يوجد بريد إلكتروني لمالك هذا العميل لإرسال الرسالة إليه.']);
        }

        $subject = (string) $request->validated('subject');
        $body = (string) $request->validated('body');

        try {
            Mail::to($email)->send(new CustomerNotification($subject, $body));
        } catch (\Throwable $e) {
            // Surface to error tracking, then a gentle message — no swallow (§3).
            report($e);

            return redirect()
                ->route('admin.customers.show', $tenant)
                ->withInput()
                ->withErrors(['subject' => 'تعذّر إرسال البريد حالياً. تم تسجيل الخطأ، يرجى المحاولة لاحقاً.']);
        }

        // Audit the send only after the mail dispatched successfully.
        CustomerMessage::create([
            'tenant_id' => $customer->id,
            'sent_by_user_id' => $request->user()?->id,
            'channel' => 'email',
            'subject' => $subject,
            'body' => $body,
        ]);

        return $this->backToCustomer($tenant, 'customer-message-sent');
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
