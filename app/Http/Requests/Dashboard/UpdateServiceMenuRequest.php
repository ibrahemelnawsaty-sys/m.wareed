<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the owner's service-menu form (Phase 7b). Authorization is the
 * `owner` middleware on the route (an agent gets 403 before reaching here), so
 * authorize() simply returns true.
 *
 * Every Meta List Message limit is enforced HERE, server-side, before a row is
 * stored — a payload that violates a limit would be REJECTED by Meta at send
 * time, banning nothing but breaking the customer's experience (§11):
 *   header ≤60 · body ≤1024 · button ≤20 · footer ≤60 · ≤10 rows ·
 *   row title ≤24 · row description ≤72.
 * `row_key` is NOT accepted from input — it is generated server-side from the
 * row position in the controller, so the list-reply id space stays trusted.
 */
class UpdateServiceMenuRequest extends FormRequest
{
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
            'enabled' => ['nullable', 'boolean'],
            'trigger_on_welcome' => ['nullable', 'boolean'],

            // Meta text-block limits (§11).
            'header' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:1024'],
            'button_label' => ['required', 'string', 'max:20'],
            'footer' => ['nullable', 'string', 'max:60'],

            // Up to 10 rows total (Meta hard cap). An empty rows set is allowed:
            // it means "no menu" (the controller then leaves the menu without
            // rows / disabled-effective). Each row is bounded individually.
            'rows' => ['present', 'array', 'max:10'],
            'rows.*.title' => ['required', 'string', 'max:24'],
            'rows.*.description' => ['nullable', 'string', 'max:72'],
            'rows.*.action_type' => ['required', Rule::in(['reply', 'handoff'])],
            // reply_text is required only for a 'reply' row, and is ignored
            // (cleared) for a 'handoff' row in the controller.
            'rows.*.reply_text' => ['nullable', 'required_if:rows.*.action_type,reply', 'string', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'نص القائمة مطلوب.',
            'body.max' => 'نص القائمة يتجاوز 1024 حرفاً.',
            'button_label.required' => 'نص الزر مطلوب.',
            'button_label.max' => 'نص الزر يتجاوز 20 حرفاً.',
            'header.max' => 'الرأس يتجاوز 60 حرفاً.',
            'footer.max' => 'التذييل يتجاوز 60 حرفاً.',
            'rows.max' => 'لا يمكن إضافة أكثر من 10 صفوف.',
            'rows.*.title.required' => 'عنوان الصف مطلوب.',
            'rows.*.title.max' => 'عنوان الصف يتجاوز 24 حرفاً.',
            'rows.*.description.max' => 'وصف الصف يتجاوز 72 حرفاً.',
            'rows.*.action_type.in' => 'نوع الإجراء غير صالح.',
            'rows.*.reply_text.required_if' => 'نص الرد مطلوب عند اختيار «ردّ جاهز».',
        ];
    }
}
