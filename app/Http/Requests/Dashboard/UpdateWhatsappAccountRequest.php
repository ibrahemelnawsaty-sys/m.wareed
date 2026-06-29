<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\WhatsappAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsappAccountRequest extends FormRequest
{
    /**
     * Authorization is handled by the route's `auth` + `tenant` middleware and
     * the TenantScope (the account is resolved from the active tenant only).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Current tenant's account (resolved via TenantScope) — ignore it so
        // re-saving the same number is fine, but block claiming ANOTHER
        // tenant's phone_number_id, which would hijack webhook routing and
        // otherwise surface as a raw 500 on the unique index (§1, ADR-01, §3).
        $accountId = WhatsappAccount::query()->value('id');

        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'phone_number_id' => ['nullable', 'string', 'max:255', Rule::unique('whatsapp_accounts', 'phone_number_id')->ignore($accountId)],
            'waba_id' => ['nullable', 'string', 'max:255'],
            // Optional on update: an empty token means "keep the saved one"
            // (non-destructive sync, §3). Never echoed back to the view (§13).
            'access_token' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
