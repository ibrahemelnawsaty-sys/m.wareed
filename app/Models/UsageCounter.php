<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UsageCounterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageCounter extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<UsageCounterFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'date',
        'messages',
        'tokens_in',
        'tokens_out',
        'cost_micros',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'messages' => 'integer',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_micros' => 'integer',
        ];
    }
}
