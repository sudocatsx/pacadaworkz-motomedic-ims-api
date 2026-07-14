<?php

namespace App\Http\Controllers\API;

use App\Services\CatalogImportService;
use App\Services\SpreadsheetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogImportController extends Controller
{
    public function __construct(
        private readonly CatalogImportService $catalogImportService,
        private readonly SpreadsheetService $spreadsheetService
    ) {}

    public function template(Request $request)
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['categories', 'brands', 'attributes', 'suppliers', 'products'])],
        ]);

        $type = $validated['type'] ?? null;
        $this->assertProductImportPermission($type);
        $path = $this->spreadsheetService->createXlsx($this->catalogImportService->templateSheets($type));
        $filename = ($type ?: 'catalog').'-import-template.xlsx';

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
            'type' => ['nullable', Rule::in(['categories', 'brands', 'attributes', 'suppliers', 'products'])],
        ]);
        $this->assertProductImportPermission($validated['type'] ?? null);

        $file = $validated['file'];
        $extension = strtolower($file->getClientOriginalExtension());
        $extension = $extension === 'txt' ? 'csv' : $extension;

        $summary = $this->catalogImportService->import(
            $file->getRealPath(),
            $extension,
            $validated['type'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    private function assertProductImportPermission(?string $type): void
    {
        if ($type !== 'products') {
            return;
        }

        $allowed = auth('api')->user()?->role?->permissions
            ?->contains(fn ($permission) => $permission->module === 'Products' && $permission->name === 'Import');

        abort_unless($allowed, 403, 'Forbidden');
    }
}
