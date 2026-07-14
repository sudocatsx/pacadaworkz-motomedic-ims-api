<?php

use App\Models\Attribute;
use App\Models\AttributesValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function masterDataUserForRole(string $roleName = 'admin'): User
{
    $role = Role::where('role_name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}

test('brand list uses the standard success data meta envelope', function () {
    Brand::create(['name' => 'Yamaha', 'description' => 'Motorcycle parts']);

    $this->actingAs(masterDataUserForRole(), 'api')
        ->getJson('/api/v1/brands?per_page=25')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'products_count'],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'total_pages'],
        ]);
});

test('plain brand list requests do not create activity logs', function () {
    Brand::create(['name' => 'Yamaha', 'description' => 'Motorcycle parts']);

    $this->actingAs(masterDataUserForRole(), 'api')
        ->getJson('/api/v1/brands')
        ->assertOk();

    $this->assertDatabaseCount('activity_logs', 0);
});

test('brand list requests with query params do not create activity logs', function () {
    Brand::create(['name' => 'Yamaha', 'description' => 'Motorcycle parts']);

    $this->actingAs(masterDataUserForRole(), 'api')
        ->getJson('/api/v1/brands?search=yamaha&per_page=25&page=1')
        ->assertOk();

    $this->assertDatabaseCount('activity_logs', 0);
});

test('product export requests create an activity log audit event', function () {
    $user = masterDataUserForRole();

    $this->actingAs($user, 'api')
        ->get('/api/v1/products/export')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $user->id,
        'module' => 'Products',
        'action' => 'View',
        'description' => 'Export Products',
    ]);
});

test('brand creation still creates an activity log through the service', function () {
    $user = masterDataUserForRole();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/brands', [
            'name' => 'Kawasaki',
            'description' => 'OEM parts',
        ])
        ->assertOk();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $user->id,
        'module' => 'Brand',
        'action' => 'created',
        'description' => 'Brand created: Kawasaki',
    ]);

    $this->assertDatabaseCount('activity_logs', 1);
});

test('category deletion is blocked while products use it', function () {
    $category = Category::create(['name' => 'Engine Parts']);
    $brand = Brand::create(['name' => 'Honda']);

    Product::create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sku' => 'ENG-001',
        'name' => 'Oil Filter',
        'unit_price' => 350,
        'cost_price' => 200,
    ]);

    $this->actingAs(masterDataUserForRole(), 'api')
        ->deleteJson("/api/v1/categories/{$category->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false);
});

test('product creation stores attribute values and exposes relationship ids', function () {
    $category = Category::create(['name' => 'Accessories']);
    $brand = Brand::create(['name' => 'Motomedic']);
    $attribute = Attribute::create(['name' => 'Color']);
    $value = AttributesValue::create([
        'attribute_id' => $attribute->id,
        'value' => 'Black',
    ]);

    $response = $this->actingAs(masterDataUserForRole(), 'api')
        ->postJson('/api/v1/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'ACC-001',
            'name' => 'Helmet Visor',
            'unit_price' => 900,
            'cost_price' => 500,
            'reorder_level' => 3,
            'initial_stock' => 2,
            'location' => 'Shelf A',
            'attribute_value_ids' => [$value->id],
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.category_id', $category->id)
        ->assertJsonPath('data.brand_id', $brand->id)
        ->assertJsonPath('data.attributes.0.attribute_id', $attribute->id);

    $this->assertDatabaseHas('product_attributes', [
        'attribute_value_id' => $value->id,
    ]);
});

test('product export route returns xlsx instead of being treated as product id', function () {
    $this->actingAs(masterDataUserForRole(), 'api')
        ->get('/api/v1/products/export')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('product export route still supports csv format', function () {
    $this->actingAs(masterDataUserForRole(), 'api')
        ->get('/api/v1/products/export?format=csv')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=utf-8');
});

test('catalog import template returns module specific xlsx attachment', function () {
    $this->actingAs(masterDataUserForRole(), 'api')
        ->get('/api/v1/imports/catalog-template?type=categories')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('catalog csv import creates new categories and skips duplicates', function () {
    Category::create(['name' => 'Existing', 'description' => 'Already present']);

    $file = UploadedFile::fake()->createWithContent(
        'categories.csv',
        "name,description\nNew Category,Fresh row\nExisting,Duplicate row\n"
    );

    $this->actingAs(masterDataUserForRole(), 'api')
        ->post('/api/v1/imports/catalog', [
            'type' => 'categories',
            'file' => $file,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.categories.created', 1)
        ->assertJsonPath('data.categories.skipped', 1);

    $this->assertDatabaseHas('categories', [
        'name' => 'New Category',
        'description' => 'Fresh row',
    ]);
});

test('catalog csv import rejects product headers for supplier imports', function () {
    $file = UploadedFile::fake()->createWithContent(
        'products.csv',
        "sku,name,category_name,brand_name,unit_price,cost_price,description,initial_stock,location,reorder_level,attribute_values\n".
        "ENG-001,Engine Oil,Fluids,Motul,500,300,Fully synthetic,10,Shelf A,3,\n"
    );

    $this->actingAs(masterDataUserForRole(), 'api')
        ->post('/api/v1/imports/catalog', [
            'type' => 'suppliers',
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'suppliers import template'))
        ->assertJsonPath('errors.file.0', fn (string $message) => str_contains($message, 'Unexpected columns: sku'));

    $this->assertDatabaseCount('suppliers', 0);
});

test('catalog csv import creates suppliers with valid supplier headers', function () {
    $file = UploadedFile::fake()->createWithContent(
        'suppliers.csv',
        "name,contact_person,email,phone,address\n".
        "Motul Philippines,Juan Dela Cruz,motul@example.test,09171234567,Manila\n"
    );

    $this->actingAs(masterDataUserForRole(), 'api')
        ->post('/api/v1/imports/catalog', [
            'type' => 'suppliers',
            'file' => $file,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.suppliers.created', 1)
        ->assertJsonPath('data.suppliers.failed', 0);

    $this->assertDatabaseHas('suppliers', [
        'name' => 'Motul Philippines',
        'contact_person' => 'Juan Dela Cruz',
        'email' => 'motul@example.test',
        'phone' => '09171234567',
        'address' => 'Manila',
    ]);
});
