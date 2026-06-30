<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreBulkCampaignRequest;
use App\Models\BulkCampaign;
use App\Models\Conversation;
use App\Models\WhatsappAccount;
use App\Services\Bulk\BulkCampaignService;
use App\Services\Bulk\BulkCapExceededException;
use App\Services\Bulk\SendQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Bulk messaging — OWNER ONLY (behind auth+tenant+owner, §13). This is a
 * SENSITIVE path: it sends to customers' numbers and can get a number banned, so
 * every Meta guard (opt-in, 250 cap, 24h window, opt-out) is enforced upstream
 * in BulkCampaignService / SendBulkMessageJob / SendQuota.
 *
 * Isolation (§1): every WhatsappAccount / BulkCampaign read runs through the
 * TenantScope (firstOrFail / route-model binding), so a foreign campaign 404s.
 */
class BulkCampaignController extends Controller
{
    public function __construct(
        private readonly BulkCampaignService $service,
        private readonly SendQuota $quota,
    ) {}

    /**
     * The campaigns list + the new-campaign form, with the live eligible-count
     * and remaining-cap figures so the owner sees exactly what a send would do.
     */
    public function index(): View
    {
        // Single account per tenant, TenantScope-filtered (foreign → not found).
        $account = WhatsappAccount::query()->first();

        // DB-level COUNT(*), not a hydrated collection — the list page needs only
        // the number (§14, shared hosting).
        $eligibleCount = $account === null ? 0 : $this->service->eligibleRecipientsCount($account);
        $remaining = $account === null ? 0 : $this->quota->remainingToday($account);

        $campaigns = BulkCampaign::query()
            ->withCount('recipients')
            ->latest()
            ->paginate(15);

        // Opted-out contacts the owner may have lost by mistake — shown with a
        // re-subscribe control so opt-out is reversible (§9).
        $optedOut = Conversation::query()
            ->whereNotNull('opted_out_at')
            ->latest('opted_out_at')
            ->limit(50)
            ->get(['id', 'wa_contact_id', 'contact_name', 'opted_out_at']);

        return view('dashboard.bulk.index', [
            'account' => $account,
            'campaigns' => $campaigns,
            'eligibleCount' => $eligibleCount,
            'remaining' => $remaining,
            'optedOut' => $optedOut,
        ]);
    }

    /**
     * Create + dispatch a campaign to all currently-eligible contacts. The
     * recipient set is derived server-side (opt-in/opt-out enforced), never from
     * input. A count over the day's remaining cap is rejected loudly (§3, §11).
     */
    public function store(StoreBulkCampaignRequest $request): RedirectResponse
    {
        $account = WhatsappAccount::query()->first();

        if ($account === null) {
            return back()->withErrors([
                'body' => 'لا يوجد رقم واتساب مربوط بعد. اربط رقمك أولاً.',
            ])->withInput();
        }

        $recipients = $this->service->eligibleRecipients($account);

        if ($recipients->isEmpty()) {
            return back()->withErrors([
                'body' => 'لا يوجد مستلمون مؤهلون (جهات تفاعلت معك ولم تنسحب).',
            ])->withInput();
        }

        try {
            $this->service->create(
                $account,
                $request->user(),
                (string) $request->validated('body'),
                $recipients,
            );
        } catch (BulkCapExceededException $e) {
            // Loud, explicit rejection with the exact remaining headroom (§3).
            return back()->withErrors([
                'body' => "عدد المستلمين يتجاوز المتبقّي من السقف اليومي ({$e->remaining}). قلّل العدد أو انتظر الغد.",
            ])->withInput();
        }

        return redirect()
            ->route('bulk.index')
            ->with('status', 'bulk-campaign-queued');
    }

    /**
     * One campaign's per-recipient breakdown. Route-model bound through the
     * TenantScope, so a foreign campaign 404s (IDOR, §1).
     */
    public function show(BulkCampaign $campaign): View
    {
        $recipients = $campaign->recipients()
            ->with('conversation:id,wa_contact_id,contact_name')
            ->latest('id')
            ->paginate(50);

        return view('dashboard.bulk.show', [
            'campaign' => $campaign,
            'recipients' => $recipients,
        ]);
    }

    /**
     * The kill switch (§9): stop a running campaign so its still-queued sends are
     * skipped. $campaign is TenantScope-bound (foreign → 404). Already-finished
     * campaigns are stopped harmlessly (the queued jobs simply see no pending).
     */
    public function stop(BulkCampaign $campaign): RedirectResponse
    {
        $this->service->stop($campaign);

        return redirect()
            ->route('bulk.index')
            ->with('status', 'bulk-campaign-stopped');
    }

    /**
     * Reversibility for opt-out (§9): the owner brings a contact back into the
     * eligible audience (e.g. an unsubscribe keyword fired by mistake).
     * $conversation is TenantScope-bound, so a foreign one 404s (IDOR, §1).
     */
    public function resubscribe(Conversation $conversation): RedirectResponse
    {
        $conversation->resubscribe();

        return redirect()
            ->route('bulk.index')
            ->with('status', 'bulk-contact-resubscribed');
    }
}
