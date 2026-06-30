<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the seat limit (max_users) an ADMIN sets on a customer (§13).
 *
 * Authorization is the route's `auth` + `admin` gate; this request only governs
 * input shape. The value is applied via Tenant::setMaxUsers (trusted save()),
 * never mass-assigned onto `max_users` — a tenant owner can never reach this.
 */
class UpdateSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The `admin` middleware already gates every /admin/* route.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Whole seats only, bounded 1..100. Integer (a count, not money) (§3).
            'max_users' => ['required', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_users.required' => 'يجب تحديد عدد المقاعد.',
            'max_users.integer' => 'عدد المقاعد يجب أن يكون عدداً صحيحاً.',
            'max_users.between' => 'عدد المقاعد يجب أن يكون بين 1 و100.',
        ];
    }
}
