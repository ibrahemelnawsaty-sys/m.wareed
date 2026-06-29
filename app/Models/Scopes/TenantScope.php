<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Enforces tenant isolation at the query layer (ADR-02, §3). Applied as a
 * global scope on every tenant-owned model so no query can read across
 * tenants without an explicit, audited bypass.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->has()) {
            $builder->where($model->getTable().'.tenant_id', $context->id());
        }
    }
}
