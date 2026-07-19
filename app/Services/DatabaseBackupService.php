<?php

namespace App\Services;

use App\Exceptions\DatabaseBackupException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DatabaseBackupService
{
    public function __construct(
        private readonly DatabaseBackupStore $store,
        private readonly GitHubWorkflowDispatcher $dispatcher,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function status(): array
    {
        $this->ensurePostgres();
        $configured = $this->providerConfigured();
        $size = $this->databaseSize();
        $period = $this->period();
        $usage = $configured ? $this->store->usage($period) : ['manual_backups' => 0, 'restores' => 0];
        $backups = $configured ? $this->store->backups() : [];
        $active = $configured ? $this->store->activeOperation() : null;
        $manualCount = collect($backups)->where('type', 'manual')->count();
        $safetyCount = collect($backups)->where('type', 'safety')->count();

        return [
            'database' => [
                'driver' => 'pgsql',
                'size_bytes' => $size,
            ],
            'provider' => [
                'driver' => 'github_r2',
                'configured' => $configured,
                'label' => 'Managed cloud recovery',
            ],
            'tools' => [
                'backup_available' => $configured,
                'restore_available' => $configured,
            ],
            'quotas' => [
                'period' => $period,
                'manual_backups' => $this->quota((int) ($usage['manual_backups'] ?? 0), (int) config('backup.manual_monthly_limit')),
                'restores' => $this->quota((int) ($usage['restores'] ?? 0), (int) config('backup.restore_monthly_limit')),
                'storage' => [
                    'manual_count' => $manualCount,
                    'manual_limit' => (int) config('backup.manual_storage_limit'),
                    'safety_count' => $safetyCount,
                    'safety_limit' => (int) config('backup.safety_storage_limit'),
                ],
            ],
            'active_operation' => $active,
            'summary' => [
                'backup_count' => count($backups),
                'last_backup_at' => $backups[0]['created_at'] ?? null,
            ],
            'backups' => $backups,
        ];
    }

    public function createBackup(User $actor): array
    {
        $this->ensureReady();

        return $this->withOperationLock(function () use ($actor) {
            $usage = $this->store->usage($this->period());
            $backups = $this->store->backups();

            $this->assertNoActiveOperation();
            $this->assertBelowLimit(
                (int) ($usage['manual_backups'] ?? 0),
                (int) config('backup.manual_monthly_limit'),
                'The monthly manual backup limit has been reached.',
                'backup_monthly_limit_reached'
            );
            $this->assertBelowLimit(
                collect($backups)->where('type', 'manual')->count(),
                (int) config('backup.manual_storage_limit'),
                'Delete a stored manual backup before creating another one.',
                'backup_storage_limit_reached'
            );

            $operation = $this->newOperation('backup', $actor);
            $this->store->putOperation($operation);

            try {
                $this->dispatcher->dispatch((string) config('backup.github.backup_workflow'), [
                    'operation_id' => $operation['id'],
                    'operation_key' => $operation['operation_key'],
                    'period' => $operation['period'],
                ]);
            } catch (DatabaseBackupException $exception) {
                $this->failOperation($operation, $exception->getMessage());
                throw $exception;
            }

            $this->safeLog('Backup Queued', 'A manual database backup was queued.');

            return $operation;
        });
    }

    public function restore(string $filename, User $actor): array
    {
        $this->ensureReady();

        return $this->withOperationLock(function () use ($filename, $actor) {
            $backup = $this->store->resolveBackup($filename);
            if (! ($backup['verified'] ?? false)) {
                throw new DatabaseBackupException('Only a verified backup from backup history can be restored.', 'backup_not_verified', 422);
            }
            $usage = $this->store->usage($this->period());
            $backups = $this->store->backups();

            $this->assertNoActiveOperation();
            $this->assertBelowLimit(
                (int) ($usage['restores'] ?? 0),
                (int) config('backup.restore_monthly_limit'),
                'The monthly database restore limit has been reached.',
                'restore_monthly_limit_reached'
            );
            $this->assertBelowLimit(
                collect($backups)->where('type', 'safety')->count(),
                (int) config('backup.safety_storage_limit'),
                'Delete a stored safety backup before starting another restore.',
                'safety_backup_storage_limit_reached'
            );

            $operation = $this->newOperation('restore', $actor, $backup);
            $this->store->putOperation($operation);

            try {
                $this->dispatcher->dispatch((string) config('backup.github.restore_workflow'), [
                    'operation_id' => $operation['id'],
                    'operation_key' => $operation['operation_key'],
                    'period' => $operation['period'],
                    'backup_key' => $backup['key'],
                ]);
            } catch (DatabaseBackupException $exception) {
                $this->failOperation($operation, $exception->getMessage());
                throw $exception;
            }

            $this->safeLog('Restore Queued', "Database restore queued from {$filename}.");

            return $operation;
        });
    }

    public function operation(string $id): array
    {
        $this->ensureReady();

        return $this->store->operation($id);
    }

    public function downloadBackup(string $filename): array
    {
        $this->ensureReady();

        return $this->store->temporaryDownload($this->store->resolveBackup($filename));
    }

    public function deleteBackup(string $filename): void
    {
        $this->ensureReady();

        $this->withOperationLock(function () use ($filename) {
            $backup = $this->store->resolveBackup($filename);
            $active = $this->store->activeOperation();

            if ($active) {
                throw new DatabaseBackupException('Wait for the active database operation before deleting a backup.', 'database_operation_busy', 409);
            }

            $this->store->deleteBackup($backup);
            $this->safeLog('Backup Deleted', "Cloud database backup deleted: {$filename}");
        });
    }

    private function newOperation(string $type, User $actor, ?array $backup = null): array
    {
        $id = (string) Str::uuid();
        $period = $this->period();
        $now = now()->toIso8601String();

        return array_filter([
            'id' => $id,
            'type' => $type,
            'status' => 'queued',
            'period' => $period,
            'operation_key' => $this->store->operationPath($period, $id),
            'backup_key' => $backup['key'] ?? null,
            'filename' => $backup['filename'] ?? null,
            'requested_by' => [
                'id' => $actor->getKey(),
                'email' => $actor->email,
            ],
            'created_at' => $now,
            'updated_at' => $now,
            'message' => $type === 'backup' ? 'Backup is queued.' : 'Restore is queued.',
        ], fn ($value) => $value !== null);
    }

    private function failOperation(array $operation, string $message): void
    {
        try {
            $this->store->putOperation(array_merge($operation, [
                'status' => 'failed',
                'message' => $message,
                'finished_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ]));
        } catch (Throwable $exception) {
            Log::warning('Failed database operation metadata could not be updated.');
        }
    }

    private function quota(int $used, int $limit): array
    {
        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
        ];
    }

    private function assertNoActiveOperation(): void
    {
        if ($this->store->activeOperation()) {
            throw new DatabaseBackupException('Another database operation is already running.', 'database_operation_busy', 409);
        }
    }

    private function assertBelowLimit(int $used, int $limit, string $message, string $code): void
    {
        if ($used >= $limit) {
            throw new DatabaseBackupException($message, $code, 409);
        }
    }

    private function ensureReady(): void
    {
        $this->ensurePostgres();

        if (! $this->providerConfigured()) {
            throw new DatabaseBackupException('Database recovery is not configured.', 'backup_provider_unconfigured', 503);
        }
    }

    private function providerConfigured(): bool
    {
        return $this->store->configured()
            && filled(config('backup.github.repository'))
            && filled(config('backup.github.token'))
            && filled(config('backup.github.ref'));
    }

    private function ensurePostgres(): void
    {
        if (config('database.default') !== 'pgsql') {
            throw new DatabaseBackupException('Database backup is supported only for PostgreSQL.', 'unsupported_database', 422);
        }
    }

    private function databaseSize(): int
    {
        try {
            return (int) (DB::selectOne('SELECT pg_database_size(current_database()) AS size')->size ?? 0);
        } catch (Throwable $exception) {
            Log::error('Database status could not be read.', ['exception' => $exception]);
            throw new DatabaseBackupException('Database status is currently unavailable.', 'database_unavailable', 503);
        }
    }

    private function period(): string
    {
        return now('Asia/Manila')->format('Y-m');
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

    private function safeLog(string $action, string $description): void
    {
        try {
            $this->activityLogService->log('Database', $action, $description);
        } catch (Throwable $exception) {
            Log::warning('Database activity log could not be written.', ['action' => $action]);
        }
    }
}
