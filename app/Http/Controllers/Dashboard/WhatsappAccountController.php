<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateWhatsappAccountRequest;
use App\Models\WhatsappAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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
            // Read-only integration values surfaced for copy (§10). The App
            // Secret is intentionally NOT exposed — it lives only in .env (§13).
            'callbackUrl' => rtrim((string) config('app.url'), '/').'/api/whatsapp/webhook',
            'verifyToken' => (string) config('services.whatsapp.verify_token'),
            'hasAccessToken' => filled($account->access_token),
        ]);
    }

    /**
     * Persist the WhatsApp link. The access token is cast `encrypted`, so it is
     * stored as ciphertext (§13). An empty token field leaves the saved token
     * untouched (non-destructive sync, §3).
     */
    public function update(UpdateWhatsappAccountRequest $request): RedirectResponse
    {
        $account = WhatsappAccount::query()->firstOrFail();

        $data = $request->safe()->only(['display_name', 'phone_number_id', 'waba_id']);

        $token = $request->validated('access_token');
        if (filled($token)) {
            $data['access_token'] = $token;
        }

        $account->fill($data)->save();

        return redirect()
            ->route('whatsapp.edit')
            ->with('status', 'whatsapp-updated');
    }
}
