<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportDateRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'])],
            'start_date' => ['nullable', 'required_if:period,custom', 'required_with:end_date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end_date' => ['nullable', 'required_if:period,custom', 'required_with:start_date', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ];
    }
}
