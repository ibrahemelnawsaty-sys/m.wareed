<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Routes a handed-off conversation to an agent in "balanced" distribution mode
 * (Phase 6c). Instead of leaving the thread unassigned for any agent to claim
 * ("fastest wins" / claim mode), it picks the LEAST-LOADED agent who is still
 * under their open-conversation target and assigns it to them automatically.
 * Also serves manual claims (claimFor) so both paths enforce the same atomic
 * per-agent capacity.
 *
 * Tenant isolation (§1): every read here runs through the TenantScope (the
 * tenant must already be bound — the webhook binds it via TenantContext::run
 * before handoff), so agents and conversation loads are this tenant's only.
 *
 * Concurrency (§13 no race on assignment): the per-agent target is a guard, and
 * a guard that is checked then acted on in two steps can be raced. Two
 * simultaneous handoffs for the same tenant would both read the same load
 * snapshot, both pick the same least-loaded agent, and — because each atomic
 * UPDATE only protects its own CONVERSATION row, not the AGENT — both succeed,
 * pushing that agent past their target. So every assignment here runs inside a
 * transaction that first locks the relevant agent row(s) FOR UPDATE (in id
 * order, deadlock-safe); the load is then recounted as the first consistent
 * read AFTER the lock, reflecting any just-committed concurrent assignment. The
 * second handoff blocks until the first commits, recounts, and sees the agent
 * is now full — so the ceiling holds under concurrency.
 *
 * Performance (§14): loads are gathered in ONE aggregate query (no query per
 * agent); the lock is held only for the brief assignment transaction.
 */
class ConversationRouter
{
    /**
     * Assign $conversation to the least-loaded under-target agent (balanced).
     *
     * Returns the agent it was assigned to, or NULL when no agent is available
     * (none exist, or every agent is at their target) — in which case the
     * conversation stays UNASSIGNED in the human queue (no 500, no token cost).
     */
    public function assignBestAgent(Conversation $conversation): ?User
    {
        // Already assigned (e.g. a racing webhook won) — never reroute it.
        if ($conversation->assigned_to_user_id !== null) {
            return null;
        }

        return DB::transaction(function () use ($conversation): ?User {
            // Lock every candidate agent FOR UPDATE in a stable id order so
            // concurrent handoffs for this tenant serialize here (and never
            // deadlock against each other). tenant eager-loaded so each
            // conversationQuota() lookup is in-memory (no N+1, §14).
            /** @var Collection<int, User> $agents */
            $agents = User::query()
                ->where('role', 'agent')
                ->with('tenant')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($agents->isEmpty()) {
                return null;
            }

            // Authoritative load: first consistent read AFTER the locking read
            // above, so it reflects an assignment a just-committed concurrent
            // handoff made (§13). Agents with zero open conversations are absent
            // from the map (treated as load 0).
            $loads = $this->openLoadsByAgent();

            $best = null;
            $bestLoad = PHP_INT_MAX;

            foreach ($agents as $agent) {
                $load = $loads[$agent->id] ?? 0;

                // Skip anyone already at/over their target — they cannot take more.
                if ($load >= $agent->conversationQuota()) {
                    continue;
                }

                // Strictly-less keeps the first (smallest-id, since ordered)
                // agent on a tie, so ties break to the oldest deterministically.
                if ($load < $bestLoad) {
                    $best = $agent;
                    $bestLoad = $load;
                }
            }

            if ($best === null) {
                return null; // everyone is at capacity — stays queued, unassigned.
            }

            return $this->forceClaim($conversation, $best) ? $best : null;
        });
    }

    /**
     * Claim $conversation for a specific $user (manual claim / reply takeover),
     * enforcing their target atomically. Returns one of:
     *  - 'claimed' — now assigned to $user (or it already was),
     *  - 'full'    — $user is at their open-conversation target (owner exempt),
     *  - 'taken'   — another agent already holds it.
     *
     * The user row is locked FOR UPDATE, then their live load is recounted, so
     * two concurrent claims by the same agent cannot both slip past the target
     * (§13). The owner is exempt — they are the supervisor/overflow.
     */
    public function claimFor(Conversation $conversation, User $user): string
    {
        if ($conversation->assigned_to_user_id !== null) {
            return $conversation->isAssignedTo($user) ? 'claimed' : 'taken';
        }

        return DB::transaction(function () use ($conversation, $user): string {
            // Serialize this user's concurrent claims; the recount below is then
            // authoritative against any just-committed assignment to them.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $user->isOwner()) {
                $load = $this->openLoadsByAgent()[$user->id] ?? 0;

                if ($load >= $user->conversationQuota()) {
                    return 'full';
                }
            }

            return $this->forceClaim($conversation, $user) ? 'claimed' : 'taken';
        });
    }

    /**
     * Open (human-mode, assigned) conversation count per agent for the bound
     * tenant, in ONE aggregate query (no N+1, §14). Runs through TenantScope.
     *
     * @return array<int, int> keyed by user id
     */
    private function openLoadsByAgent(): array
    {
        return Conversation::query()
            ->humanMode()
            ->whereNotNull('assigned_to_user_id')
            ->groupBy('assigned_to_user_id')
            ->select('assigned_to_user_id', DB::raw('COUNT(*) as open_count'))
            ->pluck('open_count', 'assigned_to_user_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * Atomic assignment: flips the conversation to $agent only while it is still
     * unassigned (§13 no double-assign). Returns whether this call won it, and
     * refreshes the in-memory model to the resulting DB state either way.
     */
    private function forceClaim(Conversation $conversation, User $agent): bool
    {
        $assigned = Conversation::query()
            ->whereKey($conversation->getKey())
            ->whereNull('assigned_to_user_id')
            ->update([
                'assigned_to_user_id' => $agent->id,
                'mode' => 'human',
                'handoff_at' => now(),
            ]);

        $conversation->refresh();

        return $assigned === 1;
    }
}
