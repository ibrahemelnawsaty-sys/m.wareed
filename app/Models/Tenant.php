<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $status
 * @property Carbon|null $subscription_ends_at
 * @property int $max_users
 * @property string $distribution_mode
 * @property int $agent_conversation_quota
 * @property int $daily_bulk_cap
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * `plan` and `status` govern billing/access and must NEVER be filled from
     * raw user input (self-upgrade risk, §13). Set them only via trusted
     * server-side logic / policies — never `Tenant::create($request->all())`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'plan',
        'status',
    ];

    /**
     * `subscription_ends_at`, `max_users`, `distribution_mode`,
     * `agent_conversation_quota`, and `daily_bulk_cap` are intentionally NOT in
     * $fillable: they are written only by trusted admin/owner logic (see the
     * management methods below), never from raw user input — a self-extended
     * subscription, a self-raised seat limit, a silently-flipped routing mode, or
     * a self-raised bulk cap is a §13 violation.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_ends_at' => 'datetime',
            'max_users' => 'integer',
            'distribution_mode' => 'string',
            'agent_conversation_quota' => 'integer',
            'daily_bulk_cap' => 'integer',
        ];
    }

    /**
     * The hard ceiling on the daily bulk-send cap. Meta's default messaging
     * limit for a fresh/unverified number is 250 unique recipients/24h; we never
     * let a tenant configure past it, so the customer's number is protected from
     * the bans that follow exceeding the limit (§11, Meta number-safety).
     */
    public const MAX_BULK_CAP = 250;

    /**
     * Whether this tenant's bot is allowed to operate right now: the account
     * must be approved AND its subscription not expired. This is the single
     * source of truth the webhook consults before any AI/send work (§9).
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->subscription_ends_at === null || $this->subscription_ends_at->isFuture());
    }

    /*
    |--------------------------------------------------------------------------
    | Admin management actions (§13)
    |--------------------------------------------------------------------------
    | Called ONLY from super-admin controllers, never from tenant-facing input.
    | They use save() directly on trusted server-side values — this is not mass
    | assignment, so `status`/`subscription_ends_at` stay out of $fillable.
    */

    /**
     * Approve a pending tenant, moving it into service.
     */
    public function approve(): void
    {
        $this->status = 'active';
        $this->save();
    }

    /**
     * Alias of approve(): bring a tenant into the active state.
     */
    public function activate(): void
    {
        $this->approve();
    }

    /**
     * Suspend a tenant; its bot goes silent immediately (isActive() is false).
     */
    public function suspend(): void
    {
        $this->status = 'suspended';
        $this->save();
    }

    /**
     * Lift a suspension and return the tenant to active service.
     */
    public function unsuspend(): void
    {
        $this->activate();
    }

    /**
     * Set the subscription to expire `$months` from now (trusted admin input).
     */
    public function setSubscriptionMonths(int $months): void
    {
        $this->subscription_ends_at = now()->addMonths($months);
        $this->save();
    }

    /**
     * Set the tenant's seat limit (owner + agents). ADMIN-ONLY: called only from
     * the super-admin console via a save() on a server-validated value — never
     * mass assignment, so `max_users` stays out of $fillable (§13). An owner can
     * never raise their own limit.
     */
    public function setMaxUsers(int $n): void
    {
        $this->max_users = $n;
        $this->save();
    }

    /**
     * Set the tenant's daily bulk-send cap. OWNER/ADMIN-trusted: called via a
     * save() on a value clamped to 1..MAX_BULK_CAP, never mass assignment, so
     * `daily_bulk_cap` stays out of $fillable and a tenant can never raise their
     * own cap past Meta's conservative 250 ceiling (§11, §13). The clamp is the
     * single enforcement point for "more than 250 locks it down".
     */
    public function setBulkCap(int $n): void
    {
        $this->daily_bulk_cap = max(1, min($n, self::MAX_BULK_CAP));
        $this->save();
    }

    /**
     * The effective daily bulk cap: the configured value, but never above the
     * hard 250 ceiling even if a larger value somehow reached the column. This
     * is the number SendQuota enforces atomically (§11).
     */
    public function effectiveBulkCap(): int
    {
        return min((int) $this->daily_bulk_cap, self::MAX_BULK_CAP);
    }

    /**
     * Whether this tenant routes handed-off conversations in "balanced" mode:
     * each new handoff is auto-assigned to the least-loaded agent still under
     * their target, instead of sitting unassigned for any agent to claim.
     */
    public function isBalancedMode(): bool
    {
        return $this->distribution_mode === 'balanced';
    }

    /**
     * Set the conversation distribution mode and the default per-agent target.
     * OWNER-ONLY: called from the team panel via save() on FormRequest-validated
     * values — never mass assignment, so `distribution_mode` /
     * `agent_conversation_quota` stay out of $fillable and cannot be smuggled
     * through request input (§13). Guards the inputs defensively even though the
     * FormRequest already validates them.
     */
    public function setDistribution(string $mode, int $quota): void
    {
        if (! in_array($mode, ['claim', 'balanced'], true)) {
            throw new InvalidArgumentException("Unknown distribution mode: {$mode}");
        }

        if ($quota < 1) {
            throw new InvalidArgumentException('Agent conversation quota must be at least 1.');
        }

        $this->distribution_mode = $mode;
        $this->agent_conversation_quota = $quota;
        $this->save();
    }

    /**
     * How many seats are currently occupied on this tenant. Counts EVERY user on
     * the tenant (owner + agents) through the relation so the global scope is not
     * in play here — this is the tenant's own user count, used to gate growth.
     */
    public function seatsUsed(): int
    {
        return $this->users()->count();
    }

    /**
     * Whether the tenant has a free seat. The owner's add-member path checks this
     * before creating a user; the seat ceiling can never be exceeded (§13).
     */
    public function canAddUser(): bool
    {
        return $this->seatsUsed() < $this->max_users;
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<WhatsappAccount, $this>
     */
    public function whatsappAccounts(): HasMany
    {
        return $this->hasMany(WhatsappAccount::class);
    }
}
