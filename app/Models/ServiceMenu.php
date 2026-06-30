<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceMenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The single interactive service menu for a tenant (Phase 7b, §11).
 *
 * `tenant_id` is DELIBERATELY ABSENT from $fillable (§13): it is set only from
 * the bound TenantContext by {@see BelongsToTenant}, never from request input.
 * The display fields are plain owner-authored copy and safe to mass-assign.
 *
 * @property bool $enabled
 * @property string|null $header
 * @property string $body
 * @property string $button_label
 * @property string|null $footer
 * @property bool $trigger_on_welcome
 */
class ServiceMenu extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ServiceMenuFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'enabled',
        'header',
        'body',
        'button_label',
        'footer',
        'trigger_on_welcome',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'trigger_on_welcome' => 'boolean',
        ];
    }

    /**
     * Whether this menu is live. A disabled menu is dormant: the webhook never
     * offers it and inbound messages take the normal AI path.
     */
    public function isEnabled(): bool
    {
        return $this->enabled === true;
    }

    /**
     * The rows the customer can tap, in display order.
     *
     * @return HasMany<ServiceMenuRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ServiceMenuRow::class)->orderBy('sort_order');
    }
}
