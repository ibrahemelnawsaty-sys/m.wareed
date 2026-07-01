<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SendTestMessageRequest;
use App\Http\Requests\Dashboard\UpdateWhatsappAccountRequest;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class WhatsappAccountController extends Controller
{
    /**
     * Show the WhatsApp connection page for the active tenant's account.
     *
     * The query is filtered automatically by TenantScope (BindTenant has bound
     * the tenant), so firstOrFail() can only ever return *this* tenant's row.
     */
    public function edit(): View
    {
        $account = WhatsappAccount::query()->firstOrFail();

        return view('dashboard.whatsapp.edit', [
            'account' => $account,
            // Read-only integration values surfaced for copy (§10).
            'callbackUrl' => rtrim((string) config('app.url'), '/').'/api/whatsapp/webhook',
            'verifyToken' => (string) config('services.whatsapp.verify_token'),
            'hasAccessToken' => filled($account->access_token),
            // Per-tenant Meta App Secret — never echoed back, presence only (§13).
            'hasAppSecret' => filled($account->app_secret),
            // The wizard can only verify/test once BOTH the number id and the
            // token are saved; surface readiness so the UI can guide (§10).
            'connectionReady' => filled($account->access_token) && filled($account->phone_number_id),
        ]);
    }

    /**
     * Show the step-by-step onboarding guide for a fully non-technical owner
     * (§5, §10). Read-only — reuses the same callback URL / verify token
     * values as `edit()` so the copy-paste values always match what the
     * connection form and Meta webhook config expect.
     */
    public function guide(): View
    {
        return view('dashboard.whatsapp.guide', [
            'callbackUrl' => rtrim((string) config('app.url'), '/').'/api/whatsapp/webhook',
            'verifyToken' => (string) config('services.whatsapp.verify_token'),
        ]);
    }

    /**
     * Persist the WhatsApp link. The access token and per-tenant Meta App
     * Secret are both cast `encrypted`, so they are stored as ciphertext
     * (§13). Empty token/secret fields leave the saved values untouched
     * (non-destructive sync, §3).
     */
    public function update(UpdateWhatsappAccountRequest $request): RedirectResponse
    {
        $account = WhatsappAccount::query()->firstOrFail();

        $data = $request->safe()->only(['display_name', 'phone_number_id', 'waba_id']);

        $token = $request->validated('access_token');
        if (filled($token)) {
            $data['access_token'] = $token;
        }

        $secret = $request->validated('app_secret');
        if (filled($secret)) {
            $data['app_secret'] = $secret;
        }

        $account->fill($data)->save();

        return redirect()
            ->route('whatsapp.edit')
            ->with('status', 'whatsapp-updated');
    }

    /**
     * Probe Meta to confirm the saved phone_number_id + token are valid and
     * flash the live number status for the wizard (§10). The account is resolved
     * from the active tenant only (TenantScope). Failures are reported and
     * surfaced as a gentle error — never a raw 500, never the token (§3, §13).
     */
    public function verify(WhatsAppClient $client): RedirectResponse
    {
        $account = WhatsappAccount::query()->firstOrFail();

        if (! filled($account->access_token) || ! filled($account->phone_number_id)) {
            return back()->withErrors([
                'connection' => 'أكمل بيانات الربط أولاً (رقم الهاتف والتوكن).',
            ]);
        }

        try {
            $info = $client->verifyConnection($account);
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors([
                'connection' => 'تعذّر التحقق من الاتصال بميتا. تأكّد من رقم الهاتف والتوكن.',
            ]);
        }

        return back()->with('connection_status', $info);
    }

    /**
     * Send a pre-approved `hello_world` template to the owner's own number so
     * they can confirm the link end-to-end before going live (§10). A template
     * is used deliberately: it works with NO open 24h window (§11). Failures are
     * reported and surfaced gently — never a raw 500, never the token (§3, §13).
     */
    public function test(SendTestMessageRequest $request, WhatsAppClient $client): RedirectResponse
    {
        $account = WhatsappAccount::query()->firstOrFail();

        if (! filled($account->access_token) || ! filled($account->phone_number_id)) {
            return back()->withErrors([
                'test' => 'أكمل بيانات الربط أولاً (رقم الهاتف والتوكن).',
            ]);
        }

        $to = (string) $request->validated('to');

        try {
            $client->sendTemplate($account, $to, 'hello_world', 'en_US');
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors([
                'test' => 'تعذّر إرسال الرسالة التجريبية. تحقّق من الاتصال والرقم.',
            ]);
        }

        return back()->with('status', 'whatsapp-test-sent');
    }
}
