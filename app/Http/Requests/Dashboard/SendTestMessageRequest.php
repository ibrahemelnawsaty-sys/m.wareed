<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the destination number for the connection wizard's test send.
 * Authorization is the route's `auth`+`tenant`+`owner` middleware; the account
 * itself is resolved from the active tenant (TenantScope) in the controller.
 *
 * `to` must be a bare international number — digits only, no '+', no spaces —
 * exactly the wa_id format Meta's Cloud API expects (8..15 digits, E.164 range).
 */
class SendTestMessageRequest extends FormRequest
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
            'to' => ['required', 'string', 'regex:/^[0-9]{8,15}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.required' => 'أدخل رقم واتساب الوجهة.',
            'to.regex' => 'أدخل الرقم بصيغة دولية بأرقام فقط (8 إلى 15 رقماً) بدون + أو مسافات.',
        ];
    }
}
