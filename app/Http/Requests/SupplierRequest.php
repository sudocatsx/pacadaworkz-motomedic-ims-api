<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
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
        $rules = [
            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('suppliers', 'name')
                    ->ignore($this->route('id'))
                    ->whereNull('deleted_at'),
            ],
            'contact_person' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('suppliers', 'email')
                    ->ignore($this->route('id'))
                    ->whereNull('deleted_at'),
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ];

        if ($this->isMethod('patch')) {
            $rules = collect($rules)->map(function ($rule) {
                return is_array($rule) ? ['sometimes', ...$rule] : 'sometimes|' . $rule;
            })->all();
        }

        return $rules;
    }
}
