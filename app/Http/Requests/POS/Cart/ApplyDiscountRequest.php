<?php

namespace App\Http\Requests\POS\Cart;

use App\Services\PosService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ApplyDiscountRequest extends FormRequest
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
            'discount' => [
                'required',
                'numeric',
                'min:0',
                // Rule::when($this->input('discount_type') === 'percentage', ['max:100']),
            ],
            'discount_type' => ['required', 'string', 'in:fixed,percentage'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $posService = app(PosService::class);
            $userId = Auth::id();

            $cartDetails = $posService->getCart($userId);
            $subtotal = $cartDetails['summary']['subtotal'];
            $requestedDiscount = $this->input('discount');
            $discountType = $this->input('discount_type');

            $actualDiscountAmount = 0;
            if ($discountType === 'percentage') {
                $actualDiscountAmount = $subtotal * ($requestedDiscount / 100);
            } else {
                $actualDiscountAmount = $requestedDiscount;
            }

            if ($actualDiscountAmount > $subtotal) {
                $validator->errors()->add('discount', 'The discount cannot be greater than the cart subtotal.');
            }
        });
    }
}
