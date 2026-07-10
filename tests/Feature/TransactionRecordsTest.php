<?php

use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

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

test('transaction records return filters pagination and filtered kpis', function () {
    $admin = transactionUser('admin');
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
    $cashier = transactionUser('staff');
    $transaction = transactionRecord($cashier);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'refund_type' => 'full',
            'reason' => 'Customer return',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.refund_amount', 900);

    expect($transaction->fresh()->status)->toBe('refunded');
});
