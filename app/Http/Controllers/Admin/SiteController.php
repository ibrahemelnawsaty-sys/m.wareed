<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteRequest;
use App\Services\Settings\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Super-admin management of the PUBLIC site content — the marketing landing-page
 * copy and its SEO metadata (Phase 4h).
 *
 * Unlike {@see SettingsController} (which guards platform secrets), the values
 * here are public copy, so the page DOES render the current stored value into
 * each field for editing. They remain untrusted input: every field is escaped
 * with `{{ }}` on the landing page (§13). A blank field on update() writes null,
 * which makes the landing page fall back to its hard-coded default — a deliberate
 * "reset to default", not a destructive wipe of unrelated rows (§3).
 */
class SiteController extends Controller
{
    /**
     * The editable site-content keys. Single source of truth shared by edit()
     * (to read current values) and update() (to know which keys to write).
     *
     * @var list<string>
     */
    private const FIELDS = [
        'brand_name',
        'contact_email',
        'contact_phone',
        'contact_address',
        'hero_eyebrow',
        'hero_title',
        'hero_subtitle',
        'hero_cta',
        'announcement',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    public function __construct(private readonly SiteSettings $site) {}

    public function edit(): View
    {
        // Public copy: pass the current stored value (or null) for each field so
        // the admin edits what is live. Nothing here is a secret.
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = $this->site->get($field);
        }

        return view('admin.site.edit', ['values' => $values]);
    }

    public function update(UpdateSiteRequest $request): RedirectResponse
    {
        // Write every editable field. A blank/omitted field is stored as null,
        // which reverts that field to its landing-page default (§3). Only the
        // listed keys are touched — never "delete all then insert".
        foreach (self::FIELDS as $field) {
            $value = $request->validated($field);

            $this->site->set($field, is_string($value) && $value !== '' ? $value : null);
        }

        return redirect()
            ->route('admin.site.edit')
            ->with('status', 'site-updated');
    }
}
