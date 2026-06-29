<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
