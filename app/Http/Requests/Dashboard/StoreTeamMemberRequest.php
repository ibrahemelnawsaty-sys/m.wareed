<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the shape of a new team member (agent) an OWNER adds (§13).
 *
 * Authorization is the route's `auth` + `tenant` + `owner` gate; this request
 * only governs input shape. The created user's role is forced to 'agent' and
 * `tenant_id` is auto-filled from TenantContext in the controller — neither is
 * accepted from this request, so an owner cannot mint another owner/admin or
 * plant a user in a foreign tenant.
 */
class StoreTeamMemberRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // unique across all users (email is the login identity, platform-wide).
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'يجب إدخال اسم الموظف.',
            'name.max' => 'اسم الموظف طويل جداً.',
            'email.required' => 'يجب إدخال البريد الإلكتروني.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'password.required' => 'يجب إدخال كلمة المرور.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ];
    }
}
