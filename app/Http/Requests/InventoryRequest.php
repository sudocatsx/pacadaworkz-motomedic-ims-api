<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryRequest extends FormRequest
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
            'product_id' => 'required|exists:products,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity' => 'required|integer|min:0',
            'location' => 'nullable|string|max:255',
            'last_stock_in' => 'nullable|date',
        ];

        if ($this->isMethod('patch')) {
            $rules = array_map(function ($rule) {
                return 'sometimes|' . $rule;
            }, $rules);
        }

        return $rules;
    }
}
