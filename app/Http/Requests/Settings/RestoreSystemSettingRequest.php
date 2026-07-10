<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RestoreSystemSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Middleware handles role authorization
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'backup_file' => [
                'required',
                'file',
                'mimes:sql,gz,tar,dump,bin', // Extension check
                'max:' . (int) config('backup.max_upload_kb', 512000), // Configurable limit (default 500MB)
                function ($attribute, $value, $fail) {
                    // Open file to read magic bytes/header
                    $handle = fopen($value->getRealPath(), 'rb');
                    $header = fread($handle, 5); // Read first 5 bytes
                    $isCustomFormat = ($header === 'PGDMP'); // Magic bytes for Postgres custom dump

                    // Reset to check for SQL text format
                    rewind($handle);
                    $textHeader = fread($handle, 100); // Read first 100 chars
                    $isSqlText = str_contains($textHeader, 'PostgreSQL database dump') ||
                                 str_contains($textHeader, 'SET statement_timeout = 0');

                    fclose($handle);

                    if (!$isCustomFormat && !$isSqlText) {
                        $fail('The uploaded file does not appear to be a valid PostgreSQL backup.');
                    }
                },
            ],
        ];
    }
}
