<?php

namespace App\Http\Requests\Settings\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => 'required|string|current_password',
            'new_password' => 'required|string|min:8|different:current_password',
            'confirm_new_password' => 'required|string|same:new_password',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $allowedFields = array_keys($this->rules());
            $incomingFields = array_keys($this->all());

            $extraFields = array_diff($incomingFields, $allowedFields);

            foreach ($extraFields as $field) {
                $validator->errors()->add($field, "The {$field} field is not allowed.");
            }
        });
    }
}
