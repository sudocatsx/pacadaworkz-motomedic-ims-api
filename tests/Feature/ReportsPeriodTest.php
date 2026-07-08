<?php

use App\Models\Role;
use App\Models\Supplier;
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

function reportsUserForRole(string $roleName = 'admin'): User
{
    $role = Role::where('role_name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}

function createSalesTransactionForDate(User $user, string $date, float $total): void
{
    DB::table('sales_transactions')->insert([
        'user_id' => $user->id,
        'transaction_no' => 'TXN-' . str_replace(['-', ' ', ':'], '', $date),
        'subtotal' => $total,
        'tax' => 0,
        'discount' => 0,
        'discount_type' => null,
        'total_amount' => $total,
        'payment_method' => 'cash',
        'amount_tendered' => $total,
        'change' => 0,
        'status' => 'completed',
        'created_at' => Carbon::parse($date),
        'updated_at' => Carbon::parse($date),
    ]);
}

function createPurchaseOrderForDate(User $user, Supplier $supplier, string $date, float $total): void
{
    DB::table('purchase_orders')->insert([
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'order_date' => Carbon::parse($date)->toDateString(),
        'expected_delivery' => null,
        'total_amount' => $total,
        'status' => 'received',
        'notes' => null,
        'created_at' => Carbon::parse($date),
        'updated_at' => Carbon::parse($date),
    ]);
}

function createInventoryReportProduct(
    string $name,
    string $sku,
    int $quantity,
    int $reorderLevel,
    float $costPrice = 100,
    float $unitPrice = 150,
): int {
    $now = Carbon::now();
    $categoryId = DB::table('categories')->insertGetId([
        'name' => $name . ' Category',
        'description' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $brandId = DB::table('brands')->insertGetId([
        'name' => $name . ' Brand',
        'description' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'name' => $name . ' Supplier',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $productId = DB::table('products')->insertGetId([
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'sku' => $sku,
        'name' => $name,
        'description' => null,
        'unit_price' => $unitPrice,
        'cost_price' => $costPrice,
        'reorder_level' => $reorderLevel,
        'image_url' => null,
        'is_active' => true,
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

    return $productId;
}

function createStockMovementForDate(
    User $user,
    int $productId,
    string $date,
    string $movementType,
    int $quantity,
    string $referenceType,
    int $referenceId,
): void {
    DB::table('stock_movements')->insert([
        'product_id' => $productId,
        'user_id' => $user->id,
        'movement_type' => $movementType,
        'quantity' => $quantity,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
        'notes' => null,
        'created_at' => Carbon::parse($date),
    ]);
}

test('weekly report period uses sunday through saturday instead of rolling seven days', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();
    $secondUser = reportsUserForRole();
    $user->update(['name' => 'Maria Santos']);
    $secondUser->update(['name' => 'Juan Dela Cruz']);

    createSalesTransactionForDate($user, '2026-07-01 10:00:00', 100);
    createSalesTransactionForDate($user, '2026-07-05 10:00:00', 200);
    createSalesTransactionForDate($secondUser, '2026-07-11 10:00:00', 300);
    createSalesTransactionForDate($user, '2026-07-12 10:00:00', 400);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?period=weekly');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_sales', 500)
        ->assertJsonPath('data.transactions', 2)
        ->assertJsonPath('data.trend.0.date', '2026-07-05')
        ->assertJsonPath('data.trend.1.date', '2026-07-11');

    expect($response->json('data.sales_by_staff'))->toBe([
        'Juan Dela Cruz' => 300,
        'Maria Santos' => 200,
    ]);
});

test('custom report period uses explicit start and end dates', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();

    createSalesTransactionForDate($user, '2026-07-01 10:00:00', 100);
    createSalesTransactionForDate($user, '2026-07-05 10:00:00', 200);
    createSalesTransactionForDate($user, '2026-07-11 10:00:00', 300);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?start_date=2026-07-01&end_date=2026-07-08')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_sales', 300)
        ->assertJsonPath('data.transactions', 2)
        ->assertJsonPath('data.trend.0.date', '2026-07-01')
        ->assertJsonPath('data.trend.1.date', '2026-07-05');
});

test('purchase report groups totals by supplier from backend data', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();
    $supplierA = Supplier::create(['name' => 'MotorPro Supply']);
    $supplierB = Supplier::create(['name' => 'Rider Parts Co.']);

    createPurchaseOrderForDate($user, $supplierA, '2026-07-05 10:00:00', 500);
    createPurchaseOrderForDate($user, $supplierA, '2026-07-06 10:00:00', 300);
    createPurchaseOrderForDate($user, $supplierB, '2026-07-11 10:00:00', 1200);
    createPurchaseOrderForDate($user, $supplierB, '2026-07-12 10:00:00', 900);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/purchases?period=weekly');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_purchases', 2000)
        ->assertJsonPath('data.purchase_orders', 3);

    expect($response->json('data.purchase_by_supplier'))->toBe([
        'Rider Parts Co.' => 1200,
        'MotorPro Supply' => 800,
    ]);
});

test('inventory report returns current health and weekly stock movement metrics', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();
    $lowStockProductId = createInventoryReportProduct('Brake Pad', 'BRK-001', 3, 5, 100, 150);
    $outOfStockProductId = createInventoryReportProduct('Chain Kit', 'CHN-001', 0, 4, 200, 300);
    $normalProductId = createInventoryReportProduct('Riding Helmet', 'HLM-001', 12, 3, 50, 80);

    createStockMovementForDate($user, $lowStockProductId, '2026-07-01 10:00:00', 'in', 99, 'purchase', 1);
    createStockMovementForDate($user, $lowStockProductId, '2026-07-05 10:00:00', 'in', 10, 'purchase', 2);
    createStockMovementForDate($user, $lowStockProductId, '2026-07-06 10:00:00', 'out', 4, 'sale', 3);
    createStockMovementForDate($user, $outOfStockProductId, '2026-07-07 10:00:00', 'out', 2, 'adjustment', 4);
    createStockMovementForDate($user, $outOfStockProductId, '2026-07-11 10:00:00', 'in', 6, 'purchase', 5);
    createStockMovementForDate($user, $normalProductId, '2026-07-12 10:00:00', 'out', 30, 'sale', 6);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/inventory?period=weekly');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_products', 3)
        ->assertJsonPath('data.low_stock', 1)
        ->assertJsonPath('data.out_of_stock', 1)
        ->assertJsonPath('data.low_stock_items.0.name', 'Brake Pad')
        ->assertJsonPath('data.out_of_stock_items.0.name', 'Chain Kit')
        ->assertJsonPath('data.movement_summary.stock_in_quantity', 16)
        ->assertJsonPath('data.movement_summary.stock_out_quantity', 6)
        ->assertJsonPath('data.movement_summary.net_stock_change', 10)
        ->assertJsonPath('data.movement_summary.movement_count', 4)
        ->assertJsonPath('data.top_moved_products.0.name', 'Brake Pad')
        ->assertJsonPath('data.top_moved_products.0.total_moved', 14)
        ->assertJsonPath('data.recent_movements.0.product_name', 'Chain Kit');

    expect($response->json('data.movement_by_source'))->toBe([
        'adjustment' => ['quantity' => 2, 'count' => 1],
        'purchase' => ['quantity' => 16, 'count' => 2],
        'sale' => ['quantity' => 4, 'count' => 1],
    ]);
});

test('inventory report custom period filters movements but keeps current stock health', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();
    $productId = createInventoryReportProduct('Brake Pad', 'BRK-002', 3, 5, 100, 150);

    createStockMovementForDate($user, $productId, '2026-07-05 10:00:00', 'in', 10, 'purchase', 1);
    createStockMovementForDate($user, $productId, '2026-07-06 10:00:00', 'out', 4, 'sale', 2);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/inventory?start_date=2026-07-06&end_date=2026-07-06')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_products', 1)
        ->assertJsonPath('data.low_stock', 1)
        ->assertJsonPath('data.movement_summary.stock_in_quantity', 0)
        ->assertJsonPath('data.movement_summary.stock_out_quantity', 4)
        ->assertJsonPath('data.movement_summary.net_stock_change', -4)
        ->assertJsonPath('data.movement_summary.movement_count', 1);
});
