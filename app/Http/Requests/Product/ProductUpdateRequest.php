<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
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
             'category_id' => 'required|exists:categories,id',
             'brand_id' => 'required|exists:brands,id',
             'sku' => [
                'required',
                'max:50',
                Rule::unique('products', 'sku')
                    ->ignore($this->route('id'))
                    ->whereNull('deleted_at'),
             ],
             'name' => 'required|max:50',
             'description' => 'sometimes|nullable',
             'unit_price' => 'required|numeric|min:0',
             'cost_price' => 'required|numeric|min:0',
             'reorder_level' => 'sometimes|integer|min:0',
             'image_url' => 'sometimes|nullable',
             'initial_stock' => 'sometimes|integer|min:0',
             'location' => 'sometimes|nullable|string',
             'attribute_value_ids' => 'sometimes|array',
             'attribute_value_ids.*' => 'integer|exists:attributes_values,id',
        ];
    }
}
