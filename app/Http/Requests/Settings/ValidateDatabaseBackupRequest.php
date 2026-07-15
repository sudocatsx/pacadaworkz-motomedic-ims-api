<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ValidateDatabaseBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'backup_file' => [
                'required',
                'file',
                'max:'.(int) config('backup.max_upload_kb'),
                function ($attribute, $value, $fail): void {
                    if (strtolower($value->getClientOriginalExtension()) !== 'dump') {
                        $fail('Only PostgreSQL .dump backup files are supported.');
                    }
                },
            ],
        ];
    }
}
