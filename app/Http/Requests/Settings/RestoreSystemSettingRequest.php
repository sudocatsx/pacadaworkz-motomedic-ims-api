<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RestoreSystemSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Middleware handles role authorization
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255', 'regex:/^(backup|pre-restore)-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.dump$/'],
            'password' => ['required', 'string', 'current_password:api'],
            'confirmation' => ['required', 'string', 'in:RESTORE DATABASE'],
        ];
    }
}
