<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;

/**
 * Read/write access to platform-wide settings (§13), most importantly the
 * admin-managed platform AI keys (gemini/openai/deepseek).
 *
 * Values are stored encrypted at rest by {@see Setting} and are read ONLY here
 * and inside the AI resolution layer. This service NEVER prints nor logs a
 * value: it returns the decrypted string to its caller and that is all (§13).
 *
 * A tiny in-request memo cache avoids re-decrypting / re-querying the same key
 * within a single request (the hot reply path may resolve a key per message).
 * It is intentionally process-local and is invalidated on every write.
 */
class PlatformSettings
{
    /**
     * In-request memo of resolved values, keyed by setting key. `null` is a
     * valid, cached value (the key exists but was cleared, or does not exist).
     *
     * @var array<string, string|null>
     */
    private array $cache = [];

    /**
     * Decrypted value for $key, or null when unset/cleared. Never logged (§13).
     */
    public function get(string $key): ?string
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $value = Setting::query()->where('key', $key)->value('value');

        // The `encrypted` cast already decrypted it on read; normalise empty to
        // null so callers can treat "unset" and "blank" the same way.
        $value = is_string($value) && $value !== '' ? $value : null;

        return $this->cache[$key] = $value;
    }

    /**
     * Upsert $key with $value (encrypted at rest by the model). Passing null
     * clears the stored secret. Non-destructive by key: only this row changes
     * (§3 — no "delete all then insert").
     */
    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        // Keep the in-request memo coherent with what was just written.
        $this->cache[$key] = is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Drop the in-request memo (e.g. after an external write in tests).
     */
    public function forget(): void
    {
        $this->cache = [];
    }
}
