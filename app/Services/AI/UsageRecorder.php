<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Accumulates per-tenant daily usage into `usage_counters` (§12 — token/cost
 * budget). One row per (tenant_id, date); each successful generation increments
 * it. All quantities are integers (no float for money/tokens, §3).
 *
 * Idempotency note: this records each successful generation once. Webhook-level
 * idempotency (the unique `wa_message_id`) guarantees a given inbound message
 * is processed at most once, so we never double-count a single reply.
 *
 * Implementation note: we go through the query builder (not Eloquent) on a
 * normalised `Y-m-d` date string. The model's `date` cast otherwise stores a
 * `Y-m-d H:i:s` value, which breaks `firstOrCreate`'s match on some drivers and
 * silently loses increments. Here the WHERE and the INSERT use the exact same
 * string, so the daily row is matched (and incremented) reliably.
 */
class UsageRecorder
{
    /**
     * Increment today's usage counters for the tenant. Non-fatal by design:
     * if accounting fails it is reported, not thrown, so a metering hiccup
     * never blocks an already-sent customer reply (§3 — explicit, not silent).
     */
    public function record(int $tenantId, int $tokensIn, int $tokensOut, int $costMicros): void
    {
        $tokensIn = max(0, $tokensIn);
        $tokensOut = max(0, $tokensOut);
        $costMicros = max(0, $costMicros);

        $date = now()->toDateString();

        try {
            // Fast path: increment the existing daily row in a single statement.
            $updated = DB::table('usage_counters')
                ->where('tenant_id', $tenantId)
                ->where('date', $date)
                ->update([
                    'messages' => DB::raw('messages + 1'),
                    'tokens_in' => DB::raw('tokens_in + '.$tokensIn),
                    'tokens_out' => DB::raw('tokens_out + '.$tokensOut),
                    'cost_micros' => DB::raw('cost_micros + '.$costMicros),
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                return;
            }

            // No row yet for today: create it with the first reading.
            try {
                DB::table('usage_counters')->insert([
                    'tenant_id' => $tenantId,
                    'date' => $date,
                    'messages' => 1,
                    'tokens_in' => $tokensIn,
                    'tokens_out' => $tokensOut,
                    'cost_micros' => $costMicros,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                // Lost the create race (unique tenant_id+date): another concurrent
                // webhook inserted first. Retry the increment so nothing is lost.
                $retried = DB::table('usage_counters')
                    ->where('tenant_id', $tenantId)
                    ->where('date', $date)
                    ->update([
                        'messages' => DB::raw('messages + 1'),
                        'tokens_in' => DB::raw('tokens_in + '.$tokensIn),
                        'tokens_out' => DB::raw('tokens_out + '.$tokensOut),
                        'cost_micros' => DB::raw('cost_micros + '.$costMicros),
                        'updated_at' => now(),
                    ]);

                if ($retried === 0) {
                    // Truly unexpected; surface it.
                    throw $e;
                }
            }
        } catch (Throwable $e) {
            // Metering must never break the reply path; surface, don't crash.
            report($e);
        }
    }
}
