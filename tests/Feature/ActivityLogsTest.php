<?php

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

function activityLogsUserForRole(string $roleName = 'admin'): User
{
    $role = Role::where('role_name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}

function grantActivityLogsPermissions(Role $role, array $permissionNames): void
{
    $permissionIds = Permission::query()
        ->where('module', 'Activity Logs')
        ->whereIn('name', $permissionNames)
        ->pluck('id');

    $role->permissions()->syncWithoutDetaching($permissionIds);
    $role->load('permissions');
}

function createActivityLogForTest(User $user, array $overrides = []): ActivityLog
{
    $timestamp = $overrides['created_at'] ?? Carbon::now();

    return ActivityLog::unguarded(fn () => ActivityLog::create(array_merge([
        'user_id' => $user->id,
        'module' => 'Products',
        'action' => 'Create',
        'description' => 'Created product SKU-1',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ], $overrides)));
}

test('view own user can list only their activity logs', function () {
    $ownUser = activityLogsUserForRole('staff');
    grantActivityLogsPermissions($ownUser->role, ['View Own']);
    $otherUser = activityLogsUserForRole('admin');

    createActivityLogForTest($ownUser, ['description' => 'Own log']);
    createActivityLogForTest($otherUser, ['description' => 'Other log']);

    $this->actingAs($ownUser, 'api')
        ->getJson('/api/v1/activity-logs')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.details', 'Own log')
        ->assertJsonMissingPath('meta.filter_options.users');
});

test('view own user cannot use user_id to view another users logs', function () {
    $ownUser = activityLogsUserForRole('staff');
    grantActivityLogsPermissions($ownUser->role, ['View Own']);
    $otherUser = activityLogsUserForRole('admin');

    createActivityLogForTest($ownUser, ['description' => 'Own log']);
    createActivityLogForTest($otherUser, ['description' => 'Other log']);

    $this->actingAs($ownUser, 'api')
        ->getJson("/api/v1/activity-logs?user_id={$otherUser->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.details', 'Own log');
});

test('view all user can filter activity logs by user_id', function () {
    $admin = activityLogsUserForRole('admin');
    $targetUser = activityLogsUserForRole('staff');
    grantActivityLogsPermissions($targetUser->role, ['View Own']);

    createActivityLogForTest($targetUser, ['description' => 'Target user log']);
    createActivityLogForTest($admin, ['description' => 'Admin log']);

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/activity-logs?user_id={$targetUser->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.details', 'Target user log')
        ->assertJsonStructure([
            'meta' => [
                'filter_options' => [
                    'users' => [
                        '*' => ['id', 'name'],
                    ],
                ],
            ],
        ]);
});

test('activity log filters work for module action search and periods', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00'));

    $admin = activityLogsUserForRole('admin');

    createActivityLogForTest($admin, [
        'module' => 'Products',
        'action' => 'Create',
        'description' => 'Created helmet',
        'created_at' => Carbon::parse('2026-07-08 09:00:00'),
    ]);
    createActivityLogForTest($admin, [
        'module' => 'Inventory',
        'action' => 'Update',
        'description' => 'Adjusted stock',
        'created_at' => Carbon::parse('2026-07-02 09:00:00'),
    ]);
    createActivityLogForTest($admin, [
        'module' => 'Purchases',
        'action' => 'Delete',
        'description' => 'Deleted order',
        'created_at' => Carbon::parse('2026-06-08 09:00:00'),
    ]);
    createActivityLogForTest($admin, [
        'module' => 'Brands',
        'action' => 'Create',
        'description' => 'Old brand log',
        'created_at' => Carbon::parse('2026-05-31 09:00:00'),
    ]);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?module=Products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.module', 'Products');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?action=Update')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'Update');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?search=helmet')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.details', 'Created helmet');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?period=today')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?period=last_7_days')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?period=last_month')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?period=all')
        ->assertOk()
        ->assertJsonCount(4, 'data');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?period=custom&start_date=2026-07-01&end_date=2026-07-03')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.details', 'Adjusted stock');
});

test('invalid custom date input returns validation error', function () {
    $admin = activityLogsUserForRole('admin');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/activity-logs?period=custom&start_date=2026-07-05&end_date=2026-07-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

test('export returns csv with active filters and no pagination', function () {
    $admin = activityLogsUserForRole('admin');

    createActivityLogForTest($admin, ['module' => 'Products', 'description' => 'First product']);
    createActivityLogForTest($admin, ['module' => 'Products', 'description' => 'Second product']);
    createActivityLogForTest($admin, ['module' => 'Inventory', 'description' => 'Inventory entry']);

    $response = $this->actingAs($admin, 'api')
        ->get('/api/v1/activity-logs/export?module=Products&per_page=1');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('First product')
        ->and($csv)->toContain('Second product')
        ->and($csv)->not->toContain('Inventory entry');
});

test('activity logs export requires activity logs export permission', function () {
    $user = activityLogsUserForRole('staff');
    grantActivityLogsPermissions($user->role, ['View Own']);
    $reportsExportId = Permission::query()
        ->where('module', 'Reports')
        ->where('name', 'Export')
        ->value('id');

    $user->role->permissions()->syncWithoutDetaching([$reportsExportId]);
    $user->role->load('permissions');

    $this->actingAs($user, 'api')
        ->get('/api/v1/activity-logs/export')
        ->assertStatus(403);
});
