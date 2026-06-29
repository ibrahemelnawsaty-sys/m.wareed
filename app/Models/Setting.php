<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single platform-wide setting (key/value), managed by the super-admin (§13).
 *
 * The store for admin-managed platform AI keys (gemini/openai/deepseek). The
 * `value` is ALWAYS encrypted at rest via the `encrypted` cast, lives in
 * `$hidden` so it never escapes through array/JSON serialization, and is read
 * ONLY inside the AI resolution layer — never printed nor logged (§13).
 *
 * Not a tenant model: settings are global to the platform, so there is no
 * tenant_id and no TenantScope here.
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * The encrypted secret value never leaves the model via serialization (§13).
     *
     * @var list<string>
     */
    protected $hidden = [
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Encrypted at rest: the ciphertext in `settings.value` is never the
            // plaintext key (§3, §13).
            'value' => 'encrypted',
        ];
    }
}
