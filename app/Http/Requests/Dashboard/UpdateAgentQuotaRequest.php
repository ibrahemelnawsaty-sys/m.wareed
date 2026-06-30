<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a per-agent conversation target an OWNER sets (Phase 6c, §13).
 *
 * Authorization is the route's `auth` + `tenant` + `owner` gate, and the target
 * {user} is route-model bound through the TenantScope (a foreign user 404s, §1).
 * A blank value clears the override so the agent inherits the tenant default.
 * The value is applied via User::setConversationQuota (trusted forceFill+save),
 * never mass-assigned onto `conversation_quota`.
 */
class UpdateAgentQuotaRequest extends FormRequest
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
            // Blank ⇒ inherit the tenant default (NULL). Otherwise a whole count 1..1000.
            'conversation_quota' => ['nullable', 'integer', 'between:1,1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conversation_quota.integer' => 'التارجت يجب أن يكون عدداً صحيحاً.',
            'conversation_quota.between' => 'التارجت يجب أن يكون بين 1 و1000.',
        ];
    }
}
