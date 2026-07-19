<?php

use App\Exceptions\DatabaseBackupException;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseBackupStore;
use App\Services\GitHubWorkflowDispatcher;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'pgsql');
    config()->set('backup.github.repository', 'owner/repository');
    config()->set('backup.github.token', 'token');
    config()->set('backup.github.ref', 'master');
    config()->set('backup.github.backup_workflow', 'database-backup.yml');
    config()->set('backup.github.restore_workflow', 'database-restore.yml');
    config()->set('backup.manual_monthly_limit', 5);
    config()->set('backup.restore_monthly_limit', 10);
    config()->set('backup.manual_storage_limit', 5);
    config()->set('backup.safety_storage_limit', 5);
});

function backupActor(): User
{
    return (new User)->forceFill(['id' => 7, 'email' => 'admin@example.com']);
}

test('manual backup creates operation metadata and dispatches the configured workflow', function () {
    $store = Mockery::mock(DatabaseBackupStore::class);
    $store->shouldReceive('configured')->andReturnTrue();
    $store->shouldReceive('usage')->once()->andReturn(['manual_backups' => 0, 'restores' => 0]);
    $store->shouldReceive('backups')->once()->andReturn([]);
    $store->shouldReceive('activeOperation')->once()->andReturnNull();
    $store->shouldReceive('operationPath')->once()->andReturnUsing(fn (string $period, string $id) => "database/operations/{$period}/{$id}.json");
    $store->shouldReceive('putOperation')->once()->with(Mockery::on(fn (array $operation) => $operation['type'] === 'backup' && $operation['status'] === 'queued'));

    $dispatcher = Mockery::mock(GitHubWorkflowDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->with('database-backup.yml', Mockery::on(fn (array $inputs) => isset($inputs['operation_id'], $inputs['operation_key'], $inputs['period'])));
    $activity = Mockery::mock(ActivityLogService::class)->shouldIgnoreMissing();

    $operation = (new DatabaseBackupService($store, $dispatcher, $activity))->createBackup(backupActor());

    expect($operation['type'])->toBe('backup')
        ->and($operation['status'])->toBe('queued')
        ->and($operation['requested_by']['email'])->toBe('admin@example.com');
});

test('manual backup quota is enforced before workflow dispatch', function () {
    $store = Mockery::mock(DatabaseBackupStore::class);
    $store->shouldReceive('configured')->andReturnTrue();
    $store->shouldReceive('usage')->once()->andReturn(['manual_backups' => 5, 'restores' => 0]);
    $store->shouldReceive('backups')->once()->andReturn([]);
    $store->shouldReceive('activeOperation')->once()->andReturnNull();
    $dispatcher = Mockery::mock(GitHubWorkflowDispatcher::class);
    $dispatcher->shouldNotReceive('dispatch');
    $activity = Mockery::mock(ActivityLogService::class)->shouldIgnoreMissing();

    expect(fn () => (new DatabaseBackupService($store, $dispatcher, $activity))->createBackup(backupActor()))
        ->toThrow(DatabaseBackupException::class, 'The monthly manual backup limit has been reached.');
});

test('restore dispatches only the trusted R2 key resolved from history', function () {
    $backup = [
        'filename' => 'backup-2026-07-19_10-00-00.dump',
        'key' => 'database/manual/backup-2026-07-19_10-00-00.dump',
        'type' => 'manual',
        'verified' => true,
    ];
    $store = Mockery::mock(DatabaseBackupStore::class);
    $store->shouldReceive('configured')->andReturnTrue();
    $store->shouldReceive('resolveBackup')->once()->with($backup['filename'])->andReturn($backup);
    $store->shouldReceive('usage')->once()->andReturn(['manual_backups' => 1, 'restores' => 0]);
    $store->shouldReceive('backups')->once()->andReturn([$backup]);
    $store->shouldReceive('activeOperation')->once()->andReturnNull();
    $store->shouldReceive('operationPath')->once()->andReturnUsing(fn (string $period, string $id) => "database/operations/{$period}/{$id}.json");
    $store->shouldReceive('putOperation')->once();

    $dispatcher = Mockery::mock(GitHubWorkflowDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->with('database-restore.yml', Mockery::on(
        fn (array $inputs) => $inputs['backup_key'] === $backup['key']
    ));
    $activity = Mockery::mock(ActivityLogService::class)->shouldIgnoreMissing();

    $operation = (new DatabaseBackupService($store, $dispatcher, $activity))->restore($backup['filename'], backupActor());

    expect($operation['type'])->toBe('restore')
        ->and($operation['backup_key'])->toBe($backup['key']);
});
