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
     * `subscription_ends_at` is intentionally NOT in $fillable: it is written
     * only by trusted admin logic (see the management methods below), never
     * from raw user input — a self-extended subscription is a §13 violation.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_ends_at' => 'datetime',
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
