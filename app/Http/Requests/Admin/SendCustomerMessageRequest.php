<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the email an admin sends to a customer (§13).
 *
 * Authorization is the route's `auth` + `admin` gate; this request only governs
 * input shape. The subject/body are escaped in the email view ({{ }}), so the
 * bounds here are about sane size, not injection (that is handled at render).
 */
class SendCustomerMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The `admin` middleware already gates every /admin/* route to genuine
        // super-admins; no further per-record authorization is needed here.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Reject CR/LF in the subject at the app layer (defence-in-depth
            // against email-header injection), not just relying on the mailer
            // to encode it (§13).
            'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'يجب كتابة موضوع الرسالة.',
            'subject.max' => 'موضوع الرسالة يجب ألا يتجاوز 200 حرف.',
            'subject.not_regex' => 'موضوع الرسالة لا يمكن أن يحتوي على أسطر جديدة.',
            'body.required' => 'يجب كتابة نص الرسالة.',
            'body.max' => 'نص الرسالة يجب ألا يتجاوز 5000 حرف.',
        ];
    }
}
