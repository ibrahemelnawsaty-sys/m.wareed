<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceMenuRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One selectable row of a {@see ServiceMenu} (Phase 7b, §11).
 *
 * `tenant_id` and `row_key` are DELIBERATELY ABSENT from $fillable (§13):
 * `tenant_id` comes from the bound TenantContext via {@see BelongsToTenant}, and
 * `row_key` is generated server-side from the row's position — never accepted
 * from request input, so the list-reply id space stays under our control.
 *
 * @property string $row_key
 * @property string $title
 * @property string|null $description
 * @property string $action_type
 * @property string|null $reply_text
 * @property int $sort_order
 */
class ServiceMenuRow extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ServiceMenuRowFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_menu_id',
        'title',
        'description',
        'action_type',
        'reply_text',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Tapping this row sends the canned reply_text and stays on the bot.
     */
    public function isReply(): bool
    {
        return $this->action_type === 'reply';
    }

    /**
     * Tapping this row hands the conversation to a human agent.
     */
    public function isHandoff(): bool
    {
        return $this->action_type === 'handoff';
    }

    /**
     * @return BelongsTo<ServiceMenu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(ServiceMenu::class, 'service_menu_id');
    }
}
