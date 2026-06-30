<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the public site content an admin edits on the site-content page
 * (Phase 4h).
 *
 * Authorization is the route's `auth` + `admin` gate; this request only governs
 * the shape of the input. Every field is `nullable`: a blank field is valid and
 * means "revert to the hard-coded default" (the controller writes null, §3).
 *
 * These values are PUBLIC copy, not secrets — but they are still untrusted input
 * printed back into HTML, so they are length-bounded here and escaped with
 * `{{ }}` everywhere they are rendered (§13, no HTML injection). SEO fields are
 * tightly bounded to the lengths Google actually uses (title ≤ 60, description
 * ≤ 160) so the admin cannot save copy that would be truncated in search results.
 */
class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The `admin` middleware already gates every /admin/* route to genuine
        // super-admins; this request adds no further per-record authorization.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Brand & contact.
            'brand_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string', 'max:500'],

            // Hero section.
            'hero_eyebrow' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:1000'],
            'hero_cta' => ['nullable', 'string', 'max:120'],

            // Optional top announcement banner.
            'announcement' => ['nullable', 'string', 'max:500'],

            // SEO — bounded to Google's usable lengths.
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'seo_title.max' => 'عنوان SEO يجب ألا يتجاوز 60 حرفاً ليظهر كاملاً في نتائج البحث.',
            'seo_description.max' => 'وصف SEO يجب ألا يتجاوز 160 حرفاً ليظهر كاملاً في نتائج البحث.',
        ];
    }
}
