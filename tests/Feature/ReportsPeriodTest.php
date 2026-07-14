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

function createSalesTransactionForDate(
    User $user,
    string $date,
    float $subtotal,
    float $discount = 0,
    float $refund = 0,
    string $status = 'completed',
): void
{
    $total = $subtotal - $discount;
    DB::table('sales_transactions')->insert([
        'user_id' => $user->id,
        'transaction_no' => 'TXN-'.str_replace(['-', ' ', ':'], '', $date),
        'subtotal' => $subtotal,
        'tax' => 0,
        'discount' => $discount,
        'discount_type' => null,
        'total_amount' => $total,
        'refund_amount' => $refund,
        'payment_method' => 'cash',
        'amount_tendered' => $total,
        'change' => 0,
        'status' => $status,
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
        'name' => $name.' Category',
        'description' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $brandId = DB::table('brands')->insertGetId([
        'name' => $name.' Brand',
        'description' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'name' => $name.' Supplier',
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
        'quantity' => $quantity,
        'last_stock_in' => $quantity > 0 ? $now : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $productId;
}

function createReportSalesItem(
    User $user,
    int $productId,
    string $date,
    int $quantity,
    float $unitPrice,
    string $status = 'completed',
    int $quantityReturned = 0,
): void {
    $createdAt = Carbon::parse($date);
    $netAmount = max($quantity - $quantityReturned, 0) * $unitPrice;
    $transactionId = DB::table('sales_transactions')->insertGetId([
        'user_id' => $user->id,
        'transaction_no' => 'RPT-'.$status.'-'.$productId.'-'.uniqid(),
        'subtotal' => $netAmount,
        'tax' => 0,
        'discount' => 0,
        'discount_type' => null,
        'total_amount' => $netAmount,
        'payment_method' => 'cash',
        'amount_tendered' => $netAmount,
        'change' => 0,
        'status' => $status,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    DB::table('sales_items')->insert([
        'sales_transactions_id' => $transactionId,
        'product_id' => $productId,
        'quantity' => $quantity,
        'quantity_returned' => $quantityReturned,
        'unit_price' => $unitPrice,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
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
        ->assertJsonPath('data.trend.0.total', 200)
        ->assertJsonPath('data.trend.1.date', '2026-07-06')
        ->assertJsonPath('data.trend.1.total', 0)
        ->assertJsonPath('data.trend.6.date', '2026-07-11')
        ->assertJsonPath('data.trend.6.total', 300);

    expect($response->json('data.trend'))->toHaveCount(7);

    expect($response->json('data.sales_by_staff'))->toBe([
        'Juan Dela Cruz' => 300,
        'Maria Santos' => 200,
    ]);
});

test('sales report reconciles gross sales discounts refunds and net sales while excluding voids', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));
    $user = reportsUserForRole();
    $user->update(['name' => 'Reconciliation Cashier']);

    createSalesTransactionForDate($user, '2026-07-08 08:00:00', 200);
    createSalesTransactionForDate($user, '2026-07-08 09:00:00', 200, 20);
    createSalesTransactionForDate($user, '2026-07-08 10:00:00', 200, 0, 100, 'partially_refunded');
    createSalesTransactionForDate($user, '2026-07-08 11:00:00', 200, 0, 0, 'voided');

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?period=daily');

    $response->assertOk()
        ->assertJsonPath('data.gross_sales', 600)
        ->assertJsonPath('data.discounts', 20)
        ->assertJsonPath('data.refunds', 100)
        ->assertJsonPath('data.net_sales', 480)
        ->assertJsonPath('data.total_sales', 480)
        ->assertJsonPath('data.transactions', 4)
        ->assertJsonPath('data.average_transaction', 160)
        ->assertJsonPath('data.trend.0.total', 480)
        ->assertJsonPath('data.sales_by_staff.Reconciliation Cashier', 480);

    $csv = $this->actingAs($user, 'api')
        ->get('/api/v1/reports/sales/export?format=csv&period=daily')
        ->assertOk()
        ->getContent();

    expect($csv)
        ->toContain('"Gross Sales",600')
        ->toContain('Discounts,20')
        ->toContain('Refunds,100')
        ->toContain('"Net Sales",480');
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
        ->assertJsonPath('data.trend.0.total', 100)
        ->assertJsonPath('data.trend.1.date', '2026-07-02')
        ->assertJsonPath('data.trend.1.total', 0)
        ->assertJsonPath('data.trend.4.date', '2026-07-05')
        ->assertJsonPath('data.trend.4.total', 200);
});

test('custom report period requires a complete valid historical date range', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
    $user = reportsUserForRole();

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?period=custom')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date', 'end_date']);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?start_date=2026-07-10')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?start_date=2026-07-10&end_date=2026-07-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?start_date=2026-07-10&end_date=2026-07-14')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

test('long sales report ranges use bounded zero-filled monthly trends', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
    $user = reportsUserForRole();

    createSalesTransactionForDate($user, '1975-01-15 10:00:00', 100);
    createSalesTransactionForDate($user, '2026-07-01 10:00:00', 200);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?start_date=1975-01-01&end_date=2026-07-13')
        ->assertOk()
        ->assertJsonPath('data.total_sales', 300)
        ->assertJsonPath('data.trend_granularity', 'monthly')
        ->assertJsonPath('data.trend.0.date', '1975-01')
        ->assertJsonPath('data.trend.0.total', 100)
        ->assertJsonPath('data.trend.618.date', '2026-07')
        ->assertJsonPath('data.trend.618.total', 200);

    expect($response->json('data.trend'))->toHaveCount(619);
});

test('long purchase report ranges use the same monthly trend strategy', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
    $user = reportsUserForRole();
    $supplier = Supplier::create(['name' => 'Monthly Trend Supplier']);

    createPurchaseOrderForDate($user, $supplier, '2024-01-15 10:00:00', 500);
    createPurchaseOrderForDate($user, $supplier, '2026-07-01 10:00:00', 700);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/purchases?start_date=2024-01-01&end_date=2026-07-13')
        ->assertOk()
        ->assertJsonPath('data.total_purchases', 1200)
        ->assertJsonPath('data.trend_granularity', 'monthly')
        ->assertJsonPath('data.trend.0.date', '2024-01')
        ->assertJsonPath('data.trend.0.total', 500)
        ->assertJsonPath('data.trend.30.date', '2026-07')
        ->assertJsonPath('data.trend.30.total', 700);

    expect($response->json('data.trend'))->toHaveCount(31);
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
        ->assertJsonPath('data.purchase_orders', 3)
        ->assertJsonPath('data.trend.0.date', '2026-07-05')
        ->assertJsonPath('data.trend.0.total', 500)
        ->assertJsonPath('data.trend.2.date', '2026-07-07')
        ->assertJsonPath('data.trend.2.total', 0)
        ->assertJsonPath('data.trend.6.date', '2026-07-11')
        ->assertJsonPath('data.trend.6.total', 1200);

    expect($response->json('data.trend'))->toHaveCount(7);

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

test('performance top products only include selected period sales ranked by quantity', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();
    $brakePadId = createInventoryReportProduct('Brake Pad', 'BRK-PERF-001', 10, 2, 100, 150);
    $chainKitId = createInventoryReportProduct('Chain Kit', 'CHN-PERF-001', 10, 2, 200, 300);
    $outsidePeriodId = createInventoryReportProduct('Outside Period Item', 'OUT-PERF-001', 10, 2, 50, 75);

    createReportSalesItem($user, $brakePadId, '2026-07-05 10:00:00', 3, 150);
    createReportSalesItem($user, $chainKitId, '2026-07-06 10:00:00', 5, 300);
    createReportSalesItem($user, $outsidePeriodId, '2026-07-12 10:00:00', 20, 75);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/product-performance?period=weekly');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.top_products.0.product_id', $chainKitId)
        ->assertJsonPath('data.top_products.0.product_name', 'Chain Kit')
        ->assertJsonPath('data.top_products.0.quantity_sold', 5)
        ->assertJsonPath('data.top_products.0.revenue', 1500)
        ->assertJsonPath('data.top_products.1.product_id', $brakePadId)
        ->assertJsonPath('data.top_products.1.quantity_sold', 3)
        ->assertJsonPath('data.top_products.1.revenue', 450);

    expect($response->json('data.top_products'))->toHaveCount(2);
});

test('weekly performance period excludes sales outside sunday through saturday', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();
    $productId = createInventoryReportProduct('Weekly Brake Pad', 'BRK-PERF-002', 10, 2, 100, 150);

    createReportSalesItem($user, $productId, '2026-07-04 10:00:00', 4, 150);
    createReportSalesItem($user, $productId, '2026-07-05 10:00:00', 2, 150);
    createReportSalesItem($user, $productId, '2026-07-11 10:00:00', 3, 150);
    createReportSalesItem($user, $productId, '2026-07-12 10:00:00', 5, 150);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/product-performance?period=weekly')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.top_products.0.product_id', $productId)
        ->assertJsonPath('data.top_products.0.quantity_sold', 5)
        ->assertJsonPath('data.top_products.0.revenue', 750);
});

test('performance top products exclude voided sales and subtract returned quantities', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();
    $brakePadId = createInventoryReportProduct('Returned Brake Pad', 'BRK-PERF-003', 10, 2, 100, 150);
    $voidedItemId = createInventoryReportProduct('Voided Item', 'VOID-PERF-001', 10, 2, 100, 500);

    createReportSalesItem($user, $brakePadId, '2026-07-05 10:00:00', 6, 150, 'completed', 2);
    createReportSalesItem($user, $voidedItemId, '2026-07-06 10:00:00', 12, 500, 'voided');

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/product-performance?period=weekly');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.top_products.0.product_id', $brakePadId)
        ->assertJsonPath('data.top_products.0.quantity_sold', 4)
        ->assertJsonPath('data.top_products.0.revenue', 600);

    expect($response->json('data.top_products'))->toHaveCount(1);

    $categoryRevenue = collect($response->json('data.revenue_by_category'))->firstWhere('name', 'Voided Item Category');
    expect((float) $categoryRevenue['total'])->toBe(0.0);
});

test('performance export includes top products section', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();
    $productId = createInventoryReportProduct('Export Brake Pad', 'BRK-PERF-004', 10, 2, 100, 150);

    createReportSalesItem($user, $productId, '2026-07-05 10:00:00', 4, 150);

    $response = $this->actingAs($user, 'api')
        ->get('/api/v1/reports/performance/export?period=weekly&format=csv');

    $response->assertOk();

    expect($response->getContent())
        ->toContain('Top Selling Products')
        ->toContain('Product')
        ->toContain('Quantity Sold')
        ->toContain('Export Brake Pad')
        ->toContain('4,600');
});
