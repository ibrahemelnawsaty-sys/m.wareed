<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned. Adds the {@see TenantScope} global scope so
 * every read is filtered by the active tenant, and auto-fills `tenant_id` on
 * create from the {@see TenantContext} when not already set (ADR-02, §3).
 *
 * @property int $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $context = app(TenantContext::class);

                if ($context->has()) {
                    $model->setAttribute('tenant_id', $context->id());
                }
            }
        });
    }

    /**
     * The tenant that owns this record.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
