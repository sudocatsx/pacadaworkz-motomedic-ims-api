<?php

namespace App\Services;

use App\Exceptions\DatabaseBackupException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class DatabaseBackupService
{
    private const BACKUP_PATTERN = '/^(backup|pre-restore)-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.dump$/';

    public function __construct(
        private readonly PostgresProcessRunner $runner,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function status(): array
    {
        $this->ensurePostgres();
        $backups = $this->backups();
        try {
            $size = (int) (DB::selectOne('SELECT pg_database_size(current_database()) AS size')->size ?? 0);
        } catch (Throwable $exception) {
            Log::error('Database status could not be read.', ['exception' => $exception]);
            throw new DatabaseBackupException('Database status is currently unavailable.', 'database_unavailable', 503);
        }

        return [
            'database' => [
                'driver' => 'pgsql',
                'size_bytes' => $size,
            ],
            'tools' => [
                'backup_available' => $this->binaryAvailable((string) config('backup.pg_dump_binary')),
                'restore_available' => $this->binaryAvailable((string) config('backup.pg_restore_binary')),
            ],
            'summary' => [
                'backup_count' => count($backups),
                'last_backup_at' => $backups[0]['created_at'] ?? null,
            ],
            'backups' => $backups,
        ];
    }

    public function backups(): array
    {
        $disk = $this->disk();
        $directory = $this->directory();

        return collect($disk->files($directory))
            ->filter(fn (string $path) => preg_match(self::BACKUP_PATTERN, basename($path)) === 1)
            ->map(fn (string $path) => $this->metadata($path))
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function createBackup(string $type = 'backup'): array
    {
        return $this->withOperationLock(fn () => $this->createBackupFile($type));
    }

    public function backupPath(string $filename): string
    {
        $this->assertTrustedFilename($filename);
        $path = $this->directory().'/'.$filename;

        if (! $this->disk()->exists($path)) {
            throw new DatabaseBackupException('The requested backup was not found.', 'backup_not_found', 404);
        }

        return $path;
    }

    public function deleteBackup(string $filename): void
    {
        $this->withOperationLock(function () use ($filename) {
            $path = $this->backupPath($filename);
            if (! $this->disk()->delete($path)) {
                throw new DatabaseBackupException('The backup could not be deleted.', 'backup_delete_failed');
            }
            $this->safeLog('Backup Deleted', "Database backup deleted: {$filename}");
        });
    }

    public function validateDump(string $absolutePath): array
    {
        return $this->withOperationLock(fn () => $this->validateDumpFile($absolutePath));
    }

    public function restore(string $absolutePath): array
    {
        return $this->withOperationLock(function () use ($absolutePath) {
            $validation = $this->validateDumpFile($absolutePath);
            $safetyBackup = $this->createBackupFile('pre-restore');
            $this->safeLog('Restore Initiated', 'Database restore initiated after a safety backup was created.');
            $maintenanceEnabled = false;

            try {
                Artisan::call('down', ['--retry' => 60]);
                $maintenanceEnabled = true;

                $process = $this->runner->run([
                    (string) config('backup.pg_restore_binary'),
                    '--host='.$this->connection()['host'],
                    '--port='.$this->connection()['port'],
                    '--username='.$this->connection()['username'],
                    '--dbname='.$this->connection()['database'],
                    '--clean',
                    '--if-exists',
                    '--no-owner',
                    '--no-privileges',
                    '--single-transaction',
                    $absolutePath,
                ], $this->environment(), (int) config('backup.timeout'));

                if (! $process->isSuccessful()) {
                    throw new DatabaseBackupException(
                        'The database restore failed. The original database was not replaced.',
                        'restore_failed'
                    );
                }

                DB::purge(config('database.default'));
                DB::reconnect(config('database.default'));
                Cache::flush();
                $this->safeLog('Restore Completed', 'Database restore completed successfully.');

                return [
                    'validation' => $validation,
                    'safety_backup' => $safetyBackup,
                ];
            } catch (DatabaseBackupException $exception) {
                $this->safeLog('Restore Failed', $exception->getMessage());
                throw $exception;
            } catch (Throwable $exception) {
                Log::error('Database restore failed.', ['exception' => $exception]);
                $this->safeLog('Restore Failed', 'Database restore failed unexpectedly.');
                throw new DatabaseBackupException(
                    'The database restore failed. The original database was not replaced.',
                    'restore_failed'
                );
            } finally {
                if ($maintenanceEnabled) {
                    try {
                        Artisan::call('up');
                    } catch (Throwable $exception) {
                        Log::critical('Application could not leave maintenance mode after database restore.', [
                            'exception' => $exception,
                        ]);
                    }
                }
            }
        });
    }

    private function createBackupFile(string $type): array
    {
        $this->ensurePostgres();
        $this->requireBinary((string) config('backup.pg_dump_binary'), 'backup_tool_unavailable');
        $type = $type === 'pre-restore' ? 'pre-restore' : 'backup';
        $filename = $type.'-'.now()->format('Y-m-d_H-i-s').'.dump';
        $path = $this->directory().'/'.$filename;
        $this->disk()->makeDirectory($this->directory());
        $absolutePath = $this->disk()->path($path);

        try {
            $process = $this->runner->run([
                (string) config('backup.pg_dump_binary'),
                '--host='.$this->connection()['host'],
                '--port='.$this->connection()['port'],
                '--username='.$this->connection()['username'],
                '--format=custom',
                '--no-owner',
                '--no-privileges',
                '--file='.$absolutePath,
                $this->connection()['database'],
            ], $this->environment(), (int) config('backup.timeout'));
        } catch (Throwable $exception) {
            $this->disk()->delete($path);
            Log::error('Database backup process could not run.', ['exception' => $exception]);
            throw new DatabaseBackupException('The database backup could not be created.', 'backup_failed');
        }

        if (! $process->isSuccessful() || ! $this->disk()->exists($path) || $this->disk()->size($path) === 0) {
            $this->disk()->delete($path);
            $this->safeLog('Backup Failed', 'Database backup creation failed.');
            throw new DatabaseBackupException('The database backup could not be created.', 'backup_failed');
        }

        $this->safeLog('Backup Created', "Database backup created: {$filename}");

        return $this->metadata($path);
    }

    private function validateDumpFile(string $absolutePath): array
    {
        $this->ensurePostgres();
        $this->requireBinary((string) config('backup.pg_restore_binary'), 'restore_tool_unavailable');

        if (! is_file($absolutePath) || filesize($absolutePath) < 5) {
            throw new DatabaseBackupException('The uploaded backup is empty or unreadable.', 'invalid_backup', 422);
        }

        $handle = fopen($absolutePath, 'rb');
        $header = $handle ? fread($handle, 5) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($header !== 'PGDMP') {
            throw new DatabaseBackupException('Only PostgreSQL custom-format .dump backups are supported.', 'invalid_backup', 422);
        }

        try {
            $process = $this->runner->run([
                (string) config('backup.pg_restore_binary'),
                '--list',
                $absolutePath,
            ], $this->environment(), (int) config('backup.timeout'));
        } catch (Throwable $exception) {
            Log::error('Database backup validation process could not run.', ['exception' => $exception]);
            throw new DatabaseBackupException('The uploaded backup could not be validated.', 'backup_validation_failed');
        }

        if (! $process->isSuccessful()) {
            $this->safeLog('Validation Failed', 'A database backup failed validation.');
            throw new DatabaseBackupException('The uploaded PostgreSQL backup is corrupt or incompatible.', 'invalid_backup', 422);
        }

        $output = $process->getOutput();
        preg_match('/Dumped from database version:\s*([^\r\n]+)/', $output, $databaseVersion);
        preg_match('/Dump Version:\s*([^\r\n]+)/', $output, $dumpVersion);

        return [
            'valid' => true,
            'format' => 'PostgreSQL custom dump',
            'size_bytes' => filesize($absolutePath),
            'database_version' => trim($databaseVersion[1] ?? 'Unknown'),
            'dump_version' => trim($dumpVersion[1] ?? 'Unknown'),
        ];
    }

    private function metadata(string $path): array
    {
        $filename = basename($path);

        return [
            'filename' => $filename,
            'type' => str_starts_with($filename, 'pre-restore-') ? 'safety' : 'manual',
            'size_bytes' => $this->disk()->size($path),
            'created_at' => now()->setTimestamp($this->disk()->lastModified($path))->toIso8601String(),
        ];
    }

    private function withOperationLock(callable $callback): mixed
    {
        $lockPath = storage_path('framework/database-maintenance.lock');
        $handle = fopen($lockPath, 'c+');
        if (! $handle || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new DatabaseBackupException('Another database operation is already running.', 'database_operation_busy', 409);
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function disk()
    {
        return Storage::disk((string) config('backup.disk'));
    }

    private function directory(): string
    {
        return trim((string) config('backup.directory'), '/');
    }

    private function connection(): array
    {
        return config('database.connections.'.config('database.default'));
    }

    private function environment(): array
    {
        return array_filter([
            'PGPASSWORD' => $this->connection()['password'] ?? null,
            'PGSSLMODE' => $this->connection()['sslmode'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function binaryAvailable(string $binary): bool
    {
        return $this->findBinary($binary) !== null;
    }

    private function requireBinary(string $binary, string $errorCode): void
    {
        if (! $this->binaryAvailable($binary)) {
            throw new DatabaseBackupException(
                $errorCode === 'backup_tool_unavailable'
                    ? 'The PostgreSQL backup tool is unavailable on the server.'
                    : 'The PostgreSQL restore tool is unavailable on the server.',
                $errorCode,
                503
            );
        }
    }

    private function findBinary(string $binary): ?string
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_executable($binary) ? $binary : null;
        }

        return (new ExecutableFinder)->find($binary);
    }

    private function ensurePostgres(): void
    {
        if (config('database.default') !== 'pgsql') {
            throw new DatabaseBackupException('Database backup is supported only for PostgreSQL.', 'unsupported_database', 422);
        }
    }

    private function assertTrustedFilename(string $filename): void
    {
        if (preg_match(self::BACKUP_PATTERN, $filename) !== 1 || basename($filename) !== $filename) {
            throw new DatabaseBackupException('The backup filename is invalid.', 'invalid_backup_filename', 422);
        }
    }

    private function safeLog(string $action, string $description): void
    {
        try {
            $this->activityLogService->log('Database', $action, $description);
        } catch (Throwable $exception) {
            Log::warning('Database activity log could not be written.', ['action' => $action]);
        }
    }
}
