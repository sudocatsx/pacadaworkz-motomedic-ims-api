<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributesValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogImportService
{
    private const SHEET_TYPES = [
        'Categories' => 'categories',
        'Brands' => 'brands',
        'Attributes' => 'attributes',
        'Suppliers' => 'suppliers',
        'Products' => 'products',
    ];

    private const IMPORT_HEADERS = [
        'categories' => ['name', 'description'],
        'brands' => ['name', 'description'],
        'attributes' => ['name', 'description', 'values'],
        'suppliers' => ['name', 'contact_person', 'email', 'phone', 'address'],
        'products' => [
            'sku',
            'name',
            'category_name',
            'brand_name',
            'unit_price',
            'cost_price',
            'description',
            'initial_stock',
            'location',
            'reorder_level',
            'attribute_values',
            'is_active',
            'image_source_url',
        ],
    ];

    public function __construct(
        private readonly SpreadsheetService $spreadsheetService,
        private readonly ActivityLogService $activityLogService,
        private readonly ProductService $productService
    ) {}

    public function templateSheets(?string $type = null): array
    {
        $sheets = collect(self::SHEET_TYPES)
            ->mapWithKeys(fn (string $sheetType, string $sheetName) => [
                $sheetName => [self::IMPORT_HEADERS[$sheetType]],
            ])
            ->all();

        if (! $type) {
            return $sheets;
        }

        $sheetName = array_search($type, self::SHEET_TYPES, true);

        return $sheetName ? [$sheetName => $sheets[$sheetName]] : $sheets;
    }

    public function import(string $path, string $extension, ?string $type = null): array
    {
        $summary = $this->emptySummary();

        if ($extension === 'csv') {
            if (! $type || ! array_key_exists($type, $summary)) {
                throw ValidationException::withMessages([
                    'file' => ['CSV imports require a valid type.'],
                ]);
            }

            $rows = $this->spreadsheetService->readCsv($path);
            $this->validateRowsForType($type, $rows);
            $this->importType($type, $rows, $summary);
        } else {
            $sheets = $this->spreadsheetService->readXlsx($path);

            if ($type) {
                $sheetName = $this->sheetNameForType($type);
                $rows = $sheets[$sheetName] ?? (count($sheets) === 1 ? reset($sheets) : null);

                if (! $rows) {
                    throw ValidationException::withMessages([
                        'file' => ["The uploaded XLSX file does not contain a sheet for {$type} imports."],
                    ]);
                }

                $this->validateRowsForType($type, $rows);
                $this->importType($type, $rows, $summary);

                return $this->finishImport($summary);
            }

            $importableSheets = [];
            foreach (self::SHEET_TYPES as $sheetName => $sheetType) {
                if (! isset($sheets[$sheetName])) {
                    continue;
                }

                $this->validateRowsForType($sheetType, $sheets[$sheetName]);
                $importableSheets[$sheetType] = $sheets[$sheetName];
            }

            if ($importableSheets === []) {
                throw ValidationException::withMessages([
                    'file' => ['The uploaded XLSX file does not contain any importable catalog sheets.'],
                ]);
            }

            foreach ($importableSheets as $sheetType => $rows) {
                $this->importType($sheetType, $rows, $summary);
            }
        }

        return $this->finishImport($summary);
    }

    private function finishImport(array $summary): array
    {
        $this->activityLogService->log(
            module: 'Imports',
            action: 'Create',
            description: 'Imported catalog data from spreadsheet',
            userId: auth()->id()
        );

        return $summary;
    }

    private function importType(string $type, array $rows, array &$summary): void
    {
        $records = $this->recordsFromRows($rows);

        foreach ($records as $rowNumber => $record) {
            if ($this->isEmptyRecord($record)) {
                continue;
            }

            match ($type) {
                'categories' => $this->importCategory($record, $rowNumber, $summary),
                'brands' => $this->importBrand($record, $rowNumber, $summary),
                'attributes' => $this->importAttribute($record, $rowNumber, $summary),
                'suppliers' => $this->importSupplier($record, $rowNumber, $summary),
                'products' => $this->importProduct($record, $rowNumber, $summary),
                default => null,
            };
        }
    }

    private function importCategory(array $record, int $rowNumber, array &$summary): void
    {
        $validator = Validator::make($record, [
            'name' => [
                'required',
                'max:100',
                Rule::unique('categories', 'name')->whereNull('deleted_at'),
            ],
            'description' => 'sometimes|nullable',
        ]);

        if ($this->handleInvalid($validator, 'categories', $rowNumber, $summary)) {
            return;
        }

        Category::create([
            'name' => $this->value($record, 'name'),
            'description' => $this->value($record, 'description'),
        ]);

        $this->created('categories', $rowNumber, $record['name'], $summary);
    }

    private function importBrand(array $record, int $rowNumber, array &$summary): void
    {
        $validator = Validator::make($record, [
            'name' => [
                'required',
                'max:50',
                Rule::unique('brands', 'name')->whereNull('deleted_at'),
            ],
            'description' => 'sometimes|nullable',
        ]);

        if ($this->handleInvalid($validator, 'brands', $rowNumber, $summary)) {
            return;
        }

        Brand::create([
            'name' => $this->value($record, 'name'),
            'description' => $this->value($record, 'description'),
        ]);

        $this->created('brands', $rowNumber, $record['name'], $summary);
    }

    private function importAttribute(array $record, int $rowNumber, array &$summary): void
    {
        $name = $this->value($record, 'name');
        $attribute = Attribute::where('name', $name)->first();

        if (! $attribute) {
            $validator = Validator::make($record, [
                'name' => [
                    'required',
                    'max:50',
                    Rule::unique('attributes', 'name')->whereNull('deleted_at'),
                ],
                'description' => 'sometimes|nullable|string|max:500',
            ]);

            if ($this->handleInvalid($validator, 'attributes', $rowNumber, $summary)) {
                return;
            }

            $attribute = Attribute::create([
                'name' => $name,
                'description' => $this->value($record, 'description'),
            ]);

            $this->created('attributes', $rowNumber, $name, $summary);
        } else {
            $this->skipped('attributes', $rowNumber, $name, 'Attribute already exists.', $summary);
        }

        foreach ($this->splitList($this->value($record, 'values')) as $value) {
            if (AttributesValue::where('value', $value)->exists()) {
                $this->skipped('attributes', $rowNumber, $value, 'Attribute value already exists.', $summary);

                continue;
            }

            AttributesValue::create([
                'attribute_id' => $attribute->id,
                'value' => $value,
            ]);

            $summary['attributes']['created']++;
            $summary['attributes']['rows'][] = [
                'row' => $rowNumber,
                'status' => 'created',
                'message' => "Created attribute value: {$value}",
            ];
        }
    }

    private function importSupplier(array $record, int $rowNumber, array &$summary): void
    {
        $validator = Validator::make($record, [
            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('suppliers', 'name')->whereNull('deleted_at'),
            ],
            'contact_person' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('suppliers', 'email')->whereNull('deleted_at'),
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        if ($this->handleInvalid($validator, 'suppliers', $rowNumber, $summary)) {
            return;
        }

        Supplier::create([
            'name' => $this->value($record, 'name'),
            'contact_person' => $this->value($record, 'contact_person'),
            'email' => $this->value($record, 'email'),
            'phone' => $this->value($record, 'phone'),
            'address' => $this->value($record, 'address'),
        ]);

        $this->created('suppliers', $rowNumber, $record['name'], $summary);
    }

    private function importProduct(array $record, int $rowNumber, array &$summary): void
    {
        $category = Category::where('name', $this->value($record, 'category_name'))->first();
        $brand = Brand::where('name', $this->value($record, 'brand_name'))->first();

        $data = [
            'category_id' => $category?->id,
            'brand_id' => $brand?->id,
            'sku' => $this->value($record, 'sku'),
            'name' => $this->value($record, 'name'),
            'description' => $this->value($record, 'description'),
            'unit_price' => $this->value($record, 'unit_price'),
            'cost_price' => $this->value($record, 'cost_price'),
            'reorder_level' => $this->value($record, 'reorder_level') ?: 10,
            'initial_stock' => $this->value($record, 'initial_stock') ?: 0,
            'location' => $this->value($record, 'location'),
            'is_active' => $this->booleanValue($this->value($record, 'is_active'), true),
            'image_source_url' => $this->value($record, 'image_source_url'),
        ];

        $validator = Validator::make($data, [
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'sku' => [
                'required',
                'max:50',
                Rule::unique('products', 'sku')->whereNull('deleted_at'),
            ],
            'name' => 'required|max:50',
            'description' => 'sometimes|nullable',
            'unit_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'reorder_level' => 'sometimes|integer|min:0',
            'initial_stock' => 'sometimes|integer|min:0',
            'location' => 'sometimes|nullable|string|max:255',
            'is_active' => 'required|boolean',
            'image_source_url' => 'sometimes|nullable|url:https|max:2048',
        ]);

        if ($this->handleInvalid($validator, 'products', $rowNumber, $summary)) {
            return;
        }

        $attributeValueIds = $this->resolveAttributeValues($this->value($record, 'attribute_values'));
        if ($attributeValueIds === null) {
            $this->failed('products', $rowNumber, $record['sku'] ?? '', 'One or more attribute values were not found.', $summary);

            return;
        }

        $this->productService->create([...$data, 'attribute_value_ids' => $attributeValueIds]);

        $this->created('products', $rowNumber, $record['sku'], $summary);
    }

    private function resolveAttributeValues(?string $attributeValues): ?array
    {
        $ids = [];

        foreach ($this->splitList($attributeValues) as $pair) {
            if (! str_contains($pair, ':')) {
                return null;
            }

            [$attributeName, $value] = array_map('trim', explode(':', $pair, 2));
            $attributeValue = AttributesValue::query()
                ->where('value', $value)
                ->whereHas('attribute', fn ($query) => $query->where('name', $attributeName))
                ->first();

            if (! $attributeValue) {
                return null;
            }

            $ids[] = $attributeValue->id;
        }

        return array_values(array_unique($ids));
    }

    private function recordsFromRows(array $rows): array
    {
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader($header), array_shift($rows));
        $records = [];

        foreach ($rows as $index => $row) {
            $record = [];
            foreach ($headers as $column => $header) {
                if ($header === '') {
                    continue;
                }
                $record[$header] = $this->value($row, $column);
            }
            $records[$index + 2] = $record;
        }

        return $records;
    }

    private function validateRowsForType(string $type, array $rows): void
    {
        $actual = $this->normalizedHeaders($rows[0] ?? []);
        $expected = self::IMPORT_HEADERS[$type] ?? [];
        $missing = array_values(array_diff($expected, $actual));
        $unexpected = array_values(array_diff($actual, $expected));

        if ($missing === [] && $unexpected === []) {
            return;
        }

        $parts = ["The uploaded file headers do not match the {$type} import template."];

        if ($missing !== []) {
            $parts[] = 'Missing columns: '.implode(', ', $missing).'.';
        }

        if ($unexpected !== []) {
            $parts[] = 'Unexpected columns: '.implode(', ', $unexpected).'.';
        }

        throw ValidationException::withMessages([
            'file' => [implode(' ', $parts)],
        ]);
    }

    private function normalizedHeaders(array $row): array
    {
        return collect($row)
            ->map(fn ($header) => $this->normalizeHeader($header))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sheetNameForType(string $type): ?string
    {
        $sheetName = array_search($type, self::SHEET_TYPES, true);

        return $sheetName === false ? null : $sheetName;
    }

    private function emptySummary(): array
    {
        return collect(['categories', 'brands', 'attributes', 'suppliers', 'products'])
            ->mapWithKeys(fn ($type) => [
                $type => [
                    'created' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                    'rows' => [],
                ],
            ])
            ->all();
    }

    private function handleInvalid($validator, string $type, int $rowNumber, array &$summary): bool
    {
        if (! $validator->fails()) {
            return false;
        }

        $message = $validator->errors()->first();
        $duplicate = str_contains(strtolower($message), 'already been taken');

        if ($duplicate) {
            $this->skipped($type, $rowNumber, '', $message, $summary);
        } else {
            $this->failed($type, $rowNumber, '', $message, $summary);
        }

        return true;
    }

    private function created(string $type, int $rowNumber, string $label, array &$summary): void
    {
        $summary[$type]['created']++;
        $summary[$type]['rows'][] = [
            'row' => $rowNumber,
            'status' => 'created',
            'message' => "Created {$label}",
        ];
    }

    private function skipped(string $type, int $rowNumber, string $label, string $message, array &$summary): void
    {
        $summary[$type]['skipped']++;
        $summary[$type]['rows'][] = [
            'row' => $rowNumber,
            'status' => 'skipped',
            'message' => trim($label.' '.$message),
        ];
    }

    private function failed(string $type, int $rowNumber, string $label, string $message, array &$summary): void
    {
        $summary[$type]['failed']++;
        $summary[$type]['rows'][] = [
            'row' => $rowNumber,
            'status' => 'failed',
            'message' => trim($label.' '.$message),
        ];
    }

    private function splitList(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(explode(';', $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeHeader(mixed $header): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim((string) $header)));
    }

    private function isEmptyRecord(array $record): bool
    {
        return collect($record)->every(fn ($value) => trim((string) $value) === '');
    }

    private function value(array $data, string|int $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function booleanValue(?string $value, bool $default): bool
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
