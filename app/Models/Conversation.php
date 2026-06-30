<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $mode
 * @property int|null $assigned_to_user_id
 * @property Carbon|null $handoff_at
 * @property Carbon|null $opted_out_at
 */
class Conversation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * `contact_name` is plain display data and safe to mass-assign. `mode`,
     * `assigned_to_user_id`, `handoff_at`, and `opted_out_at` are DELIBERATELY
     * ABSENT (§13): they govern who controls the conversation, whether the AI
     * replies, and whether the contact may be bulk-messaged, so they are only
     * ever changed through the trusted methods below (handoffToHumans / claimBy /
     * returnToAi / optOut), never from request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'wa_contact_id',
        'contact_name',
        'status',
        'window_expires_at',
    ];

    /**
     * Default `mode` to 'ai' in memory too, not just at the DB column level.
     * firstOrCreate() does not pass `mode` (it is not fillable), so a freshly
     * created instance would otherwise read `mode` as null until reloaded —
     * harmless for the current readers but a latent trap for any future
     * isAiMode()/isHumanMode() call on a just-created row. Seed it explicitly.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'mode' => 'ai',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_expires_at' => 'datetime',
            'handoff_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }

    /**
     * Whether the 24-hour customer service window is still open (§11). Free-form
     * replies are only permitted while this is true; outside it a pre-approved
     * template is required. `window_expires_at` is the source of truth.
     */
    public function isWindowOpen(): bool
    {
        return $this->window_expires_at !== null
            && now()->lessThan($this->window_expires_at);
    }

    /**
     * In AI mode the bot answers automatically inside the webhook. In human
     * mode the bot stays silent and an agent replies from the inbox.
     */
    public function isAiMode(): bool
    {
        return $this->mode === 'ai';
    }

    public function isHumanMode(): bool
    {
        return $this->mode === 'human';
    }

    public function isAssigned(): bool
    {
        return $this->assigned_to_user_id !== null;
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_to_user_id !== null
            && $this->assigned_to_user_id === $user->id;
    }

    /**
     * Move the conversation out of the bot's hands into the human queue, leaving
     * it UNASSIGNED so any agent can pick it up (§13: never mass-assigned).
     */
    public function handoffToHumans(): void
    {
        $this->forceFill([
            'mode' => 'human',
            'handoff_at' => now(),
            'assigned_to_user_id' => null,
        ])->save();
    }

    /**
     * Atomically claim this conversation for $user. The UPDATE only matches
     * while `assigned_to_user_id` is still NULL, so when two agents race only
     * the first affects a row — the loser sees affected=0 and keeps off it.
     * Prevents two agents handling the same customer (§13). Returns whether
     * THIS call won the claim.
     */
    public function claimBy(User $user): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('assigned_to_user_id')
            ->update([
                'assigned_to_user_id' => $user->id,
                'mode' => 'human',
                'handoff_at' => now(),
            ]);

        // Reflect the new DB state locally regardless of who won.
        $this->refresh();

        return $claimed === 1;
    }

    /**
     * Hand the conversation back to the bot and clear the assignment (§13).
     */
    public function returnToAi(): void
    {
        $this->forceFill([
            'mode' => 'ai',
            'assigned_to_user_id' => null,
        ])->save();
    }

    /**
     * Whether this contact has unsubscribed from bulk messaging. Once opted out
     * they are permanently excluded from every campaign (Meta opt-out, §11).
     */
    public function isOptedOut(): bool
    {
        return $this->opted_out_at !== null;
    }

    /**
     * Mark this contact as opted out of bulk messaging (they sent an unsubscribe
     * keyword). Written via forceFill (§13: `opted_out_at` is not mass-assignable,
     * so a request can never clear it). Idempotent: a contact who opts out twice
     * keeps their original opt-out timestamp.
     */
    public function optOut(): void
    {
        if ($this->opted_out_at !== null) {
            return;
        }

        $this->forceFill(['opted_out_at' => now()])->save();
    }

    /**
     * Re-subscribe a contact the owner has decided to bring back (e.g. an opt-out
     * keyword fired by mistake). Written via forceFill (§13: `opted_out_at` is
     * not mass-assignable). This is the reversibility path for opt-out (§9): a
     * mistaken unsubscribe is never a dead end for the owner.
     */
    public function resubscribe(): void
    {
        if ($this->opted_out_at === null) {
            return;
        }

        $this->forceFill(['opted_out_at' => null])->save();
    }

    /**
     * Conversations eligible to receive a bulk message: opt-in is implicit (they
     * have a conversation = they messaged us first), and they have NOT opted out.
     * The 24h-window and cap checks happen per-recipient at send time in the job,
     * never here — eligibility is the opt-in/opt-out gate only (§11).
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeEligibleForBulk(Builder $query): Builder
    {
        return $query->whereNull('opted_out_at');
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeHumanMode(Builder $query): Builder
    {
        return $query->where('mode', 'human');
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to_user_id', $userId);
    }

    /**
     * Human conversations awaiting an agent (handed off, not yet claimed).
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeUnassignedHuman(Builder $query): Builder
    {
        return $query->where('mode', 'human')->whereNull('assigned_to_user_id');
    }

    /**
     * @return BelongsTo<WhatsappAccount, $this>
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The most recent message, eager-loadable so the conversations index can
     * show last-activity context without an N+1 (§14).
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
