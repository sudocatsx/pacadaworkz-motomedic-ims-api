<?php

use App\Models\Permission;
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

function dashboardUserWithPermissions(string $roleName, array $permissionNames): User
{
    $role = Role::create([
        'role_name' => $roleName,
        'description' => 'Dashboard permission test role',
    ]);
    $permissionIds = Permission::where('module', 'Dashboard')
        ->whereIn('name', $permissionNames)
        ->pluck('id');
    $role->permissions()->attach($permissionIds);

    return User::factory()->create(['role_id' => $role->id]);
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
): void {
    $now = $date ? Carbon::parse($date) : Carbon::now();
    $netAmount = ($quantity - $quantityReturned) * $unitPrice;
    $transactionId = DB::table('sales_transactions')->insertGetId([
        'user_id' => $user->id,
        'transaction_no' => 'DASH-'.$status.'-'.$productId.'-'.uniqid(),
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
        'name' => 'Dashboard Supplier '.uniqid(),
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
        'name' => 'Inventory Supplier '.uniqid(),
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

test('dashboard top products only counts sales from the last seven days', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = dashboardUserForRole();
    $recentProductId = createDashboardRevenueProduct('Recent Category', 'Recent Brand', 'Recent Product', 'REC-001');
    $oldProductId = createDashboardRevenueProduct('Old Category', 'Old Brand', 'Old Product', 'OLD-001');
    $voidedProductId = createDashboardRevenueProduct('Voided Top Category', 'Voided Top Brand', 'Voided Top Product', 'VTOP-001');

    createDashboardSalesItem($user, $recentProductId, 'completed', 7, 2, 100, '2026-07-02 09:00:00');
    createDashboardSalesItem($user, $oldProductId, 'completed', 50, 0, 100, '2026-07-01 09:00:00');
    createDashboardSalesItem($user, $voidedProductId, 'voided', 20, 0, 100, '2026-07-08 09:00:00');

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/dashboard/charts/top-products');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.Recent Product', 5)
        ->assertJsonPath('data.Old Product', 0)
        ->assertJsonPath('data.Voided Top Product', 0);
});

test('operational dashboard stats are scoped to the authenticated employee and omit financial fields', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $cashier = dashboardUserForRole('staff');
    $otherCashier = dashboardUserForRole('staff');
    $productId = createDashboardRevenueProduct('Scope Category', 'Scope Brand', 'Scope Product', 'SCOPE-001');

    createDashboardSalesItem($cashier, $productId, 'completed', 4, 1, 100, '2026-07-08 09:00:00');
    createDashboardSalesItem($otherCashier, $productId, 'completed', 9, 2, 100, '2026-07-08 10:00:00');

    $response = $this->actingAs($cashier, 'api')->getJson('/api/v1/dashboard/stats');

    $response->assertOk()
        ->assertJsonPath('data.my_transactions_today', 1)
        ->assertJsonPath('data.my_items_sold_today', 3);

    foreach ([
        'todays_sales', 'todays_transactions', 'todays_items_sold', 'todays_purchases',
        'todays_cost_of_goods_sold', 'todays_stock_adjustment_losses', 'todays_net_profit',
        'total_products', 'total_revenue', 'total_transactions', 'total_sales', 'active_users',
    ] as $field) {
        $response->assertJsonMissingPath('data.'.$field);
    }
});

test('operational dashboard users cannot access financial chart endpoints', function () {
    $staff = dashboardUserForRole('staff');

    foreach ([
        '/api/v1/dashboard/charts/sales-trend',
        '/api/v1/dashboard/charts/revenue-by-category',
        '/api/v1/dashboard/charts/revenue-by-brand',
        '/api/v1/dashboard/charts/inventory-overview',
    ] as $endpoint) {
        $this->actingAs($staff, 'api')->getJson($endpoint)->assertForbidden();
    }
});

test('built-in financial roles and a custom role with both permissions receive financial access', function () {
    $users = [
        dashboardUserForRole('admin'),
        dashboardUserForRole('superadmin'),
        dashboardUserWithPermissions('dashboard-analyst', ['View', 'View Financial Data']),
    ];

    foreach ($users as $user) {
        $this->actingAs($user, 'api')
            ->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'my_transactions_today', 'my_items_sold_today', 'low_stock', 'out_of_stock',
                'todays_sales', 'todays_purchases', 'todays_net_profit', 'total_revenue',
            ]]);

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/dashboard/charts/sales-trend')
            ->assertOk();
    }
});

test('financial permission without dashboard view is not sufficient for any dashboard endpoint', function () {
    $user = dashboardUserWithPermissions('financial-without-dashboard', ['View Financial Data']);

    foreach ([
        '/api/v1/dashboard/stats',
        '/api/v1/dashboard/charts/top-products',
        '/api/v1/dashboard/recent-activities',
        '/api/v1/dashboard/charts/sales-trend',
    ] as $endpoint) {
        $this->actingAs($user, 'api')->getJson($endpoint)->assertForbidden();
    }
});

test('operational users retain unit-based top products and own-only recent activity', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $cashier = dashboardUserForRole('staff');
    $otherCashier = dashboardUserForRole('staff');
    $productId = createDashboardRevenueProduct('Ops Category', 'Ops Brand', 'Ops Product', 'OPS-001');
    createDashboardSalesItem($cashier, $productId, 'completed', 6, 2, 500, '2026-07-08 09:00:00');

    DB::table('activity_logs')->insert([
        [
            'user_id' => $cashier->id,
            'action' => 'checkout',
            'module' => 'POS',
            'description' => 'Own cashier activity',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $otherCashier->id,
            'action' => 'checkout',
            'module' => 'POS',
            'description' => 'Other cashier activity',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($cashier, 'api')
        ->getJson('/api/v1/dashboard/charts/top-products')
        ->assertOk()
        ->assertJsonPath('data.Ops Product', 4);

    $this->actingAs($cashier, 'api')
        ->getJson('/api/v1/dashboard/recent-activities')
        ->assertOk()
        ->assertJsonFragment(['description' => 'Own cashier activity'])
        ->assertJsonMissing(['description' => 'Other cashier activity']);
});
