<?php

use App\Exceptions\DatabaseBackupException;
use App\Services\ActivityLogService;
use App\Services\DatabaseBackupService;
use App\Services\PostgresProcessRunner;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    config()->set('database.default', 'pgsql');
    config()->set('database.connections.pgsql', [
        'host' => '127.0.0.1',
        'port' => '5432',
        'username' => 'postgres',
        'password' => 'secret',
        'database' => 'motomedic',
    ]);
    config()->set('backup.disk', 'local');
    config()->set('backup.directory', 'backups');
    config()->set('backup.pg_dump_binary', '/bin/true');
    config()->set('backup.pg_restore_binary', '/bin/true');
});

test('backup service creates a private custom dump without exposing the password in arguments', function () {
    $process = Mockery::mock(Process::class);
    $process->shouldReceive('isSuccessful')->andReturnTrue();
    $runner = Mockery::mock(PostgresProcessRunner::class);
    $runner->shouldReceive('run')->once()->withArgs(function (array $command, array $environment, int $timeout) {
        $fileArgument = collect($command)->first(fn (string $argument) => str_starts_with($argument, '--file='));
        file_put_contents(substr($fileArgument, 7), 'PGDMP-test-archive');

        expect($command)->toContain('--format=custom')
            ->and($command)->not->toContain('secret')
            ->and($environment['PGPASSWORD'])->toBe('secret')
            ->and($timeout)->toBe(300);

        return true;
    })->andReturn($process);
    $activity = Mockery::mock(ActivityLogService::class)->shouldIgnoreMissing();

    $metadata = (new DatabaseBackupService($runner, $activity))->createBackup();

    expect($metadata['filename'])->toMatch('/^backup-.*\.dump$/')
        ->and($metadata['type'])->toBe('manual')
        ->and($metadata['size_bytes'])->toBeGreaterThan(5);
    Storage::disk('local')->assertExists('backups/'.$metadata['filename']);
});

test('backup validation rejects a renamed non PostgreSQL file before running pg_restore', function () {
    $path = Storage::disk('local')->path('temp/fake.dump');
    Storage::disk('local')->put('temp/fake.dump', 'not-a-postgres-dump');
    $runner = Mockery::mock(PostgresProcessRunner::class);
    $runner->shouldNotReceive('run');
    $activity = Mockery::mock(ActivityLogService::class)->shouldIgnoreMissing();

    expect(fn () => (new DatabaseBackupService($runner, $activity))->validateDump($path))
        ->toThrow(DatabaseBackupException::class, 'Only PostgreSQL custom-format .dump backups are supported.');
});
