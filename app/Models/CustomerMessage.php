<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record of a platform-to-customer message sent by a super-admin (§13).
 *
 * DELIBERATELY does NOT use BelongsToTenant / TenantScope: this is an
 * admin-owned, cross-tenant audit log with no tenant context behind it. It is
 * created and read ONLY from admin controllers, which already operate across
 * tenants (the tenant/sentBy relations below are read with withoutGlobalScopes
 * by the caller). `tenant_id` is set explicitly by the admin controller, never
 * inferred from a (non-existent) TenantContext.
 *
 * @property int $tenant_id
 * @property int|null $sent_by_user_id
 * @property string $channel
 * @property string $subject
 * @property string $body
 */
class CustomerMessage extends Model
{
    /**
     * Only the content/attribution fields are fillable. `channel` defaults to
     * 'email' at the DB level but is set explicitly by the controller.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'sent_by_user_id',
        'channel',
        'subject',
        'body',
    ];

    /**
     * The customer (tenant) this message was sent to. The admin caller reads it
     * with withoutGlobalScopes() since there is no bound tenant context.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The super-admin who sent the message (nullable: the user may be deleted).
     *
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
