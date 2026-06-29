<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateBotSettingsRequest;
use App\Models\WhatsappAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BotSettingsController extends Controller
{
    /**
     * Show the bot configuration for the active tenant's account (TenantScope).
     */
    public function edit(): View
    {
        $account = WhatsappAccount::query()->firstOrFail();

        return view('dashboard.bot.edit', [
            'account' => $account,
        ]);
    }

    /**
     * Save system_prompt + temperature on the tenant's account. The model is
     * fixed to gemini-2.5-flash-lite and never switched from the panel (§12).
     */
    public function update(UpdateBotSettingsRequest $request): RedirectResponse
    {
        $account = WhatsappAccount::query()->firstOrFail();

        $account->fill($request->safe()->only(['system_prompt', 'temperature']))->save();

        return redirect()
            ->route('bot.edit')
            ->with('status', 'bot-updated');
    }
}
