<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the body an OWNER sends as a bulk campaign (Phase 6d, §13).
 *
 * Authorization is the route's `auth` + `tenant` + `owner` gate; this request
 * governs input shape only. The recipient set is derived server-side from the
 * eligible (opted-in, non-opted-out) conversations — it is NEVER taken from
 * request input, so a caller cannot smuggle in arbitrary numbers (opt-in, §11).
 */
class StoreBulkCampaignRequest extends FormRequest
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
            // A WhatsApp text body is capped at 4096 chars by Meta; bound it well
            // under that. min:1 prevents an empty broadcast.
            'body' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'نص الرسالة مطلوب.',
            'body.min' => 'نص الرسالة لا يمكن أن يكون فارغاً.',
            'body.max' => 'نص الرسالة طويل جداً (الحد 4000 حرف).',
        ];
    }
}
