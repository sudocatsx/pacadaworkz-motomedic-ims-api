<?php

namespace App\Http\Requests\Tutorial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTutorialProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_version' => ['required', 'integer'],
            'status' => ['required', Rule::in(['in_progress', 'completed', 'skipped'])],
            'current_step' => ['required', 'integer', 'min:0'],
            'restart' => ['sometimes', 'boolean'],
        ];
    }
}
