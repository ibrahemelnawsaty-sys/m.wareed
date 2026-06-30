<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\SiteSetting;

/**
 * Read/write access to the PUBLIC site content that drives the marketing
 * landing page and its SEO metadata (Phase 4h).
 *
 * This is the public-content counterpart to {@see PlatformSettings}: the values
 * here are NOT secrets — they are the copy every visitor sees — so they are
 * stored and returned as plaintext (no encryption, §13 applies only to secrets).
 *
 * Called from two surfaces: the landing page (read, with a hard-coded default
 * per field so nothing breaks when unset) and the admin SiteController (write).
 *
 * A tiny in-request memo cache avoids re-querying the same key within a single
 * request — the landing page resolves a dozen keys per render. The whole row set
 * is loaded once on first access. The memo is process-local and invalidated on
 * every write.
 */
class SiteSettings
{
    /**
     * In-request memo of every key → value, loaded lazily on first access.
     * `null` once loaded means "table read"; a missing key means "not set".
     *
     * @var array<string, string|null>|null
     */
    private ?array $cache = null;

    /**
     * The stored value for $key, or $default when the key is unset/blank. The
     * default keeps the landing page intact even before the admin saves anything.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        return $value !== null && $value !== '' ? $value : $default;
    }

    /**
     * Upsert $key with $value. A null/blank value clears the row so the landing
     * page reverts to its hard-coded default (§3 — non-destructive by key: only
     * this one row changes, never a "delete all then insert").
     */
    public function set(string $key, ?string $value): void
    {
        $normalised = is_string($value) && $value !== '' ? $value : null;

        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $normalised],
        );

        // Keep the in-request memo coherent with what was just written.
        if ($this->cache !== null) {
            $this->cache[$key] = $normalised;
        }
    }

    /**
     * Every stored key → value as a flat map (memoised for the request). Blank
     * values are normalised to null so callers can treat "unset" and "blank"
     * the same way.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        return $this->cache = SiteSetting::query()
            ->pluck('value', 'key')
            ->map(fn ($value) => is_string($value) && $value !== '' ? $value : null)
            ->all();
    }

    /**
     * Drop the in-request memo (e.g. after an external write in tests).
     */
    public function forget(): void
    {
        $this->cache = null;
    }
}
