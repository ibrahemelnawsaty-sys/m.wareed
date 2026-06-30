<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the conversation distribution settings an OWNER sets (Phase 6c, §13).
 *
 * Authorization is the route's `auth` + `tenant` + `owner` gate; this request
 * only governs input shape. The values are applied via Tenant::setDistribution
 * (trusted save()), never mass-assigned onto `distribution_mode` /
 * `agent_conversation_quota` — an agent can never reach this route, and the mode
 * cannot be smuggled through any other input.
 */
class UpdateDistributionRequest extends FormRequest
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
            'distribution_mode' => ['required', 'string', Rule::in(['claim', 'balanced'])],
            // Whole conversations only, bounded 1..1000. Integer (a count) (§3).
            'agent_conversation_quota' => ['required', 'integer', 'between:1,1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'distribution_mode.required' => 'يجب اختيار وضع التوزيع.',
            'distribution_mode.in' => 'وضع التوزيع غير صالح.',
            'agent_conversation_quota.required' => 'يجب تحديد السقف الافتراضي للموظف.',
            'agent_conversation_quota.integer' => 'السقف يجب أن يكون عدداً صحيحاً.',
            'agent_conversation_quota.between' => 'السقف يجب أن يكون بين 1 و1000.',
        ];
    }
}
