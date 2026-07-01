<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use App\Services\Bulk\TemplateSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/**
 * Owner-only management of Meta-approved message templates (Phase 7c, §11/§13).
 * The route is behind the `owner` middleware, so an agent never reaches here (403).
 * Every read/write is filtered by TenantScope, so a foreign template 404s (§1).
 *
 * Templates are CREATED and APPROVED in Meta Business Manager — this screen only
 * MIRRORS their live status (TemplateSync) so the bulk form can offer only the
 * approved ones, plus a manual-entry fallback for when sync is unavailable. A
 * manual entry is always non-approved (status is server-controlled, §13); only a
 * Meta sync can confirm approval.
 */
class MessageTemplateController extends Controller
{
    /**
     * List this tenant's templates with their cached status/category/variables.
     */
    public function index(): View
    {
        // Single account per tenant, TenantScope-filtered (foreign → not found).
        $account = WhatsappAccount::query()->first();

        $templates = MessageTemplate::query()
            ->orderBy('name')
            ->orderBy('language')
            ->paginate(30);

        return view('dashboard.templates.index', [
            'account' => $account,
            'templates' => $templates,
            // The wizard can only sync once the WABA id + token are present.
            'syncReady' => $account !== null
                && filled($account->waba_id)
                && filled($account->access_token),
        ]);
    }

    /**
     * Pull the live templates from Meta and mirror their status into the cache
     * (TemplateSync). Failures are reported and surfaced gently — never a raw 500,
     * never the token (§3, §13). The owner sees how many templates were synced.
     */
    public function sync(TemplateSync $sync): RedirectResponse
    {
        $account = WhatsappAccount::query()->first();

        if ($account === null || ! filled($account->waba_id) || ! filled($account->access_token)) {
            return back()->withErrors([
                'sync' => 'أكمل بيانات الربط أولاً (معرّف حساب واتساب للأعمال والتوكن).',
            ]);
        }

        try {
            $count = $sync->sync($account);
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors([
                'sync' => 'تعذّرت المزامنة مع ميتا. تأكّد من بيانات الربط وحاوِل مجدداً.',
            ]);
        }

        return back()->with('status', "synced:{$count}");
    }

    /**
     * Manually register a template the owner has already created in Meta (the
     * fallback when sync is unavailable). status is server-controlled (NEVER from
     * input, §13) — a manual entry is 'unknown', so it is NOT sendable until a Meta
     * sync confirms it approved. variable_count is derived from the body server-side
     * (the {{n}} placeholders), never trusted from input.
     */
    public function store(StoreMessageTemplateRequest $request): RedirectResponse
    {
        $account = WhatsappAccount::query()->first();

        if ($account === null) {
            return back()->withErrors([
                'name' => 'لا يوجد رقم واتساب مربوط بعد. اربط رقمك أولاً.',
            ])->withInput();
        }

        $name = (string) $request->validated('name');
        $language = (string) $request->validated('language');
        $bodyText = $request->validated('body_text');
        $bodyText = is_string($bodyText) && $bodyText !== '' ? $bodyText : null;

        // Upsert on the trusted (account, name, language) key so a duplicate manual
        // add updates rather than collides on the unique index (§3 non-destructive).
        $template = MessageTemplate::query()->firstOrNew([
            'whatsapp_account_id' => $account->id,
            'name' => $name,
            'language' => $language,
        ]);

        // Owner-authored descriptors only.
        $template->fill([
            'whatsapp_account_id' => $account->id,
            'name' => $name,
            'language' => $language,
            'category' => (string) $request->validated('category'),
            'body_text' => $bodyText,
        ]);

        // Server-controlled fields (§13): status stays 'unknown' until Meta sync
        // confirms approval, and variable_count is derived from the body, never
        // trusted from input.
        $template->forceFill([
            'status' => $request->defaultStatus(),
            'variable_count' => $this->countVariables($bodyText),
        ])->save();

        return redirect()
            ->route('templates.index')
            ->with('status', 'template-added');
    }

    /**
     * Count the highest {{n}} placeholder index in the body — the number of
     * variables the template requires. Mirrors TemplateSync's count so a manual
     * entry and a synced one agree.
     */
    private function countVariables(?string $bodyText): int
    {
        if ($bodyText === null || $bodyText === '') {
            return 0;
        }

        if (preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyText, $matches) === false) {
            return 0;
        }

        $indexes = array_map('intval', $matches[1]);

        return $indexes === [] ? 0 : max($indexes);
    }
}
