<?php

namespace App\Http\Requests\Settings\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
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
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|min:1|max:50', // this is user.name hahaha xD!
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
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
