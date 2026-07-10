<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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

          // $isupdate = $this->method == 'PUT' || $this->method == 'PATCH';

        


        return [
            'name' => [
                'required',
                'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($this->route('id'))
                    ->whereNull('deleted_at'),
            ],
            'description' => 'sometimes|nullable',
        ];
    }
}
