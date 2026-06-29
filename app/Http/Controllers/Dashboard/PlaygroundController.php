<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PlaygroundSendRequest;
use App\Models\WhatsappAccount;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ProviderResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/**
 * Bot playground (§12, §13, §14). Lets the signed-in tenant try its own bot live
 * against Gemini WITHOUT WhatsApp.
 *
 * EPHEMERAL BY DESIGN — this is the load-bearing guarantee here: a playground
 * round persists NOTHING. It never creates a Conversation or Message row and
 * never calls UsageRecorder, so trying the bot can never pollute real customer
 * data nor inflate the tenant's usage/billing counters (§3, §4). The Gemini call
 * is made directly via the resolved provider client, deliberately bypassing
 * AiReplyService (which would record usage).
 *
 * Isolation (§1): the account is resolved through the TenantScope global scope,
 * so the prompt is always built from the CURRENT tenant's own account — there is
 * no way to address another tenant's bot.
 *
 * Secrets (§13): the system prompt is never returned to the browser, and the API
 * key (tenant key, platform setting, or .env fallback) is never echoed, logged,
 * or leaked in the error path. On any provider failure we report($e) and return a
 * polite JSON error — no stack trace, no key, no crash.
 *
 * Provider parity (§12): the playground tries the SAME provider/model/key the
 * webhook would use for this account, resolved via {@see ProviderResolver}.
 */
class PlaygroundController extends Controller
{
    public function __construct(
        private readonly PromptBuilder $promptBuilder,
        private readonly ProviderResolver $resolver,
    ) {}

    /**
     * Render the playground UI for the active tenant's account.
     */
    public function index(): View|RedirectResponse
    {
        // Activation gate (§9): the playground runs the live bot, so it honours
        // the SAME rule as the webhook — a pending / suspended / expired tenant
        // may not run it (it would drain the platform key). Send them to the
        // dashboard, where the status banner explains their state (§10).
        $tenant = auth()->user()?->tenant;

        if ($tenant === null || ! $tenant->isActive()) {
            return redirect()->route('dashboard');
        }

        $account = WhatsappAccount::query()->first();

        // No bot provisioned yet — guide the user instead of a raw 404 (§10).
        if ($account === null) {
            return redirect()->route('whatsapp.edit')
                ->with('status', 'اربط رقم واتساب أولاً لتجربة البوت.');
        }

        return view('dashboard.playground.index', [
            'account' => $account,
        ]);
    }

    /**
     * Run one ephemeral turn through Gemini and return the reply as JSON.
     *
     * No model is touched on the write side: no Conversation, no Message, no
     * usage_counters row. The untrusted message rides as a single `user` turn,
     * while the trusted persona/knowledge live only in the system instruction
     * the PromptBuilder hardens (§12).
     */
    public function send(PlaygroundSendRequest $request): JsonResponse
    {
        // Activation gate (§9): mirror WebhookController — a pending / suspended
        // / expired tenant must NOT run the bot, not even in the playground,
        // which would otherwise drain the platform Gemini key with no admin
        // approval (closes review finding H1).
        $tenant = $request->user()->tenant;

        if ($tenant === null || ! $tenant->isActive()) {
            return response()->json([
                'message' => 'حسابك غير مُفعَّل بعد. فعِّل اشتراكك لتجربة البوت.',
            ], 403);
        }

        $account = WhatsappAccount::query()->first();

        if ($account === null) {
            return response()->json([
                'message' => 'لا يوجد بوت مهيّأ بعد. أكمل ربط رقم واتساب أولاً.',
            ], 409);
        }

        // Per-tenant daily playground cap (§12, §14): the playground bypasses
        // AiReplyService (so it records no usage), which also bypasses the
        // production daily cap. Guard it independently so live testing can't
        // drain the platform key.
        $tenantId = (int) $request->user()?->tenant_id;
        $cap = (int) config('services.gemini.playground_daily_cap', 200);
        $cacheKey = "playground:{$tenantId}:".now()->toDateString();

        if ($cap > 0 && (int) Cache::get($cacheKey, 0) >= $cap) {
            return response()->json([
                'message' => 'لقد بلغت حدّ تجارب المختبر لليوم. عاود المحاولة غداً.',
            ], 429);
        }

        $message = trim((string) $request->validated('message'));

        // System instruction = trusted persona + injected knowledge, hardened
        // against injection (§12). It is built but NEVER sent to the client.
        $systemInstruction = $this->promptBuilder->buildSystemInstruction($account);

        // The untrusted message is the ONLY turn, framed strictly as `user`.
        $turns = [['role' => 'user', 'text' => $message]];

        // Account temperature is an int 0..100; providers want a float clamped to
        // a sane 0.0..2.0 range (§12 — no out-of-range value).
        $temperature = max(0.0, min(2.0, (int) $account->temperature / 100));

        // Same provider/model/key the webhook would use for this account (§12).
        $resolved = $this->resolver->resolve($account);

        try {
            $result = $resolved['provider']->generate(
                systemInstruction: $systemInstruction,
                turns: $turns,
                temperature: $temperature,
                apiKey: $resolved['apiKey'],
                model: $resolved['model'],
            );
        } catch (Throwable $e) {
            // Explicit failure handling (§3): report, then a polite JSON error.
            // The exception carries no key (the client strips it), and we do not
            // echo its message to avoid surfacing internals (§13).
            report($e);

            return response()->json([
                'message' => 'تعذّر الحصول على رد من النموذج الآن. حاول مرة أخرى بعد قليل.',
            ], 502);
        }

        // Count only this successful try toward the daily cap (early returns
        // above — over-cap / failure — are not counted).
        if ($cap > 0) {
            Cache::add($cacheKey, 0, now()->endOfDay());
            Cache::increment($cacheKey);
        }

        return response()->json([
            'reply' => $result['text'],
            'tokens_in' => $result['tokensIn'],
            'tokens_out' => $result['tokensOut'],
        ]);
    }
}
