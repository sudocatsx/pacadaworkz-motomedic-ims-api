<?php

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function dashboardUserForRole(string $roleName = 'admin'): User
{
    $role = Role::where('role_name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}

function createDashboardRevenueProduct(
    string $categoryName,
    string $brandName,
    string $productName,
    string $sku,
): int {
    $now = Carbon::now();
    $categoryId = DB::table('categories')->insertGetId([
        'name' => $categoryName,
        'description' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $brandId = DB::table('brands')->insertGetId([
        'name' => $brandName,
        'description' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return DB::table('products')->insertGetId([
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'sku' => $sku,
        'name' => $productName,
        'description' => null,
        'unit_price' => 100,
        'cost_price' => 60,
        'reorder_level' => 5,
        'image_url' => null,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function createDashboardSalesItem(User $user, int $productId, string $status, int $quantity, int $quantityReturned, float $unitPrice): void
{
    $now = Carbon::now();
    $transactionId = DB::table('sales_transactions')->insertGetId([
        'user_id' => $user->id,
        'transaction_no' => 'DASH-' . $status . '-' . $productId . '-' . uniqid(),
        'subtotal' => ($quantity - $quantityReturned) * $unitPrice,
        'tax' => 0,
        'discount' => 0,
        'discount_type' => null,
        'total_amount' => ($quantity - $quantityReturned) * $unitPrice,
        'refund_amount' => 0,
        'payment_method' => 'cash',
        'amount_tendered' => ($quantity - $quantityReturned) * $unitPrice,
        'change' => 0,
        'status' => $status,
        'refund_reason' => null,
        'refunded_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('sales_items')->insert([
        'sales_transactions_id' => $transactionId,
        'product_id' => $productId,
        'quantity' => $quantity,
        'quantity_returned' => $quantityReturned,
        'unit_price' => $unitPrice,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

test('dashboard revenue by category includes zero revenue categories and excludes voided sales', function () {
    $user = dashboardUserForRole();

    $engineProductId = createDashboardRevenueProduct('Engine Parts', 'Honda', 'Oil Filter', 'ENG-001');
    $brakeProductId = createDashboardRevenueProduct('Brake Parts', 'Yamaha', 'Brake Pad', 'BRK-001');
    createDashboardRevenueProduct('Accessories', 'Motomedic', 'Helmet Visor', 'ACC-001');

    createDashboardSalesItem($user, $engineProductId, 'completed', 3, 1, 100);
    createDashboardSalesItem($user, $brakeProductId, 'voided', 5, 0, 200);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/dashboard/charts/revenue-by-category');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.Engine Parts', 200)
        ->assertJsonPath('data.Brake Parts', 0)
        ->assertJsonPath('data.Accessories', 0);
});

test('dashboard revenue by brand includes zero revenue brands and excludes voided sales', function () {
    $user = dashboardUserForRole();

    $hondaProductId = createDashboardRevenueProduct('Engine Parts', 'Honda', 'Oil Filter', 'ENG-002');
    $yamahaProductId = createDashboardRevenueProduct('Brake Parts', 'Yamaha', 'Brake Pad', 'BRK-002');
    createDashboardRevenueProduct('Accessories', 'Motomedic', 'Helmet Visor', 'ACC-002');

    createDashboardSalesItem($user, $hondaProductId, 'completed', 4, 1, 150);
    createDashboardSalesItem($user, $yamahaProductId, 'voided', 2, 0, 500);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/dashboard/charts/revenue-by-brand');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.Honda', 450)
        ->assertJsonPath('data.Yamaha', 0)
        ->assertJsonPath('data.Motomedic', 0);
});
