<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the platform AI keys an admin sets on the settings page (§13).
 *
 * Authorization is the route's `auth` + `admin` gate; this request only governs
 * the shape of the input. Every field is `nullable`: a blank field means "keep
 * the stored key" (non-destructive, §3), so the controller — not the validator —
 * decides whether to write. The values are secrets: they are accepted here but
 * NEVER echoed back, logged, nor surfaced in any error message (§13).
 */
class UpdateSettingsRequest extends FormRequest
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
        // nullable: an omitted/blank field is valid and means "leave the stored
        // key untouched" (§3). max:500 bounds the input without ever revealing it.
        return [
            'gemini_api_key' => ['nullable', 'string', 'max:500'],
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'deepseek_api_key' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gemini_api_key.max' => 'مفتاح Gemini طويل جداً.',
            'openai_api_key.max' => 'مفتاح OpenAI طويل جداً.',
            'deepseek_api_key.max' => 'مفتاح DeepSeek طويل جداً.',
        ];
    }
}
