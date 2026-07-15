<?php

namespace App\Http\Controllers\API;

use App\Exceptions\DatabaseBackupException;
use App\Http\Requests\Settings\RestoreSystemSettingRequest;
use App\Http\Requests\Settings\UpdateSystemSettingRequest;
use App\Http\Requests\Settings\ValidateDatabaseBackupRequest;
use App\Services\DatabaseBackupService;
use App\Services\SystemSettingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemSettingController extends Controller
{
    protected $systemSettingService;

    public function __construct(
        SystemSettingService $systemSettingService,
        private readonly DatabaseBackupService $databaseBackupService,
    ) {
        $this->systemSettingService = $systemSettingService;
    }

    public function database(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->databaseBackupService->status()]);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        }
    }

    public function createBackup(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Database backup created successfully.',
                'data' => $this->databaseBackupService->createBackup(),
            ], 201);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        }
    }

    public function downloadBackup(string $filename): StreamedResponse|JsonResponse
    {
        try {
            $path = $this->databaseBackupService->backupPath($filename);

            return Storage::disk((string) config('backup.disk'))->download($path, $filename, [
                'Content-Type' => 'application/octet-stream',
            ]);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        }
    }

    public function deleteBackup(string $filename): JsonResponse
    {
        try {
            $this->databaseBackupService->deleteBackup($filename);

            return response()->json(null, 204);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        }
    }

    public function validateBackup(ValidateDatabaseBackupRequest $request): JsonResponse
    {
        $path = null;

        try {
            $path = $this->storeTemporaryBackup($request->file('backup_file'));

            return response()->json([
                'success' => true,
                'message' => 'The PostgreSQL backup is valid.',
                'data' => $this->databaseBackupService->validateDump(Storage::disk((string) config('backup.disk'))->path($path)),
            ]);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        } finally {
            if ($path) {
                Storage::disk((string) config('backup.disk'))->delete($path);
            }
        }
    }

    /**
     * Get global system configuration.
     */
    public function index(): JsonResponse
    {
        try {
            $settings = $this->systemSettingService->getAllSettings();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (Exception $e) {
            Log::error('System Settings [GET] Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update global system configuration.
     */
    public function update(UpdateSystemSettingRequest $request): JsonResponse
    {
        try {
            $updatedSettings = $this->systemSettingService->updateSettings($request->validated()['settings']);

            return response()->json([
                'success' => true,
                'message' => 'System settings updated successfully.',
                'data' => $updatedSettings,
            ]);
        } catch (Exception $e) {
            Log::error('System Settings [PATCH] Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Generate and download system backup.
     */
    public function backup(): StreamedResponse|JsonResponse
    {
        try {
            $metadata = $this->databaseBackupService->createBackup();
            $path = $this->databaseBackupService->backupPath($metadata['filename']);

            return Storage::disk((string) config('backup.disk'))->download($path, $metadata['filename']);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        }
    }

    /**
     * Restore system from backup file.
     */
    public function restore(RestoreSystemSettingRequest $request): JsonResponse
    {
        $tempPath = null;

        try {
            $tempPath = $this->storeTemporaryBackup($request->file('backup_file'));
            $result = $this->databaseBackupService->restore(
                Storage::disk((string) config('backup.disk'))->path($tempPath)
            );

            return response()->json([
                'success' => true,
                'message' => 'Database restored successfully. Sign in again to continue.',
                'data' => $result,
            ]);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        } finally {
            if ($tempPath) {
                Storage::disk((string) config('backup.disk'))->delete($tempPath);
            }
        }
    }

    private function storeTemporaryBackup(UploadedFile $uploadedFile): string
    {
        $path = $uploadedFile->storeAs(
            trim((string) config('backup.temporary_directory'), '/'),
            'database-'.Str::uuid().'.dump',
            (string) config('backup.disk')
        );

        if (! $path) {
            throw new DatabaseBackupException('The uploaded backup could not be stored.', 'backup_upload_failed');
        }

        return $path;
    }

    private function databaseError(DatabaseBackupException $exception): JsonResponse
    {
        Log::warning('Database operation failed.', [
            'error_code' => $exception->errorCode,
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'error_code' => $exception->errorCode,
        ], $exception->httpStatus);
    }
}
