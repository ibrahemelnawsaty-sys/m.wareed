<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\UsageCounter;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Platform-wide analytics across EVERY tenant (§1, §13).
 *
 * The admin has no bound tenant, so every aggregate calls withoutGlobalScopes()
 * EXPLICITLY — the audited cross-tenant exception. All money stays integer
 * micro-USD; only <x-cost> divides for display (§3). No JS chart library — a
 * lightweight CSS bar chart keeps the page cheap on shared hosting (§14).
 */
class AnalyticsController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();
        $windowStart = $today->copy()->subDays(29); // inclusive 30-day window

        // Today's totals (all tenants).
        /** @var object{messages: int|null, tokens_in: int|null, tokens_out: int|null, cost_micros: int|null}|null $todayTotals */
        $todayTotals = UsageCounter::query()
            ->withoutGlobalScopes()
            ->whereDate('date', $today)
            ->selectRaw('COALESCE(SUM(messages),0) as messages')
            ->selectRaw('COALESCE(SUM(tokens_in),0) as tokens_in')
            ->selectRaw('COALESCE(SUM(tokens_out),0) as tokens_out')
            ->selectRaw('COALESCE(SUM(cost_micros),0) as cost_micros')
            ->first();

        // 30-day totals (all tenants). whereDate, not BETWEEN: the `date` cast
        // serialises with a time part, so BETWEEN would drop the today row (§3).
        /** @var object{messages: int|null, tokens_in: int|null, tokens_out: int|null, cost_micros: int|null}|null $monthTotals */
        $monthTotals = UsageCounter::query()
            ->withoutGlobalScopes()
            ->whereDate('date', '>=', $windowStart)
            ->whereDate('date', '<=', $today)
            ->selectRaw('COALESCE(SUM(messages),0) as messages')
            ->selectRaw('COALESCE(SUM(tokens_in),0) as tokens_in')
            ->selectRaw('COALESCE(SUM(tokens_out),0) as tokens_out')
            ->selectRaw('COALESCE(SUM(cost_micros),0) as cost_micros')
            ->first();

        // Customer distribution by status (all tenants).
        $byStatus = Tenant::query()
            ->withoutGlobalScopes()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusCounts = [
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'active' => (int) ($byStatus['active'] ?? 0),
            'suspended' => (int) ($byStatus['suspended'] ?? 0),
        ];
        $totalCustomers = array_sum($statusCounts);

        // Top 10 customers by all-time message volume. One grouped query joined
        // to tenant names — no N+1 (§14). Cross-tenant read, scope bypassed (§1).
        $topRows = UsageCounter::query()
            ->withoutGlobalScopes()
            ->selectRaw('tenant_id')
            ->selectRaw('COALESCE(SUM(messages),0) as messages')
            ->selectRaw('COALESCE(SUM(cost_micros),0) as cost_micros')
            ->groupBy('tenant_id')
            ->orderByDesc('messages')
            ->limit(10)
            ->get();

        $tenantNames = Tenant::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $topRows->pluck('tenant_id'))
            ->pluck('name', 'id');

        $maxTopMessages = (int) ($topRows->max('messages') ?? 0);

        $topCustomers = $topRows->map(fn ($row): array => [
            'name' => (string) ($tenantNames[$row->tenant_id] ?? '—'),
            'messages' => (int) $row->messages,
            'costMicros' => (int) $row->cost_micros,
        ])->all();

        return view('admin.analytics.index', [
            'messagesToday' => (int) ($todayTotals->messages ?? 0),
            'tokensToday' => (int) ($todayTotals->tokens_in ?? 0) + (int) ($todayTotals->tokens_out ?? 0),
            'costTodayMicros' => (int) ($todayTotals->cost_micros ?? 0),
            'messages30' => (int) ($monthTotals->messages ?? 0),
            'tokens30' => (int) ($monthTotals->tokens_in ?? 0) + (int) ($monthTotals->tokens_out ?? 0),
            'cost30Micros' => (int) ($monthTotals->cost_micros ?? 0),
            'statusCounts' => $statusCounts,
            'totalCustomers' => $totalCustomers,
            'topCustomers' => $topCustomers,
            'maxTopMessages' => $maxTopMessages,
        ]);
    }
}
