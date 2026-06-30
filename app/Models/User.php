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
}
