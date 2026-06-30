<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property bool $is_admin
 * @property int|null $conversation_quota
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use BelongsToTenant;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * SECURITY (§13): `is_admin` is deliberately ABSENT here. A super-admin is
     * created only via the `wareed:make-admin` CLI command which sets the flag
     * with forceFill(), never through User::create()/fill() with user input —
     * including it would be a one-line privilege-escalation hole.
     *
     * `conversation_quota` is likewise ABSENT: it is the agent's own open-
     * conversation ceiling, set ONLY by the tenant owner via
     * setConversationQuota() (trusted save). An agent able to mass-assign it
     * would simply raise their own limit (§13).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'conversation_quota' => 'integer',
        ];
    }

    /**
     * Whether this user is a platform super-admin (crosses all tenants).
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Whether this user is the OWNER of their tenant. Only the owner manages the
     * team (adds/removes agents); an agent is gated out by EnsureUserIsOwner (§13).
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * The agent's effective open-conversation target in balanced mode: their own
     * override if set, otherwise the tenant-wide default. The tenant relation is
     * loaded lazily; in the webhook/inbox the agents are read in a batch with
     * `tenant` eager-loaded (ConversationRouter) so this stays N+1-free (§14).
     */
    public function conversationQuota(): int
    {
        return $this->conversation_quota ?? (int) $this->tenant?->agent_conversation_quota;
    }

    /**
     * How many open (human-mode) conversations are currently assigned to this
     * agent. Runs through the Conversation TenantScope, so it counts only this
     * tenant's threads (§1). Used as the load metric for balanced routing and
     * the capacity guard.
     */
    public function openConversationsCount(): int
    {
        return Conversation::query()
            ->humanMode()
            ->assignedTo($this->id)
            ->count();
    }

    /**
     * Whether this agent has reached their open-conversation target and must not
     * be handed (or claim) another. The OWNER is EXEMPT (supervisor / overflow):
     * they may always take a conversation regardless of load, so they are never
     * "at capacity". Only a non-owner agent at or above their quota is blocked.
     */
    public function isAtConversationCapacity(): bool
    {
        return ! $this->isOwner()
            && $this->openConversationsCount() >= $this->conversationQuota();
    }

    /**
     * Set (or clear) this agent's per-agent conversation target. OWNER-ONLY:
     * called from the team panel via forceFill()->save() on a FormRequest-
     * validated value (NULL ⇒ inherit the tenant default) — never mass
     * assignment, so `conversation_quota` stays out of $fillable and an agent
     * cannot raise their own ceiling (§13).
     */
    public function setConversationQuota(?int $quota): void
    {
        $this->forceFill(['conversation_quota' => $quota])->save();
    }
}
