<?php

namespace App\Http\Controllers\API;

use App\Exceptions\DatabaseBackupException;
use App\Http\Requests\Settings\RestoreSystemSettingRequest;
use App\Http\Requests\Settings\UpdateSystemSettingRequest;
use App\Services\DatabaseBackupService;
use App\Services\SystemSettingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function createBackup(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Database backup queued. You can follow its progress here.',
                'data' => $this->databaseBackupService->createBackup($request->user()),
            ], 202);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        }
    }

    public function operation(string $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->databaseBackupService->operation($id),
            ]);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        }
    }

    public function downloadBackup(string $filename): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->databaseBackupService->downloadBackup($filename),
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

    public function restore(RestoreSystemSettingRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            return response()->json([
                'success' => true,
                'message' => 'Database restore queued. You will be signed out after it completes.',
                'data' => $this->databaseBackupService->restore(
                    $validated['filename'],
                    $request->user()
                ),
            ], 202);
        } catch (DatabaseBackupException $exception) {
            return $this->databaseError($exception);
        }
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
