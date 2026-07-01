<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an OWNER's bulk campaign (Phase 6d/7c, §13).
 *
 * Authorization is the route's `auth` + `tenant` + `owner` gate; this request
 * governs input shape only. The recipient set is derived server-side from the
 * eligible (opted-in, non-opted-out) conversations — it is NEVER taken from
 * request input, so a caller cannot smuggle in arbitrary numbers (opt-in, §11).
 *
 * Two modes:
 *  - Free-form: no template ⇒ `body` is required (sent only inside the 24h window).
 *  - Template:  a Meta-approved template ⇒ `body` is optional (the template's own
 *    copy is the message), and the contact is reachable OUTSIDE the window (§11).
 *
 * `message_template_id` is constrained with a TENANT-SCOPED exists rule so a
 * caller can never reference another tenant's template; the controller additionally
 * verifies the template is APPROVED and that the variable count matches (§13).
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
            // Tenant-scoped: the id must belong to a MessageTemplate THIS tenant
            // owns. TenantScope filters the exists query, so a foreign id fails
            // validation (defence in depth on top of the controller's checks, §1).
            'message_template_id' => [
                'nullable',
                'integer',
                Rule::exists(MessageTemplate::class, 'id'),
            ],

            // Body is required only for the free-form path (no template). With a
            // template the template's own copy is the message, so body is optional.
            // A WhatsApp text body is capped at 4096 by Meta; bound it well under.
            'body' => ['nullable', 'required_without:message_template_id', 'string', 'min:1', 'max:4000'],

            // The positional variables ({{1}}, {{2}}…) for the template body. The
            // controller verifies the count equals the template's variable_count.
            'template_variables' => ['nullable', 'array', 'max:20'],
            'template_variables.*' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required_without' => 'نص الرسالة مطلوب عند عدم اختيار قالب.',
            'body.min' => 'نص الرسالة لا يمكن أن يكون فارغاً.',
            'body.max' => 'نص الرسالة طويل جداً (الحد 4000 حرف).',
            'message_template_id.exists' => 'القالب المختار غير موجود.',
            'template_variables.*.required' => 'قيمة المتغيّر مطلوبة.',
        ];
    }
}
