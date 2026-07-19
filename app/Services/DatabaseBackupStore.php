<?php

namespace App\Services;

use App\Exceptions\DatabaseBackupException;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DatabaseBackupStore
{
    private const BACKUP_PATTERN = '/^(backup|pre-restore)-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.dump$/';

    public function configured(): bool
    {
        $disk = config('filesystems.disks.'.config('backup.disk'));

        return config('backup.driver') === 'github_r2'
            && filled($disk['key'] ?? null)
            && filled($disk['secret'] ?? null)
            && filled($disk['bucket'] ?? null)
            && filled($disk['endpoint'] ?? null);
    }

    public function backups(): array
    {
        $records = [];

        try {
            foreach (['manual', 'safety'] as $type) {
                foreach ($this->disk()->files($this->path($type)) as $path) {
                    if (! str_ends_with($path, '.dump.json')) {
                        continue;
                    }

                    $metadata = $this->readJson($path);
                    $expectedKey = substr($path, 0, -5);
                    $key = (string) ($metadata['key'] ?? $expectedKey);
                    $filename = basename($key);

                    if (
                        ! hash_equals($expectedKey, $key)
                        || preg_match(self::BACKUP_PATTERN, $filename) !== 1
                        || ! $this->disk()->exists($key)
                    ) {
                        continue;
                    }

                    $records[] = [
                        'filename' => $filename,
                        'key' => $key,
                        'type' => $type,
                        'size_bytes' => (int) ($metadata['size_bytes'] ?? $this->disk()->size($key)),
                        'created_at' => $metadata['created_at'] ?? Carbon::createFromTimestampUTC($this->disk()->lastModified($key))->toIso8601String(),
                        'sha256' => $metadata['sha256'] ?? null,
                        'verified' => (bool) ($metadata['verified'] ?? false),
                    ];
                }
            }
        } catch (DatabaseBackupException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->storageException($exception);
        }

        usort($records, fn (array $left, array $right) => strcmp((string) $right['created_at'], (string) $left['created_at']));

        return $records;
    }

    public function resolveBackup(string $filename): array
    {
        $this->assertTrustedFilename($filename);
        $backup = collect($this->backups())->firstWhere('filename', $filename);

        if (! $backup) {
            throw new DatabaseBackupException('The requested backup was not found in trusted backup history.', 'backup_not_found', 404);
        }

        return $backup;
    }

    public function deleteBackup(array $backup): void
    {
        try {
            $deleted = $this->disk()->delete([$backup['key'], $backup['key'].'.json']);
        } catch (Throwable $exception) {
            throw $this->storageException($exception);
        }

        if (! $deleted) {
            throw new DatabaseBackupException('The backup could not be deleted.', 'backup_delete_failed');
        }
    }

    public function temporaryDownload(array $backup): array
    {
        $expiresAt = now()->addMinutes((int) config('backup.download_url_ttl_minutes', 5));

        try {
            $url = $this->disk()->temporaryUrl($backup['key'], $expiresAt, [
                'ResponseContentType' => 'application/octet-stream',
                'ResponseContentDisposition' => 'attachment; filename="'.$backup['filename'].'"',
            ]);
        } catch (Throwable $exception) {
            throw $this->storageException($exception);
        }

        return [
            'filename' => $backup['filename'],
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function usage(string $period): array
    {
        $path = $this->usagePath($period);
        $default = [
            'period' => $period,
            'manual_backups' => 0,
            'restores' => 0,
        ];

        try {
            if (! $this->disk()->exists($path)) {
                return $default;
            }

            return array_merge($default, $this->readJson($path));
        } catch (DatabaseBackupException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->storageException($exception);
        }
    }

    public function putOperation(array $operation): void
    {
        try {
            $written = $this->disk()->put(
                (string) $operation['operation_key'],
                json_encode($operation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
        } catch (Throwable $exception) {
            throw $this->storageException($exception);
        }

        if (! $written) {
            throw new DatabaseBackupException('The database operation could not be queued.', 'operation_storage_failed');
        }
    }

    public function operation(string $id): array
    {
        if (! Str::isUuid($id)) {
            throw new DatabaseBackupException('The database operation identifier is invalid.', 'invalid_operation_id', 422);
        }

        try {
            foreach ($this->disk()->allFiles($this->path('operations')) as $path) {
                if (basename($path) === $id.'.json') {
                    return $this->readJson($path);
                }
            }
        } catch (DatabaseBackupException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->storageException($exception);
        }

        throw new DatabaseBackupException('The requested database operation was not found.', 'operation_not_found', 404);
    }

    public function activeOperation(): ?array
    {
        try {
            $operations = collect($this->disk()->allFiles($this->path('operations')))
                ->filter(fn (string $path) => str_ends_with($path, '.json'))
                ->map(fn (string $path) => $this->readJson($path))
                ->filter(fn (array $operation) => $this->isActive($operation))
                ->sortByDesc('created_at');

            return $operations->first();
        } catch (DatabaseBackupException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->storageException($exception);
        }
    }

    public function operationPath(string $period, string $id): string
    {
        return $this->path("operations/{$period}/{$id}.json");
    }

    public function usagePath(string $period): string
    {
        return $this->path("control/usage-{$period}.json");
    }

    private function isActive(array $operation): bool
    {
        $status = $operation['status'] ?? null;
        if (! in_array($status, ['queued', 'in_progress'], true)) {
            return false;
        }

        $reference = $operation['started_at'] ?? $operation['created_at'] ?? null;
        if (! $reference) {
            return false;
        }

        $timeout = $status === 'queued'
            ? (int) config('backup.queued_timeout_minutes', 20)
            : (int) config('backup.running_timeout_minutes', 120);

        return Carbon::parse($reference)->greaterThan(now()->subMinutes($timeout));
    }

    private function readJson(string $path): array
    {
        try {
            $payload = json_decode($this->disk()->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            report($exception);
            throw new DatabaseBackupException('Stored database metadata is unreadable.', 'backup_metadata_invalid', 503);
        }

        if (! is_array($payload)) {
            throw new DatabaseBackupException('Stored database metadata is unreadable.', 'backup_metadata_invalid', 503);
        }

        return $payload;
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('backup.disk'));
    }

    private function path(string $suffix): string
    {
        return trim(config('backup.prefix').'/'.trim($suffix, '/'), '/');
    }

    private function assertTrustedFilename(string $filename): void
    {
        if (basename($filename) !== $filename || preg_match(self::BACKUP_PATTERN, $filename) !== 1) {
            throw new DatabaseBackupException('The backup filename is invalid.', 'invalid_backup_filename', 422);
        }
    }

    private function storageException(Throwable $exception): DatabaseBackupException
    {
        report($exception);

        return new DatabaseBackupException('Cloud backup storage is currently unavailable.', 'backup_storage_unavailable', 503);
    }
}
