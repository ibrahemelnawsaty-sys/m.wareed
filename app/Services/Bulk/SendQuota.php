<?php

declare(strict_types=1);

namespace App\Services\Bulk;

use App\Models\Tenant;
use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\DB;

/**
 * The atomic daily send-cap enforcer (Phase 6d, §11, §13).
 *
 * Devil's advocate (§9): the worst outcome of a bulk feature is BANNING the
 * customer's number by blowing past Meta's daily messaging limit. The defence is
 * a HARD per-number daily cap (default 250, never above 250) that holds even
 * under concurrency — two queue workers reserving a slot at the same instant must
 * never both succeed at cap-1. So reservation is a SINGLE conditional UPDATE on
 * one ledger row; the row lock serialises the increment and an over-cap update
 * affects 0 rows. No read-then-write race window exists.
 */
class SendQuota
{
    /**
     * How many bulk messages this account may still send today: the effective
     * cap (min(tenant cap, 250)) minus what has already been sent. Never below 0.
     * This is a READ for display/pre-flight only — the binding decision is
     * tryConsume(), which is atomic. A caller must never gate sending on this
     * value alone under concurrency.
     */
    public function remainingToday(WhatsappAccount $account): int
    {
        $cap = $this->capFor($account);

        // Query the raw table with a canonical Y-m-d date so the lookup matches
        // the value stored by tryConsume exactly (no Eloquent date-cast
        // serialization mismatch). Cross-tenant rows can never collide here: the
        // (whatsapp_account_id, send_date) pair is unique.
        $sent = (int) DB::table('daily_send_counters')
            ->where('whatsapp_account_id', $account->id)
            ->where('send_date', today()->toDateString())
            ->value('sent_count');

        return max(0, $cap - $sent);
    }

    /**
     * Atomically reserve ONE send slot for today. Returns true if the slot was
     * reserved (caller may send), false if the cap is already reached (caller
     * must NOT send — "more than 250 locks it down").
     *
     * Implementation: firstOrCreate the (account, today) ledger row, then a
     * single conditional increment `... SET sent_count = sent_count + 1 WHERE id
     * = ? AND sent_count < :cap`. MySQL takes a row lock for the UPDATE, so
     * concurrent workers serialise on it; the one that would push the count past
     * the cap matches 0 rows. affected === 1 ⇒ this caller holds the slot.
     */
    public function tryConsume(WhatsappAccount $account): bool
    {
        $cap = $this->capFor($account);

        // No headroom configured at all — never send.
        if ($cap < 1) {
            return false;
        }

        // Canonical Y-m-d date used for BOTH the find and the insert, so the
        // unique (whatsapp_account_id, send_date) row is addressed consistently
        // (no Eloquent date-cast format drift that would make the find miss and
        // trigger a duplicate insert).
        $date = today()->toDateString();
        $now = now();

        // Ensure the ledger row exists (idempotent). Raw query builder so the
        // stored `send_date` is exactly $date. insertOrIgnore is race-safe
        // against the unique index — a colliding concurrent insert is ignored,
        // not thrown — so two workers starting the day's first send both proceed
        // to the atomic increment below against the SAME row.
        DB::table('daily_send_counters')->insertOrIgnore([
            'tenant_id' => $account->tenant_id,
            'whatsapp_account_id' => $account->id,
            'send_date' => $date,
            'sent_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // The atomic reservation. The WHERE guards the cap inside the same
        // statement, so the increment and the limit check are one indivisible
        // operation — no read-then-write window, no over-cap under concurrency.
        $affected = DB::table('daily_send_counters')
            ->where('whatsapp_account_id', $account->id)
            ->where('send_date', $date)
            ->where('sent_count', '<', $cap)
            ->update([
                'sent_count' => DB::raw('sent_count + 1'),
                'updated_at' => $now,
            ]);

        return $affected === 1;
    }

    /**
     * The effective cap for the account's tenant: the configured daily_bulk_cap
     * but never above the hard 250 ceiling (§11). Falls back to the 250 default
     * if the tenant is somehow unloaded.
     */
    private function capFor(WhatsappAccount $account): int
    {
        $tenant = $account->tenant;

        if ($tenant === null) {
            return Tenant::MAX_BULK_CAP;
        }

        return $tenant->effectiveBulkCap();
    }
}
