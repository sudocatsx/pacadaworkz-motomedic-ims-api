# Copilot Instructions for MotoMedic IMS API

## Project Context
This is a Laravel 12 backend API for an Inventory Management System (IMS) for motorcycle parts and services.

## Tech Stack
- **Framework:** Laravel 12.x
- **PHP Version:** 8.2+
- **Database:** MySQL
- **Architecture:** Feature-based (Services, Controllers, Models)

## Coding Guidelines
1. **Always use Service layer** - Never put business logic directly in controllers
2. **Type everything** - Use PHP 8.2 typed properties, return types, and parameters
3. **Follow PSR-12** - Code style standards
4. **Use Form Requests** - For validation, never validate in controllers
5. **Eloquent best practices** - Use eager loading to prevent N+1 queries

## File Structure
```
app/
├── Http/Controllers/API/  # Thin controllers, delegate to Services
├── Services/              # Business logic layer
├── Models/                # Eloquent models
└── Http/Requests/         # Form request validation
```

## Code Review Checklist
Before suggesting any changes, verify:
- [ ] Is there an existing test?
- [ ] Does this follow the Service pattern?
- [ ] Are all properties typed?
- [ ] Is validation in a FormRequest?
- [ ] Are relationships eager-loaded?

## Common Patterns

### Controller Structure
```php
public function store(StoreProductRequest $request)
{
    $userId = auth()->id();
    $validated = $request->validated();
    
    $result = $this->productService->create($userId, $validated);
    
    return response()->json($result, 201);
}
```

### Service Structure
```php
public function create(int $userId, array $data): array
{
    DB::beginTransaction();
    try {
        $product = Product::create([...]);
        DB::commit();
        return ['success' => true, 'data' => $product];
    } catch (\Exception $e) {
        DB::rollback();
        throw $e;
    }
}
```

## When to Ask
- Architectural decisions that affect multiple modules
- Changes to database schema
- New feature implementation approach
- Breaking changes to existing APIs
