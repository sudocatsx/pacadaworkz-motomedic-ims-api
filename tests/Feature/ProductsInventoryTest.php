<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function productsActor(string $role = 'admin'): User
{
    return User::factory()->create([
        'role_id' => Role::where('role_name', $role)->value('id'),
    ]);
}

function productsFixture(array $product = [], array $inventory = []): Product
{
    $category = Category::create(['name' => 'Engine Parts '.uniqid()]);
    $brand = Brand::create(['name' => 'Motomedic '.uniqid()]);
    $model = Product::create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sku' => $product['sku'] ?? 'SKU-'.uniqid(),
        'name' => $product['name'] ?? 'Brake Pad',
        'unit_price' => $product['unit_price'] ?? 500,
        'cost_price' => $product['cost_price'] ?? 300,
        'reorder_level' => $product['reorder_level'] ?? 5,
        'is_active' => $product['is_active'] ?? true,
    ]);
    $model->inventory()->create([
        'quantity' => $inventory['quantity'] ?? 3,
        'location' => $inventory['location'] ?? 'Shelf B-2',
    ]);

    return $model;
}

test('product list is the canonical inventory response with real status and summary', function () {
    productsFixture([], ['quantity' => 3, 'location' => 'Shelf B-2']);
    productsFixture(['name' => 'Chain Kit'], ['quantity' => 0, 'location' => null]);

    $this->actingAs(productsActor(), 'api')
        ->getJson('/api/v1/products?stock_status=low_stock&per_page=20')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.location', 'Shelf B-2')
        ->assertJsonPath('data.0.inventory.location', 'Shelf B-2')
        ->assertJsonPath('data.0.stock_status', 'low_stock')
        ->assertJsonPath('summary.total_products', 2)
        ->assertJsonPath('summary.low_stock', 1)
        ->assertJsonPath('summary.out_of_stock', 1)
        ->assertJsonPath('meta.per_page', 20);
});

test('product creation stores opening stock and records an opening movement', function () {
    $category = Category::create(['name' => 'Accessories']);
    $brand = Brand::create(['name' => 'Moto']);

    $response = $this->actingAs(productsActor(), 'api')->postJson('/api/v1/products', [
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sku' => 'ACC-100',
        'name' => 'Helmet Visor',
        'unit_price' => 900,
        'cost_price' => 500,
        'initial_stock' => 8,
        'reorder_level' => 3,
        'location' => 'Display 1',
        'is_active' => false,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.current_stock', 8)
        ->assertJsonPath('data.location', 'Display 1')
        ->assertJsonPath('data.is_active', false);
    $productId = $response->json('data.id');
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $productId,
        'movement_type' => 'in',
        'quantity' => 8,
        'reference_type' => 'opening',
    ]);
});

test('product edit cannot overwrite inventory quantity', function () {
    $product = productsFixture([], ['quantity' => 9]);

    $this->actingAs(productsActor(), 'api')->putJson("/api/v1/products/{$product->id}", [
        'category_id' => $product->category_id,
        'brand_id' => $product->brand_id,
        'sku' => $product->sku,
        'name' => 'Updated Brake Pad',
        'unit_price' => 550,
        'cost_price' => 320,
        'reorder_level' => 4,
        'location' => 'Shelf C',
        'initial_stock' => 100,
    ])->assertOk()->assertJsonPath('data.current_stock', 9);

    expect($product->inventory->fresh()->quantity)->toBe(9);
});

test('count adjustment atomically updates stock and creates audit records', function () {
    $product = productsFixture([], ['quantity' => 9]);
    $actor = productsActor();

    $this->actingAs($actor, 'api')
        ->postJson("/api/v1/products/{$product->id}/stock-adjustments", [
            'counted_quantity' => 4,
            'reason' => 'damaged',
            'notes' => 'Five units damaged by water',
        ])
        ->assertCreated()
        ->assertJsonPath('data.product.current_stock', 4)
        ->assertJsonPath('data.adjustment.previous_quantity', 9)
        ->assertJsonPath('data.adjustment.counted_quantity', 4)
        ->assertJsonPath('data.movement.movement_type', 'out')
        ->assertJsonPath('data.movement.quantity', 5);

    $this->assertDatabaseHas('inventory', ['product_id' => $product->id, 'quantity' => 4]);
    $this->assertDatabaseHas('activity_logs', ['module' => 'Products', 'action' => 'Adjust Stock']);

    $this->actingAs($actor, 'api')
        ->postJson("/api/v1/products/{$product->id}/stock-adjustments", [
            'counted_quantity' => 4,
            'reason' => 'physical_count',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['counted_quantity']);
});

test('product permissions are exact to their module and action', function () {
    $product = productsFixture();
    $role = Role::create(['role_name' => 'viewer', 'description' => 'Viewer']);
    $permissionIds = Permission::where(function ($query) {
        $query->where(fn ($q) => $q->where('module', 'Products')->where('name', 'View'))
            ->orWhere(fn ($q) => $q->where('module', 'Reports')->where('name', 'Export'));
    })->pluck('id');
    $role->permissions()->sync($permissionIds);
    $viewer = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($viewer, 'api')->getJson('/api/v1/products')->assertOk();
    $this->actingAs($viewer, 'api')->get('/api/v1/products/export')->assertForbidden();
    $this->actingAs($viewer, 'api')->postJson("/api/v1/products/{$product->id}/stock-adjustments", [
        'counted_quantity' => 2,
        'reason' => 'physical_count',
    ])->assertForbidden();
});

test('uploaded product images are stored as files and exposed by URL', function () {
    Storage::fake('public');
    $category = Category::create(['name' => 'Oils']);
    $brand = Brand::create(['name' => 'Motul']);

    $response = $this->actingAs(productsActor(), 'api')->post('/api/v1/products', [
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sku' => 'OIL-100',
        'name' => 'Engine Oil',
        'unit_price' => 500,
        'cost_price' => 300,
        'initial_stock' => 1,
        'image' => UploadedFile::fake()->createWithContent(
            'oil.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        ),
    ]);

    $response->assertCreated()->assertJsonPath('data.image_url', fn ($url) => str_contains($url, '/storage/products/'));
    $path = Product::find($response->json('data.id'))->image_url;
    Storage::disk('public')->assertExists($path);
    expect($path)->not->toContain('base64');
});

test('remote product images are owned by storage and private hosts are rejected', function () {
    Storage::fake('public');
    Http::fake([
        'https://93.184.216.34/product.png' => Http::response(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            200,
            ['Content-Type' => 'image/png']
        ),
    ]);
    $category = Category::create(['name' => 'Remote Images']);
    $brand = Brand::create(['name' => 'Remote Brand']);
    $payload = [
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sku' => 'REMOTE-1',
        'name' => 'Remote Image Product',
        'unit_price' => 200,
        'cost_price' => 100,
        'initial_stock' => 0,
    ];

    $response = $this->actingAs(productsActor(), 'api')->postJson('/api/v1/products', [
        ...$payload,
        'image_source_url' => 'https://93.184.216.34/product.png',
    ])->assertCreated();
    $product = Product::findOrFail($response->json('data.id'));
    Storage::disk('public')->assertExists($product->image_url);
    expect($product->image_source)->toBe('url');

    $this->actingAs(productsActor(), 'api')->postJson('/api/v1/products', [
        ...$payload,
        'sku' => 'REMOTE-2',
        'image_source_url' => 'https://127.0.0.1/private.png',
    ])->assertUnprocessable()->assertJsonValidationErrors(['image_source_url']);
});

test('standalone inventory routes are removed', function () {
    $this->actingAs(productsActor(), 'api')->getJson('/api/v1/inventory')->assertNotFound();
});

test('POS catalog only returns active products with canonical image data', function () {
    productsFixture(['name' => 'Active Product', 'is_active' => true]);
    productsFixture(['name' => 'Inactive Product', 'is_active' => false]);

    $this->actingAs(productsActor('staff'), 'api')
        ->getJson('/api/v1/pos/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Product');
});
