<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function posTestUser(string $role, array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role_id' => Role::where('role_name', $role)->firstOrFail()->id,
    ], $overrides));
}

function posTestProduct(float $price = 1200, float $cost = 700): int
{
    $categoryId = DB::table('categories')->insertGetId(['name' => 'POS Test Category', 'created_at' => now(), 'updated_at' => now()]);
    $brandId = DB::table('brands')->insertGetId(['name' => 'POS Test Brand', 'created_at' => now(), 'updated_at' => now()]);
    $productId = DB::table('products')->insertGetId([
        'category_id' => $categoryId, 'brand_id' => $brandId, 'sku' => 'POS-TEST-001', 'name' => 'POS Test Product',
        'unit_price' => $price, 'cost_price' => $cost, 'reorder_level' => 2, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('inventory')->insert(['product_id' => $productId, 'quantity' => 10, 'created_at' => now(), 'updated_at' => now()]);

    return $productId;
}

test('authorized discount survives cart refresh and checkout stores discounted financial snapshots', function () {
    $staff = posTestUser('staff');
    $manager = posTestUser('manager', ['authorization_pin' => Hash::make('123456')]);
    $productId = posTestProduct();

    $this->actingAs($staff, 'api')->postJson('/api/v1/pos/cart/add-item', ['product_id' => $productId, 'quantity' => 1])->assertCreated();
    $this->actingAs($staff, 'api')->postJson('/api/v1/pos/cart/add-item', ['product_id' => $productId, 'quantity' => 1])->assertCreated();

    $this->actingAs($staff, 'api')->postJson('/api/v1/pos/cart/apply-discount', [
        'discount' => 5, 'discount_type' => 'percentage', 'authorizer_id' => $manager->id, 'pin' => '123456',
    ])->assertOk()
        ->assertJsonPath('data.summary.subtotal', 2400)
        ->assertJsonPath('data.summary.discount', 120)
        ->assertJsonPath('data.summary.total', 2280);

    $this->actingAs($staff, 'api')->getJson('/api/v1/pos/cart')
        ->assertOk()->assertJsonPath('data.summary.total', 2280);

    $checkout = $this->actingAs($staff, 'api')->postJson('/api/v1/pos/checkout', [
        'payment_method' => 'cash', 'amount_tendered' => 2300,
    ])->assertOk()
        ->assertJsonPath('data.discount', 120)
        ->assertJsonPath('data.total_amount', 2280)
        ->assertJsonPath('data.change', 20);

    $transactionId = $checkout->json('data.id');
    $item = DB::table('sales_items')->where('sales_transactions_id', $transactionId)->first();
    expect((float) $item->unit_cost)->toBe(700.0)
        ->and((float) $item->allocated_discount)->toBe(120.0)
        ->and((float) $item->net_line_total)->toBe(2280.0)
        ->and((float) $item->refunded_line_amount)->toBe(0.0)
        ->and(DB::table('inventory')->where('product_id', $productId)->value('quantity'))->toBe(8);
});

test('adding an item invalidates an already authorized discount', function () {
    $staff = posTestUser('staff');
    $manager = posTestUser('manager', ['authorization_pin' => Hash::make('123456')]);
    $productId = posTestProduct(1000, 600);

    $this->actingAs($staff, 'api')->postJson('/api/v1/pos/cart/add-item', ['product_id' => $productId, 'quantity' => 1])->assertCreated();
    $this->actingAs($staff, 'api')->postJson('/api/v1/pos/cart/apply-discount', [
        'discount' => 10, 'discount_type' => 'percentage', 'authorizer_id' => $manager->id, 'pin' => '123456',
    ])->assertOk()->assertJsonPath('data.summary.total', 900);

    $this->actingAs($staff, 'api')->postJson('/api/v1/pos/cart/add-item', ['product_id' => $productId, 'quantity' => 1])->assertCreated();
    $this->actingAs($staff, 'api')->getJson('/api/v1/pos/cart')
        ->assertOk()->assertJsonPath('data.summary.discount', 0)->assertJsonPath('data.summary.total', 2000);
});

test('invalid discount authorization returns a user friendly validation response', function () {
    $staff = posTestUser('staff');
    $manager = posTestUser('manager', ['authorization_pin' => Hash::make('123456')]);
    $productId = posTestProduct(1000, 600);

    $this->actingAs($staff, 'api')->postJson('/api/v1/pos/cart/add-item', ['product_id' => $productId, 'quantity' => 1])->assertCreated();

    $this->actingAs($staff, 'api')->postJson('/api/v1/pos/cart/apply-discount', [
        'discount' => 10, 'discount_type' => 'percentage', 'authorizer_id' => $manager->id, 'pin' => '000000',
    ])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The authorization PIN is invalid.')
        ->assertJsonPath('errors.pin.0', 'The authorization PIN is invalid.')
        ->assertJsonMissingPath('exception')
        ->assertJsonMissingPath('trace');

    $this->actingAs($staff, 'api')->getJson('/api/v1/pos/cart')
        ->assertOk()->assertJsonPath('data.summary.discount', 0)->assertJsonPath('data.summary.total', 1000);
});
