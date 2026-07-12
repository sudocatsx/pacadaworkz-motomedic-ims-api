<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class RefundTransactionRequest extends FormRequest
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
            'refund_items' => 'required_if:refund_type,partial|array',
            'refund_items.*.sales_item_id' => 'required_with:refund_items|integer|exists:sales_items,id',
            'refund_items.*.quantity' => 'required_with:refund_items|integer|min:1',
            'refund_items.*.reason' => 'nullable|string',
            'refund_type' => 'nullable|string|in:full,partial',
            'reason' => 'required|string|max:1000',
            'authorizer_id' => 'required|integer|exists:users,id',
            'pin' => 'required|digits:6',
        ];
    }
}
