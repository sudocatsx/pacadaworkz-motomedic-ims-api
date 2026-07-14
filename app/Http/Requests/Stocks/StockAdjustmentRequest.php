<?php

namespace App\Http\Requests\Stocks;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
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
            'counted_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|in:physical_count,damaged,found_stock,data_correction',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
