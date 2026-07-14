<?php

namespace App\Http\Requests\Stocks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMovementRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'movement_type' => ['required', 'string', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
