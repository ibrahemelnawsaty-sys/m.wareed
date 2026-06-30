<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $status
 * @property Carbon|null $subscription_ends_at
 * @property int $max_users
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
     * `subscription_ends_at` and `max_users` are intentionally NOT in $fillable:
     * they are written only by trusted admin logic (see the management methods
     * below), never from raw user input — a self-extended subscription or a
     * self-raised seat limit is a §13 violation.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_ends_at' => 'datetime',
            'max_users' => 'integer',
        ];
    }

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
