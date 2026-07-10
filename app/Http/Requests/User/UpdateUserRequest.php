<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'role_id' => 'sometimes|integer|exists:roles,id',
            'name' => 'sometimes|string|min:1|max:50',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($this->route('id'))
                    ->withoutTrashed(),
            ],
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'is_active' => 'sometimes|boolean',

            'id' => 'prohibited',
            'password' => 'prohibited',
            'deleted_at' => 'prohibited',
            'created_at' => 'prohibited',
            'updated_at' => 'prohibited',
            // 'last_login' => 'prohibited', //disabled ko muna baka magamit itong endpoint na ito pang recycle
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            // 1. Kunin lahat ng keys na pinasa ng user (e.g., "name", "vance")
            $inputData = $validator->getData();
            $inputKeys = array_keys($inputData);

            // 2. Kunin lahat ng keys na nasa rules natin
            $allowedKeys = array_keys($this->rules());

            // 3. Hanapin ang difference (Mga keys na nasa Input pero wala sa Rules)
            $unknownKeys = array_diff($inputKeys, $allowedKeys);

            // 4. Kapag may nahanap na random keys, mag-add ng error
            foreach ($unknownKeys as $unknownKey) {
                $validator->errors()->add(
                    $unknownKey,
                    "The field '{$unknownKey}' is not allowed in this request."
                );
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors(),
        ], 422));
    }
}
