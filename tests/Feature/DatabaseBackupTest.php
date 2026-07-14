<?php

use App\Models\Role;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;

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
        'tools' => ['backup_available' => true, 'restore_available' => true],
        'summary' => ['backup_count' => 1, 'last_backup_at' => '2026-07-14T10:00:00+08:00'],
        'backups' => [],
    ]);
    $this->app->instance(DatabaseBackupService::class, $service);

    $this->actingAs(databaseActor('admin'), 'api')
        ->getJson('/api/v1/settings/system/database')
        ->assertOk()
        ->assertJsonPath('data.database.driver', 'pgsql')
        ->assertJsonPath('data.summary.backup_count', 1);

    $this->actingAs(databaseActor('staff'), 'api')
        ->getJson('/api/v1/settings/system/database')
        ->assertForbidden();
});

test('creating a backup returns canonical dump metadata', function () {
    $service = Mockery::mock(DatabaseBackupService::class);
    $service->shouldReceive('createBackup')->once()->andReturn([
        'filename' => 'backup-2026-07-14_10-30-00.dump',
        'type' => 'manual',
        'size_bytes' => 113331,
        'created_at' => '2026-07-14T10:30:00+08:00',
    ]);
    $this->app->instance(DatabaseBackupService::class, $service);

    $this->actingAs(databaseActor('admin'), 'api')
        ->postJson('/api/v1/settings/system/backups')
        ->assertCreated()
        ->assertJsonPath('data.filename', 'backup-2026-07-14_10-30-00.dump')
        ->assertJsonPath('data.size_bytes', 113331);
});

test('restore requires a dump current password and exact confirmation phrase', function () {
    $service = Mockery::mock(DatabaseBackupService::class);
    $service->shouldReceive('restore')->once()->andReturn([
        'validation' => ['valid' => true],
        'safety_backup' => ['filename' => 'pre-restore-2026-07-14_11-00-00.dump'],
    ]);
    $this->app->instance(DatabaseBackupService::class, $service);
    $admin = databaseActor('admin');

    $this->actingAs($admin, 'api')->post('/api/v1/settings/system/restore', [
        'backup_file' => UploadedFile::fake()->create('recovery.dump', 10, 'application/octet-stream'),
        'password' => 'password',
        'confirmation' => 'RESTORE DATABASE',
    ])->assertOk()
        ->assertJsonPath('data.validation.valid', true)
        ->assertJsonPath('data.safety_backup.filename', 'pre-restore-2026-07-14_11-00-00.dump');
});

test('restore rejects unsupported files and invalid confirmation before invoking the service', function () {
    $service = Mockery::mock(DatabaseBackupService::class);
    $service->shouldNotReceive('restore');
    $this->app->instance(DatabaseBackupService::class, $service);
    $admin = databaseActor('admin');

    $this->actingAs($admin, 'api')->post('/api/v1/settings/system/restore', [
        'backup_file' => UploadedFile::fake()->create('backup.json', 10, 'application/json'),
        'password' => 'wrong-password',
        'confirmation' => 'restore database',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['backup_file', 'password', 'confirmation']);
});
