<?php

return [
    'driver' => env('DATABASE_BACKUP_DRIVER', 'github_r2'),
    'disk' => env('DATABASE_BACKUP_DISK', 'r2_backups'),
    'prefix' => trim(env('DATABASE_BACKUP_PREFIX', 'database'), '/'),

    'manual_monthly_limit' => (int) env('DATABASE_BACKUP_MONTHLY_LIMIT', 5),
    'restore_monthly_limit' => (int) env('DATABASE_RESTORE_MONTHLY_LIMIT', 10),
    'manual_storage_limit' => (int) env('DATABASE_BACKUP_STORAGE_LIMIT', 5),
    'safety_storage_limit' => (int) env('DATABASE_SAFETY_BACKUP_STORAGE_LIMIT', 5),
    'download_url_ttl_minutes' => (int) env('DATABASE_BACKUP_DOWNLOAD_TTL_MINUTES', 5),
    'queued_timeout_minutes' => (int) env('DATABASE_OPERATION_QUEUED_TIMEOUT_MINUTES', 20),
    'running_timeout_minutes' => (int) env('DATABASE_OPERATION_RUNNING_TIMEOUT_MINUTES', 120),

    'github' => [
        'repository' => env('DATABASE_GITHUB_REPOSITORY'),
        'token' => env('DATABASE_GITHUB_TOKEN'),
        'ref' => env('DATABASE_GITHUB_REF', 'master'),
        'api_url' => rtrim(env('DATABASE_GITHUB_API_URL', 'https://api.github.com'), '/'),
        'backup_workflow' => env('DATABASE_BACKUP_WORKFLOW', 'database-backup.yml'),
        'restore_workflow' => env('DATABASE_RESTORE_WORKFLOW', 'database-restore.yml'),
    ],
];
