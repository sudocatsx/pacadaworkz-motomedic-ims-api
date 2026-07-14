<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;

class SystemSettingService
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Get all system settings as key-value pairs.
     */
    public function getAllSettings(): array
    {
        return SystemSetting::all()->pluck('setting_value', 'setting_key')->toArray();
    }

    /**
     * Bulk update or create system settings.
     */
    public function updateSettings(array $settings): Collection
    {
        $updatedSettings = collect();

        foreach ($settings as $key => $value) {
            $setting = SystemSetting::where('setting_key', $key)->first();
            $action = $setting ? 'Updated' : 'Created';
            $oldValue = $setting ? $setting->setting_value : 'N/A';

            $setting = SystemSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
            $updatedSettings->push($setting);

            $this->activityLogService->log(
                'System Setting',
                $action,
                "{$action} setting '{$key}' from '{$oldValue}' to '{$value}'"
            );
        }

        return $updatedSettings;
    }

    /**
     * Delete a system setting.
     */
    public function deleteSetting(string $key): bool
    {
        $setting = SystemSetting::where('setting_key', $key)->first();

        if ($setting) {
            $setting->delete();
            $this->activityLogService->log(
                'System Setting',
                'Deleted',
                "Deleted setting '{$key}' with value '{$setting->setting_value}'"
            );

            return true;
        }

        return false;
    }
}
