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

test('weekly report period uses sunday through saturday instead of rolling seven days', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $user = reportsUserForRole();

    createSalesTransactionForDate($user, '2026-07-01 10:00:00', 100);
    createSalesTransactionForDate($user, '2026-07-05 10:00:00', 200);
    createSalesTransactionForDate($user, '2026-07-11 10:00:00', 300);
    createSalesTransactionForDate($user, '2026-07-12 10:00:00', 400);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/reports/sales?period=weekly')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_sales', 500)
        ->assertJsonPath('data.transactions', 2)
        ->assertJsonPath('data.trend.0.date', '2026-07-05')
        ->assertJsonPath('data.trend.1.date', '2026-07-11');
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
