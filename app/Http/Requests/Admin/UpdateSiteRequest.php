<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

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
     * Drop fully-empty repeater rows BEFORE validation so the admin can leave
     * trailing blank rows in the form without tripping the count limit or the
     * required_with rules. A feature row counts only when it has a title; an FAQ
     * row only when it has a question. After this filter, an all-blank list
     * becomes `[]`, which the controller stores as null → the landing page falls
     * back to its hard-coded default (§3). The list is re-indexed so the
     * `features.*` / `faq.*` rules and `max` count apply to real rows only.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'features' => $this->compactRows('features', 'title'),
            'faq' => $this->compactRows('faq', 'question'),
        ]);
    }

    /**
     * Keep only the rows of $field whose $keyField is a non-blank string, with
     * each kept row trimmed to string values. Non-array input yields an empty
     * list (never an error here — the rules report a bad shape instead).
     *
     * @return list<array<string, string>>
     */
    private function compactRows(string $field, string $keyField): array
    {
        $rows = $this->input($field);

        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = $row[$keyField] ?? null;
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            // Coerce to string + trim so only scalar text survives; arrays/objects
            // smuggled into a row field are dropped, not validated as strings.
            $clean[] = Arr::map(
                Arr::only($row, [$keyField, $keyField === 'title' ? 'description' : 'answer']),
                fn ($value) => is_string($value) ? trim($value) : '',
            );
        }

        return $clean;
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

            // Features grid (#features) — JSON list of {title, description}. Empty
            // rows are dropped in prepareForValidation; an all-blank list becomes
            // [] → stored as null → landing default (§3). Capped at 8 rows.
            'features' => ['nullable', 'array', 'max:8'],
            'features.*.title' => ['required_with:features.*.description', 'string', 'max:80'],
            'features.*.description' => ['nullable', 'string', 'max:240'],

            // FAQ accordion (#faq) — JSON list of {question, answer}. Same rules,
            // capped at 12 rows.
            'faq' => ['nullable', 'array', 'max:12'],
            'faq.*.question' => ['required_with:faq.*.answer', 'string', 'max:140'],
            'faq.*.answer' => ['nullable', 'string', 'max:600'],
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
            'features.max' => 'لا يمكن إضافة أكثر من 8 مزايا.',
            'features.*.title.required_with' => 'عنوان الميزة مطلوب عند كتابة وصف لها.',
            'features.*.title.max' => 'عنوان الميزة يجب ألا يتجاوز 80 حرفاً.',
            'features.*.description.max' => 'وصف الميزة يجب ألا يتجاوز 240 حرفاً.',
            'faq.max' => 'لا يمكن إضافة أكثر من 12 سؤالاً.',
            'faq.*.question.required_with' => 'نص السؤال مطلوب عند كتابة إجابة له.',
            'faq.*.question.max' => 'السؤال يجب ألا يتجاوز 140 حرفاً.',
            'faq.*.answer.max' => 'الإجابة يجب ألا يتجاوز 600 حرفاً.',
        ];
    }
}
