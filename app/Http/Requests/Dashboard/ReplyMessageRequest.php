<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an agent's free-form reply from the inbox. Authorization (whose
 * conversation it is, window state) is enforced in the controller against the
 * tenant-scoped, route-bound conversation — not here.
 */
class ReplyMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:4096'],
        ];
    }
}
