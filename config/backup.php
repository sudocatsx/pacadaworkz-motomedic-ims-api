<?php

return [
    'disk' => env('DATABASE_BACKUP_DISK', 'local'),
    'directory' => env('DATABASE_BACKUP_DIRECTORY', 'backups'),
    'temporary_directory' => env('DATABASE_RESTORE_TEMP_DIRECTORY', 'temp_restores'),
    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
    'pg_restore_binary' => env('PG_RESTORE_BINARY', 'pg_restore'),
    'timeout' => (int) env('DATABASE_BACKUP_TIMEOUT', 300),
    'max_upload_kb' => (int) env('DATABASE_BACKUP_MAX_UPLOAD_KB', 512000),
];
