<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\Settings\UpdateSystemSettingRequest;
use App\Http\Requests\Settings\RestoreSystemSettingRequest;
use App\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class SystemSettingController extends Controller
{
    protected $systemSettingService;

    public function __construct(SystemSettingService $systemSettingService)
    {
        $this->systemSettingService = $systemSettingService;
    }

    /**
     * Get global system configuration.
     *
     * @return JsonResponse
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
            Log::error('System Settings [GET] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update global system configuration.
     *
     * @param UpdateSystemSettingRequest $request
     * @return JsonResponse
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
            Log::error('System Settings [PATCH] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Generate and download system backup.
     *
     * @return StreamedResponse|JsonResponse
     */
    public function backup(): StreamedResponse|JsonResponse
    {
        try {
            $path = $this->systemSettingService->backupDatabase();

            return Storage::download($path);
        } catch (Exception $e) {
            Log::error('System Settings [BACKUP] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Restore system from backup file.
     *
     * @param RestoreSystemSettingRequest $request
     * @return JsonResponse
     */
    public function restore(RestoreSystemSettingRequest $request): JsonResponse
    {
        $uploadedFile = $request->file('backup_file');
        $tempPath = null;

        try {
            // Store file temporarily
            $filename = 'restore_' . time() . '.' . $uploadedFile->getClientOriginalExtension();
            $tempPath = $uploadedFile->storeAs('temp_restores', $filename);

            $absolutePath = Storage::path($tempPath);

            $this->systemSettingService->restoreDatabase($absolutePath);
            // Cleanup
            if ($tempPath && Storage::exists($tempPath)) {
                Storage::delete($tempPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'System restored successfully.',
            ], 201);
        } catch (Exception $e) {
            // Attempt cleanup on failure
            if ($tempPath && Storage::exists($tempPath)) {
                Storage::delete($tempPath);
            }

            Log::error('System Settings [RESTORE] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
