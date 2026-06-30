<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreTeamMemberRequest;
use App\Http\Requests\Dashboard\UpdateAgentQuotaRequest;
use App\Http\Requests\Dashboard\UpdateDistributionRequest;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tenant team management — OWNER ONLY (behind auth+tenant+owner, §13).
 *
 * Isolation: every User read/write goes through the TenantScope (User uses
 * BelongsToTenant), so the list, the create, and the route-model-bound destroy
 * all stay inside the active tenant — an owner can never see or remove another
 * tenant's users. The seat ceiling (Tenant::canAddUser / max_users, admin-set)
 * is checked before any user is created and can never be exceeded.
 */
class TeamController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * List the active tenant's members (owner + agents). TenantScope filters the
     * query to the current tenant, so foreign users are never visible (§1).
     */
    public function index(): View
    {
        $tenant = $this->currentTenant();

        $members = User::query()->oldest()->get();

        // Per-agent open-conversation load in ONE aggregate (no query per row,
        // §14): human-mode, assigned conversations grouped by agent. Runs through
        // the TenantScope so it counts only this tenant's threads (§1).
        /** @var array<int, int> $loads keyed by user id */
        $loads = Conversation::query()
            ->humanMode()
            ->whereNotNull('assigned_to_user_id')
            ->selectRaw('assigned_to_user_id, COUNT(*) as open_count')
            ->groupBy('assigned_to_user_id')
            ->pluck('open_count', 'assigned_to_user_id')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return view('dashboard.team.index', [
            'members' => $members,
            'seatsUsed' => $tenant->seatsUsed(),
            'maxUsers' => $tenant->max_users,
            'canAddUser' => $tenant->canAddUser(),
            'distributionMode' => $tenant->distribution_mode,
            'defaultQuota' => $tenant->agent_conversation_quota,
            'loads' => $loads,
        ]);
    }

    /**
     * Set the tenant's conversation distribution mode + default per-agent target
     * (Phase 6c). OWNER-ONLY (route's `owner` gate). Applied via the trusted
     * Tenant::setDistribution (save() on FormRequest-validated values), never
     * mass assignment — `distribution_mode`/`agent_conversation_quota` stay out
     * of $fillable, so the mode can never be smuggled through input (§13).
     */
    public function updateDistribution(UpdateDistributionRequest $request): RedirectResponse
    {
        $this->currentTenant()->setDistribution(
            (string) $request->validated('distribution_mode'),
            (int) $request->validated('agent_conversation_quota'),
        );

        return redirect()
            ->route('team.index')
            ->with('status', 'team-distribution-updated');
    }

    /**
     * Set (or clear) one agent's per-agent conversation target. OWNER-ONLY.
     * $user is route-model bound through the TenantScope, so a foreign user 404s
     * (IDOR, §1). A blank value clears the override (inherit the tenant default).
     * The owner has no target (exempt), so setting one on the owner is rejected
     * gently rather than silently stored (§3). Applied via the trusted
     * User::setConversationQuota (forceFill+save), never mass assignment (§13).
     */
    public function updateAgentQuota(UpdateAgentQuotaRequest $request, User $user): RedirectResponse
    {
        if ($user->isOwner()) {
            return redirect()
                ->route('team.index')
                ->withErrors(['team' => 'المالك معفى من سقف المحادثات؛ لا يُضبط له تارجت.']);
        }

        $quota = $request->validated('conversation_quota');

        $user->setConversationQuota($quota === null ? null : (int) $quota);

        return redirect()
            ->route('team.index')
            ->with('status', 'team-quota-updated');
    }

    /**
     * Add an agent to the tenant. The seat ceiling is checked FIRST (gentle error
     * if full, no silent swallow, §3). The new user's role is forced to 'agent'
     * and tenant_id is auto-filled by BelongsToTenant from the bound context —
     * neither is taken from input, so no owner/admin can be minted and no foreign
     * tenant can be targeted (§13). email_verified_at is set so the agent can log
     * in immediately; is_admin stays false (not in $fillable).
     */
    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();

        if (! $tenant->canAddUser()) {
            // Hard ceiling — surface it loudly, never quietly drop the request (§3).
            return redirect()
                ->route('team.index')
                ->withInput()
                ->withErrors([
                    'email' => "بلغت حدّ المقاعد ({$tenant->max_users}). تواصل مع الدعم لرفع الحدّ.",
                ]);
        }

        $agent = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => 'agent',
            // tenant_id is auto-filled by BelongsToTenant from TenantContext (§1).
        ]);

        // email_verified_at is intentionally NOT in $fillable, so it is set here
        // via forceFill (trusted server value, never user input) so the agent can
        // log in immediately without an email verification round-trip.
        $agent->forceFill(['email_verified_at' => now()])->save();

        return redirect()
            ->route('team.index')
            ->with('status', 'team-member-added');
    }

    /**
     * Remove an agent. $user is route-model bound through the TenantScope, so a
     * foreign user 404s (IDOR, §13). The OWNER cannot be removed, and a user
     * cannot remove themselves — both surface as a gentle error, never a 500.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->isOwner()) {
            return redirect()
                ->route('team.index')
                ->withErrors(['team' => 'لا يمكن إزالة مالك الحساب.']);
        }

        if ($user->id === auth()->id()) {
            return redirect()
                ->route('team.index')
                ->withErrors(['team' => 'لا يمكنك إزالة نفسك.']);
        }

        $user->delete();

        return redirect()
            ->route('team.index')
            ->with('status', 'team-member-removed');
    }

    /**
     * The bound tenant for the current request. The `tenant` middleware fails
     * closed on a tenant-less user, so a context is always present here; resolved
     * by id (no scoping to lean on — Tenant has no global scope).
     */
    private function currentTenant(): Tenant
    {
        return Tenant::query()->findOrFail($this->context->id());
    }
}
