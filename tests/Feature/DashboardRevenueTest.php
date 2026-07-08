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

afterEach(function () {
    Carbon::setTestNow();
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
    float $costPrice = 60,
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
        'cost_price' => $costPrice,
        'reorder_level' => 5,
        'image_url' => null,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function createDashboardSalesItem(
    User $user,
    int $productId,
    string $status,
    int $quantity,
    int $quantityReturned,
    float $unitPrice,
    ?string $date = null,
    float $refundAmount = 0,
): void
{
    $now = $date ? Carbon::parse($date) : Carbon::now();
    $netAmount = ($quantity - $quantityReturned) * $unitPrice;
    $transactionId = DB::table('sales_transactions')->insertGetId([
        'user_id' => $user->id,
        'transaction_no' => 'DASH-' . $status . '-' . $productId . '-' . uniqid(),
        'subtotal' => $netAmount,
        'tax' => 0,
        'discount' => 0,
        'discount_type' => null,
        'total_amount' => $netAmount,
        'refund_amount' => $refundAmount,
        'payment_method' => 'cash',
        'amount_tendered' => $netAmount,
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

function createDashboardPurchaseOrder(User $user, string $date, string $status, float $total): void
{
    $now = Carbon::parse($date);
    $supplierId = DB::table('suppliers')->insertGetId([
        'name' => 'Dashboard Supplier ' . uniqid(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('purchase_orders')->insert([
        'supplier_id' => $supplierId,
        'user_id' => $user->id,
        'order_date' => $now->toDateString(),
        'expected_delivery' => null,
        'total_amount' => $total,
        'status' => $status,
        'notes' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function createDashboardInventoryRecord(int $productId, int $quantity): void
{
    $now = Carbon::now();
    $supplierId = DB::table('suppliers')->insertGetId([
        'name' => 'Inventory Supplier ' . uniqid(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('inventory')->insert([
        'product_id' => $productId,
        'supplier_id' => $supplierId,
        'quantity' => $quantity,
        'last_stock_in' => $quantity > 0 ? $now : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function createDashboardStockAdjustmentLoss(User $user, int $productId, string $date, int $quantity): void
{
    $now = Carbon::parse($date);
    $adjustmentId = DB::table('stock_adjustments')->insertGetId([
        'user_id' => $user->id,
        'reason' => 'damaged',
        'notes' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('stock_movements')->insert([
        'product_id' => $productId,
        'user_id' => $user->id,
        'movement_type' => 'out',
        'quantity' => $quantity,
        'reference_type' => 'adjustment',
        'reference_id' => $adjustmentId,
        'notes' => null,
        'created_at' => $now,
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

test('dashboard stats return today sales purchases net profit and stock alerts', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = dashboardUserForRole();
    $todayProductId = createDashboardRevenueProduct('Today Category', 'Today Brand', 'Today Product', 'TOD-001', 40);
    $voidedProductId = createDashboardRevenueProduct('Voided Category', 'Voided Brand', 'Voided Product', 'VOID-001', 30);
    $lowStockProductId = createDashboardRevenueProduct('Low Category', 'Low Brand', 'Low Stock Product', 'LOW-001', 25);
    $outOfStockProductId = createDashboardRevenueProduct('Out Category', 'Out Brand', 'Out Product', 'OUT-001', 20);

    createDashboardSalesItem($user, $todayProductId, 'completed', 5, 1, 100, '2026-07-08 09:00:00', 50);
    createDashboardSalesItem($user, $todayProductId, 'completed', 2, 0, 150, '2026-07-07 09:00:00');
    createDashboardSalesItem($user, $voidedProductId, 'voided', 3, 0, 200, '2026-07-08 10:00:00');

    createDashboardPurchaseOrder($user, '2026-07-08 08:00:00', 'received', 700);
    createDashboardPurchaseOrder($user, '2026-07-08 08:30:00', 'pending', 300);
    createDashboardPurchaseOrder($user, '2026-07-08 11:00:00', 'cancelled', 999);
    createDashboardPurchaseOrder($user, '2026-07-07 11:00:00', 'received', 500);

    createDashboardStockAdjustmentLoss($user, $todayProductId, '2026-07-08 13:00:00', 2);
    createDashboardStockAdjustmentLoss($user, $todayProductId, '2026-07-07 13:00:00', 10);

    createDashboardInventoryRecord($lowStockProductId, 3);
    createDashboardInventoryRecord($outOfStockProductId, 0);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/dashboard/stats');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.todays_sales', 350)
        ->assertJsonPath('data.todays_transactions', 1)
        ->assertJsonPath('data.todays_items_sold', 4)
        ->assertJsonPath('data.todays_purchases', 1000)
        ->assertJsonPath('data.todays_cost_of_goods_sold', 160)
        ->assertJsonPath('data.todays_stock_adjustment_losses', 80)
        ->assertJsonPath('data.todays_net_profit', 110)
        ->assertJsonPath('data.low_stock', 1)
        ->assertJsonPath('data.out_of_stock', 1);
});
