<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an OWNER's MANUAL template entry (Phase 7c, §13) — the fallback for
 * when Meta sync is unavailable but the owner has already created and approved the
 * template in Meta Business Manager.
 *
 * Authorization is the route's `auth` + `tenant` + `owner` gate; this request
 * governs input shape only. The owner supplies only descriptive fields
 * (name/language/category/body); `status`/`variable_count`/`last_synced_at` are
 * NEVER taken from input — they are server-controlled (set to the conservative
 * defaults / derived from the body in the controller), so a manual entry can never
 * mark itself approved (§13). Only a Meta sync can move a template to approved.
 */
class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The `owner` middleware already gates this route to the tenant owner.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Meta template names: lowercase letters, digits, underscores.
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:15', 'regex:/^[a-zA-Z_]+$/'],
            'category' => ['required', Rule::in(['marketing', 'utility', 'authentication'])],
            'body_text' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم القالب مطلوب.',
            'name.regex' => 'اسم القالب يقبل حروفاً إنجليزية صغيرة وأرقاماً وشرطة سفلية فقط.',
            'language.required' => 'رمز اللغة مطلوب (مثل ar أو en_US).',
            'language.regex' => 'رمز اللغة غير صالح (مثل ar أو en_US).',
            'category.required' => 'فئة القالب مطلوبة.',
            'category.in' => 'الفئة يجب أن تكون utility أو marketing أو authentication.',
        ];
    }

    /**
     * The known statuses for a manually-added template — always the conservative
     * default; never request-controlled (§13).
     */
    public function defaultStatus(): string
    {
        return MessageTemplate::STATUS_UNKNOWN;
    }
}
