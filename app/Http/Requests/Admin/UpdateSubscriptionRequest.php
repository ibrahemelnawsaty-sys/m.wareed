<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the subscription-duration input an admin sets on a customer (§13).
 *
 * Authorization is the route's `auth` + `admin` gate; this request only governs
 * the shape of the input. The value is applied via Tenant::setSubscriptionMonths
 * (trusted save()), never mass-assigned onto `subscription_ends_at`.
 */
class UpdateSubscriptionRequest extends FormRequest
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
        return [
            // Whole months only, bounded 1..60 (5 years). Integer, never float
            // — duration is a count, not money, but we keep the no-float spirit.
            'months' => ['required', 'integer', 'between:1,60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'months.required' => 'يجب تحديد مدة الاشتراك بالأشهر.',
            'months.integer' => 'مدة الاشتراك يجب أن تكون عدداً صحيحاً من الأشهر.',
            'months.between' => 'مدة الاشتراك يجب أن تكون بين شهر واحد و60 شهراً.',
        ];
    }
}
