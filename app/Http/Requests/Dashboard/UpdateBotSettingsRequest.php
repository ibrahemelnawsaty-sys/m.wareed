<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBotSettingsRequest extends FormRequest
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
            'system_prompt' => ['required', 'string', 'max:20000'],
            // Integer 0..100 — no float for model knobs (§3). Rendered as a slider.
            'temperature' => ['required', 'integer', 'between:0,100'],
        ];
    }
}
