<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\UsageCounter;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Read-only usage analytics for the active tenant (§1, §3, §14).
 *
 * Every figure is summed from `usage_counters`, which the TenantScope global
 * scope already filters to the current tenant — there is no manual
 * `where('tenant_id')` (ADR-02). All amounts stay integers end-to-end; money is
 * micro-USD and only converted for display by the <x-cost> component (§3).
 */
class AnalyticsController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();
        $windowStart = $today->copy()->subDays(29); // inclusive 30-day window
        $chartStart = $today->copy()->subDays(13);   // inclusive 14-day window

        // Today's message count (single scalar aggregate).
        $messagesToday = (int) UsageCounter::query()
            ->whereDate('date', $today)
            ->sum('messages');

        // 30-day rollups. Use whereDate, NOT a string BETWEEN: the `date`
        // attribute is cast to `date`, which Eloquent serialises WITH a time
        // part, so `BETWEEN [start, today]` drops the today (upper-bound) row.
        // whereDate compares the DATE part on both sides — correctness over a
        // micro-index gain on this small per-tenant table (§3, §14).
        /** @var object{messages: int|null, tokens_in: int|null, tokens_out: int|null, cost_micros: int|null}|null $totals */
        $totals = UsageCounter::query()
            ->whereDate('date', '>=', $windowStart)
            ->whereDate('date', '<=', $today)
            ->selectRaw('COALESCE(SUM(messages),0) as messages')
            ->selectRaw('COALESCE(SUM(tokens_in),0) as tokens_in')
            ->selectRaw('COALESCE(SUM(tokens_out),0) as tokens_out')
            ->selectRaw('COALESCE(SUM(cost_micros),0) as cost_micros')
            ->first();

        $messages30 = (int) ($totals->messages ?? 0);
        $tokens30 = (int) ($totals->tokens_in ?? 0) + (int) ($totals->tokens_out ?? 0);
        $cost30Micros = (int) ($totals->cost_micros ?? 0);

        // 14-day daily message series for the CSS bar chart. Pull the rows once,
        // then key by the normalised Y-m-d date (the `date` cast may stringify
        // as a full datetime depending on the driver). Filling every day in PHP
        // keeps the chart gap-free with no per-day query (§14).
        $daily = [];
        UsageCounter::query()
            ->whereDate('date', '>=', $chartStart)
            ->whereDate('date', '<=', $today)
            ->get(['date', 'messages'])
            ->each(function (UsageCounter $row) use (&$daily): void {
                // Normalise to Y-m-d; the stored value may be a date or a full
                // datetime depending on the driver, so parse defensively.
                $key = Carbon::parse((string) $row->date)->format('Y-m-d');
                $daily[$key] = (int) $row->messages;
            });

        $series = [];
        $maxMessages = 0;

        for ($cursor = $chartStart->copy(); $cursor->lessThanOrEqualTo($today); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $count = $daily[$key] ?? 0;
            $maxMessages = max($maxMessages, $count);

            $series[] = [
                'date' => $cursor->copy(),
                'messages' => $count,
            ];
        }

        return view('dashboard.analytics.index', [
            'messagesToday' => $messagesToday,
            'messages30' => $messages30,
            'tokens30' => $tokens30,
            'cost30Micros' => $cost30Micros,
            'series' => $series,
            'maxMessages' => $maxMessages,
        ]);
    }
}
