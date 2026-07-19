<?php

use App\Exceptions\DatabaseBackupException;
use App\Services\DatabaseBackupStore;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    config()->set('backup.disk', 'local');
    config()->set('backup.prefix', 'database');
    config()->set('backup.queued_timeout_minutes', 20);
    config()->set('backup.running_timeout_minutes', 120);
});

test('history contains only dumps with trusted checksum sidecars', function () {
    $key = 'database/manual/backup-2026-07-19_10-00-00.dump';
    Storage::disk('local')->put($key, 'PGDMP-archive');
    Storage::disk('local')->put($key.'.json', json_encode([
        'key' => $key,
        'filename' => basename($key),
        'type' => 'manual',
        'size_bytes' => 13,
        'created_at' => '2026-07-19T02:00:00Z',
        'sha256' => 'checksum',
        'verified' => true,
    ]));
    Storage::disk('local')->put('database/manual/untrusted.dump', 'not-listed');

    $backups = (new DatabaseBackupStore)->backups();

    expect($backups)->toHaveCount(1)
        ->and($backups[0]['filename'])->toBe(basename($key))
        ->and($backups[0]['verified'])->toBeTrue();
});

test('history lookup rejects path traversal and missing objects', function () {
    $store = new DatabaseBackupStore;

    expect(fn () => $store->resolveBackup('../../backup-2026-07-19_10-00-00.dump'))
        ->toThrow(DatabaseBackupException::class, 'The backup filename is invalid.')
        ->and(fn () => $store->resolveBackup('backup-2026-07-19_10-00-00.dump'))
        ->toThrow(DatabaseBackupException::class, 'The requested backup was not found in trusted backup history.');
});

test('only fresh queued or running operations block another operation', function () {
    $freshId = '01944444-4444-7444-8444-444444444444';
    $staleId = '01955555-5555-7555-8555-555555555555';
    Storage::disk('local')->put("database/operations/2026-07/{$staleId}.json", json_encode([
        'id' => $staleId,
        'status' => 'queued',
        'created_at' => now()->subMinutes(30)->toIso8601String(),
    ]));
    Storage::disk('local')->put("database/operations/2026-07/{$freshId}.json", json_encode([
        'id' => $freshId,
        'status' => 'in_progress',
        'started_at' => now()->subMinute()->toIso8601String(),
        'created_at' => now()->subMinutes(2)->toIso8601String(),
    ]));

    expect((new DatabaseBackupStore)->activeOperation()['id'])->toBe($freshId);
});
