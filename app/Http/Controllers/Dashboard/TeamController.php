<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreTeamMemberRequest;
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

        return view('dashboard.team.index', [
            'members' => $members,
            'seatsUsed' => $tenant->seatsUsed(),
            'maxUsers' => $tenant->max_users,
            'canAddUser' => $tenant->canAddUser(),
        ]);
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
