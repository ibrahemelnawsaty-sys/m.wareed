<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Super-admin management of the platform AI keys (gemini/openai/deepseek), §13.
 *
 * The keys are platform secrets. This controller is the ONLY admin surface that
 * may write them, and it never reads a real value into the view: edit() passes
 * presence flags (has*) only, so the page can show "stored ✓ / not set" without
 * the plaintext ever reaching the browser (§13). Blank input on update() means
 * "keep the stored key" — a non-destructive sync, never a clear (§3).
 */
class SettingsController extends Controller
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function edit(): View
    {
        // Presence-only. We deliberately pass booleans, NOT the decrypted keys,
        // so no real value can leak into the rendered HTML (§13).
        return view('admin.settings.edit', [
            'hasGemini' => filled($this->settings->get('gemini_api_key')),
            'hasOpenai' => filled($this->settings->get('openai_api_key')),
            'hasDeepseek' => filled($this->settings->get('deepseek_api_key')),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        // For each provider: a non-blank field is written; a blank/omitted field
        // is left exactly as stored (§3 — no destructive clear-on-empty). The
        // values are never logged nor echoed (§13).
        foreach (['gemini', 'openai', 'deepseek'] as $provider) {
            $value = $request->validated("{$provider}_api_key");

            if (filled($value)) {
                $this->settings->set("{$provider}_api_key", (string) $value);
            }
        }

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'settings-updated');
    }
}
