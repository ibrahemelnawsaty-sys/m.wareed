<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * The atomic daily send ledger row: one per (whatsapp_account, calendar day),
 * holding `sent_count` for that number that day (Phase 6d, §11). The cap is
 * enforced by a conditional UPDATE in App\Services\Bulk\SendQuota, not here.
 *
 * tenant-owned (BelongsToTenant) for isolation/audit — but the atomicity key is
 * the unique (whatsapp_account_id, send_date) index, so all of a number's jobs
 * contend on ONE row.
 *
 * @property int $id
 * @property int $sent_count
 */
class DailySendCounter extends Model
{
    use BelongsToTenant;

    /**
     * `tenant_id`, `whatsapp_account_id`, `send_date`, and `sent_count` are the
     * ledger identity/value. They are written only by SendQuota via firstOrCreate
     * + a raw conditional UPDATE (never request input), so the model is not used
     * for mass assignment of `sent_count` from anywhere user-facing (§13).
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'send_date',
        'sent_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'send_date' => 'date',
            'sent_count' => 'integer',
        ];
    }
}
