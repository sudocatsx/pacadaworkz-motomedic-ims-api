<?php

use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00', 'Asia/Manila'));
});

afterEach(fn () => Carbon::setTestNow());

function transactionUser(string $role): User
{
    return User::factory()->create([
        'role_id' => Role::where('role_name', $role)->firstOrFail()->id,
    ]);
}

function transactionRecord(User $cashier, array $overrides = []): SalesTransaction
{
    return SalesTransaction::unguarded(fn () => SalesTransaction::create(array_merge([
        'user_id' => $cashier->id,
        'transaction_no' => 'TXN-1001',
        'subtotal' => 1000,
        'tax' => 0,
        'discount' => 100,
        'total_amount' => 900,
        'payment_method' => 'cash',
        'amount_tendered' => 1000,
        'change' => 100,
        'status' => 'completed',
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ], $overrides)));
}

function voidableTransactionItem(SalesTransaction $transaction, int $quantity = 3): int
{
    $now = Carbon::now();
    $categoryId = DB::table('categories')->insertGetId([
        'name' => 'Void Test Category',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $brandId = DB::table('brands')->insertGetId([
        'name' => 'Void Test Brand',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $productId = DB::table('products')->insertGetId([
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'sku' => 'VOID-TEST-001',
        'name' => 'Item 2',
        'unit_price' => 300,
        'cost_price' => 200,
        'reorder_level' => 2,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('inventory')->insert([
        'product_id' => $productId,
        'quantity' => 5,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('sales_items')->insert([
        'sales_transactions_id' => $transaction->id,
        'product_id' => $productId,
        'quantity' => $quantity,
        'quantity_returned' => 0,
        'unit_price' => 300,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $productId;
}

test('transaction records return filters pagination and filtered kpis', function () {
    $admin = transactionUser('admin');
    $admin->update(['authorization_pin' => Hash::make('123456')]);
    transactionRecord($admin);
    transactionRecord($admin, [
        'transaction_no' => 'TXN-OLD',
        'total_amount' => 500,
        'status' => 'voided',
        'created_at' => Carbon::now()->subMonth(),
        'updated_at' => Carbon::now()->subMonth(),
    ]);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/transactions?period=today&payment_method=cash')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.transaction_no', 'TXN-1001')
        ->assertJsonPath('data.0.cashier.name', $admin->name)
        ->assertJsonPath('meta.summary.transactions', 1)
        ->assertJsonPath('meta.summary.revenue', 900)
        ->assertJsonPath('meta.summary.status_counts.completed', 1)
        ->assertJsonPath('meta.filter_options.statuses.1', 'partially_refunded');
});

test('daily report uses gross and net sales and payment counts', function () {
    $admin = transactionUser('admin');
    transactionRecord($admin, ['refund_amount' => 200, 'status' => 'partially_refunded']);
    transactionRecord($admin, [
        'transaction_no' => 'TXN-VOID',
        'subtotal' => 400,
        'total_amount' => 400,
        'status' => 'voided',
    ]);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/transactions/daily-report?date=2026-07-10')
        ->assertOk()
        ->assertJsonPath('data.sales_overview.gross_sales', 1000)
        ->assertJsonPath('data.sales_overview.net_sales', 700)
        ->assertJsonPath('data.payment_breakdown.0.payment_method', 'cash')
        ->assertJsonPath('data.payment_breakdown.0.count', 1);
});

test('exports csv and real xlsx while enforcing export permission', function () {
    $admin = transactionUser('admin');
    $staff = transactionUser('staff');
    transactionRecord($admin);

    $this->actingAs($admin, 'api')
        ->get('/api/v1/transactions/export?format=csv&period=all')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $xlsx = $this->actingAs($admin, 'api')
        ->get('/api/v1/transactions/export?format=xlsx&period=all')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect(substr(file_get_contents($xlsx->baseResponse->getFile()->getPathname()), 0, 2))->toBe('PK');

    $this->actingAs($staff, 'api')
        ->getJson('/api/v1/transactions/export?format=csv&period=all')
        ->assertForbidden();
});

test('authorized manager can refund a transaction created by another cashier', function () {
    $admin = transactionUser('admin');
    $admin->update(['authorization_pin' => Hash::make('123456')]);
    $cashier = transactionUser('staff');
    $transaction = transactionRecord($cashier);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'refund_type' => 'full',
            'reason' => 'Customer return',
            'authorizer_id' => $admin->id,
            'pin' => '123456',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.refund_amount', 900)
        ->assertJsonPath('data.authorization_history.0.action', 'refund')
        ->assertJsonPath('data.authorization_history.0.initiator.id', $admin->id)
        ->assertJsonPath('data.authorization_history.0.authorizer.id', $admin->id)
        ->assertJsonPath('data.authorization_history.0.details.reason', 'Customer return');

    expect($transaction->fresh()->status)->toBe('refunded');
});

test('voiding a sale restores stock and records one inbound void movement per item', function () {
    $admin = transactionUser('admin');
    $admin->update(['authorization_pin' => Hash::make('123456')]);
    $cashier = transactionUser('staff');
    $transaction = transactionRecord($cashier);
    $productId = voidableTransactionItem($transaction, 3);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/transactions/{$transaction->id}/void", ['reason' => 'Cashier error', 'authorizer_id' => $admin->id, 'pin' => '123456'])
        ->assertOk()
        ->assertJsonPath('data.status', 'voided')
        ->assertJsonPath('data.authorization_history.0.action', 'void')
        ->assertJsonPath('data.authorization_history.0.authorizer.id', $admin->id)
        ->assertJsonPath('data.authorization_history.0.details.reason', 'Cashier error');

    expect(DB::table('inventory')->where('product_id', $productId)->value('quantity'))->toBe(8)
        ->and(DB::table('stock_movements')
            ->where('product_id', $productId)
            ->where('reference_type', 'void')
            ->where('reference_id', $transaction->id)
            ->where('movement_type', 'in')
            ->where('quantity', 3)
            ->where('user_id', $admin->id)
            ->count())->toBe(1);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/transactions/{$transaction->id}/void", ['reason' => 'Cashier error', 'authorizer_id' => $admin->id, 'pin' => '123456'])
        ->assertOk();

    expect(DB::table('inventory')->where('product_id', $productId)->value('quantity'))->toBe(8)
        ->and(DB::table('stock_movements')
            ->where('reference_type', 'void')
            ->where('reference_id', $transaction->id)
            ->count())->toBe(1);
});

test('each partial refund creates a separate authorization history event', function () {
    $staff = transactionUser('staff');
    $manager = transactionUser('manager');
    $manager->update(['authorization_pin' => Hash::make('123456')]);
    $transaction = transactionRecord($staff);
    $productId = voidableTransactionItem($transaction, 2);
    $salesItemId = DB::table('sales_items')->where('sales_transactions_id', $transaction->id)->value('id');
    DB::table('sales_items')->where('id', $salesItemId)->update([
        'unit_price' => 500,
        'unit_cost' => 300,
        'allocated_discount' => 100,
        'net_line_total' => 900,
        'refunded_line_amount' => 0,
    ]);

    foreach (['First returned item', 'Second returned item'] as $reason) {
        $this->actingAs($staff, 'api')->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'refund_type' => 'partial',
            'reason' => $reason,
            'refund_items' => [['sales_item_id' => $salesItemId, 'quantity' => 1]],
            'authorizer_id' => $manager->id,
            'pin' => '123456',
        ])->assertOk();
    }

    $this->actingAs($staff, 'api')->getJson("/api/v1/transactions/{$transaction->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.authorization_history')
        ->assertJsonPath('data.authorization_history.0.details.reason', 'First returned item')
        ->assertJsonPath('data.authorization_history.0.details.refund_amount', 450)
        ->assertJsonPath('data.authorization_history.1.details.reason', 'Second returned item')
        ->assertJsonPath('data.authorization_history.1.details.refund_amount', 450)
        ->assertJsonPath('data.authorization_history.0.initiator.id', $staff->id)
        ->assertJsonPath('data.authorization_history.0.authorizer.id', $manager->id);

    expect(DB::table('inventory')->where('product_id', $productId)->value('quantity'))->toBe(7)
        ->and(DB::table('transaction_authorizations')->where('sales_transaction_id', $transaction->id)->count())->toBe(2);
});
