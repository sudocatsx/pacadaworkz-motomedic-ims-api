<?php

use App\Models\Role;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function databaseActor(string $role): User
{
    return User::factory()->create([
        'role_id' => Role::where('role_name', $role)->value('id'),
    ]);
}

test('database status requires the exact manage database permission', function () {
    $service = Mockery::mock(DatabaseBackupService::class);
    $service->shouldReceive('status')->once()->andReturn([
        'database' => ['driver' => 'pgsql', 'size_bytes' => 2048],
        'provider' => ['driver' => 'github_r2', 'configured' => true],
        'quotas' => ['period' => '2026-07'],
        'summary' => ['backup_count' => 1, 'last_backup_at' => '2026-07-14T10:00:00+08:00'],
        'backups' => [],
    ]);
    $this->app->instance(DatabaseBackupService::class, $service);

    $this->actingAs(databaseActor('admin'), 'api')
        ->getJson('/api/v1/settings/system/database')
        ->assertOk()
        ->assertJsonPath('data.database.driver', 'pgsql')
        ->assertJsonPath('data.provider.driver', 'github_r2')
        ->assertJsonPath('data.summary.backup_count', 1);

    $this->actingAs(databaseActor('staff'), 'api')
        ->getJson('/api/v1/settings/system/database')
        ->assertForbidden();
});

test('creating a backup queues an asynchronous operation', function () {
    $service = Mockery::mock(DatabaseBackupService::class);
    $service->shouldReceive('createBackup')->once()->with(Mockery::type(User::class))->andReturn([
        'id' => '01911111-1111-7111-8111-111111111111',
        'type' => 'backup',
        'status' => 'queued',
        'period' => '2026-07',
    ]);
    $this->app->instance(DatabaseBackupService::class, $service);

    $this->actingAs(databaseActor('admin'), 'api')
        ->postJson('/api/v1/settings/system/backups')
        ->assertAccepted()
        ->assertJsonPath('data.type', 'backup')
        ->assertJsonPath('data.status', 'queued');
});

test('restore accepts only a history filename current password and exact phrase', function () {
    $service = Mockery::mock(DatabaseBackupService::class);
    $service->shouldReceive('restore')
        ->once()
        ->with('backup-2026-07-14_11-00-00.dump', Mockery::type(User::class))
        ->andReturn([
            'id' => '01922222-2222-7222-8222-222222222222',
            'type' => 'restore',
            'status' => 'queued',
        ]);
    $this->app->instance(DatabaseBackupService::class, $service);
    $admin = databaseActor('admin');

    $this->actingAs($admin, 'api')->postJson('/api/v1/settings/system/restore', [
        'filename' => 'backup-2026-07-14_11-00-00.dump',
        'password' => 'password',
        'confirmation' => 'RESTORE DATABASE',
    ])->assertAccepted()
        ->assertJsonPath('data.type', 'restore')
        ->assertJsonPath('data.status', 'queued');
});

test('restore rejects arbitrary filenames and invalid confirmation before dispatch', function () {
    $service = Mockery::mock(DatabaseBackupService::class);
    $service->shouldNotReceive('restore');
    $this->app->instance(DatabaseBackupService::class, $service);
    $admin = databaseActor('admin');

    $this->actingAs($admin, 'api')->postJson('/api/v1/settings/system/restore', [
        'filename' => '../../untrusted.dump',
        'password' => 'wrong-password',
        'confirmation' => 'restore database',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['filename', 'password', 'confirmation']);
});

test('operation status stays behind manage database permission', function () {
    $service = Mockery::mock(DatabaseBackupService::class);
    $service->shouldReceive('operation')->once()->andReturn([
        'id' => '01933333-3333-7333-8333-333333333333',
        'type' => 'backup',
        'status' => 'in_progress',
    ]);
    $this->app->instance(DatabaseBackupService::class, $service);

    $this->actingAs(databaseActor('admin'), 'api')
        ->getJson('/api/v1/settings/system/operations/01933333-3333-7333-8333-333333333333')
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');
});
