<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\UsageCounter;
use App\Models\WhatsappAccount;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Tenant dashboard overview. All figures are summed through the TenantScope
 * global scope (no manual where('tenant_id'), §1); money stays integer
 * micro-USD and is only converted for display by <x-cost> (§3). Aggregation
 * lives here, not in the Blade view (separation of concerns, §5).
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $account = WhatsappAccount::query()->first();

        // whereDate (not string BETWEEN): the `date` cast serialises with a time
        // part, so a plain BETWEEN would drop the upper-boundary row (§3).
        $messagesToday = (int) UsageCounter::query()
            ->whereDate('date', Carbon::today())
            ->sum('messages');

        $monthCostMicros = (int) UsageCounter::query()
            ->whereDate('date', '>=', Carbon::now()->startOfMonth())
            ->whereDate('date', '<=', Carbon::now()->endOfMonth())
            ->sum('cost_micros');

        return view('dashboard', [
            'account' => $account,
            'isConnected' => $account !== null
                && filled($account->phone_number_id)
                && filled($account->access_token),
            'knowledgeCount' => KnowledgeDocument::query()->count(),
            'messagesToday' => $messagesToday,
            'activeConversations' => Conversation::query()->where('status', 'open')->count(),
            'monthCostMicros' => $monthCostMicros,
        ]);
    }
}
