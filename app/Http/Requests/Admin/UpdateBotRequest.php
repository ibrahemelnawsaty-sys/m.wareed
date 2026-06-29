<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the AI provider/model an admin sets on a customer's WhatsApp
 * account (§12, §13).
 *
 * Only gemini is wired today; openai/deepseek are accepted as stored selections
 * (they activate later when their API key is added). The allow-list here is the
 * guard: an admin cannot point a customer's bot at an unsupported provider.
 */
class UpdateBotRequest extends FormRequest
{
    /**
     * Providers the platform recognises. gemini is live; the others are stored
     * but inert until their key is provisioned (surfaced as a note in the UI).
     *
     * @var list<string>
     */
    public const PROVIDERS = ['gemini', 'openai', 'deepseek'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ai_provider' => ['required', 'string', Rule::in(self::PROVIDERS)],
            // Free-form model id (e.g. gemini-2.5-flash-lite). Bounded length;
            // no secret is ever accepted or echoed through this request (§13).
            'ai_model' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ai_provider.required' => 'يجب اختيار مزوّد الذكاء الاصطناعي.',
            'ai_provider.in' => 'مزوّد الذكاء الاصطناعي المختار غير مدعوم.',
            'ai_model.required' => 'يجب تحديد اسم النموذج.',
            'ai_model.max' => 'اسم النموذج طويل جداً.',
        ];
    }
}
