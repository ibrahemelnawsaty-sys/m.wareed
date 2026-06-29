<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class PlaygroundSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The message is untrusted input (§12); it is validated for size only and
     * later passed strictly as a `user` turn — never merged into the system
     * instruction.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4000'],
        ];
    }
}
