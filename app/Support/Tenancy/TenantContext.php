<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Holds the identifier of the currently active tenant for the running request
 * or process. Registered as a singleton so every consumer (TenantScope,
 * BelongsToTenant, services) reads and writes the same value.
 */
class TenantContext
{
    private ?int $tenantId = null;

    /**
     * The current tenant identifier, or null when no tenant is bound.
     */
    public function id(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Bind the active tenant.
     */
    public function set(int $id): void
    {
        // Tenant primary keys start at 1; 0 (e.g. from a stray `(int) null`)
        // is never a valid tenant. Reject it loudly instead of silently
        // binding a phantom tenant and leaking across the isolation boundary.
        if ($id < 1) {
            throw new \InvalidArgumentException("Tenant id must be a positive integer; got {$id}.");
        }

        $this->tenantId = $id;
    }

    /**
     * Clear the active tenant (e.g. after a request or in tests).
     */
    public function forget(): void
    {
        $this->tenantId = null;
    }

    /**
     * Whether a tenant is currently bound.
     */
    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Run a callback with the given tenant bound, restoring the previous
     * binding afterwards — even if the callback throws. Use this on every
     * queue job / CLI path so tenant state never leaks from one unit of work
     * to the next inside a long-lived process (e.g. `queue:work`) (§3, ADR-02).
     *
     * @template TReturn
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(int $id, \Closure $callback): mixed
    {
        if ($id < 1) {
            throw new \InvalidArgumentException("Tenant id must be a positive integer; got {$id}.");
        }

        $previous = $this->tenantId;
        $this->tenantId = $id;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
