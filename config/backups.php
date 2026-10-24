<?php

// Encrypted, signed database backups. docs/backups.md has the whole design.
// path is the container side of the BACKUP_HOST_PATH bind mount in compose.yaml.

return [
    'path' => env('BACKUP_PATH', '/backups'),
    'host_path' => env('BACKUP_HOST_PATH'),

    // age public keys (comma separated). The private identity never lives on this machine.
    'recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('BACKUP_AGE_RECIPIENTS', ''))))),

    'signing_key' => env('BACKUP_SIGNING_KEY', storage_path('app/private/backup-keys/signing.pem')),
    'verify_key' => env('BACKUP_VERIFY_KEY', storage_path('app/private/backup-keys/signing.pub.pem')),

    'keep_daily' => (int) env('BACKUP_KEEP_DAILY', 7),
    'keep_weekly' => (int) env('BACKUP_KEEP_WEEKLY', 4),
    'keep_manual' => (int) env('BACKUP_KEEP_MANUAL', 10),

    'daily_at' => env('BACKUP_DAILY_AT', '03:00'),
    'timezone' => 'Asia/Baghdad',
    'stale_after_hours' => 24,
    // When the sitewide banner and the Backups page call a missing success a problem. Longer than
    // stale_after_hours on purpose: --if-stale already catches up after 24h, so the banner only
    // speaks once a whole day has been missed.
    'attention_after_hours' => 48,

    // The one account that sees the Backups page (its username). Unset means nobody does.
    'owner' => env('BACKUP_OWNER'),

    // Where the age identity is plugged in for a restore. Only shown in the restore commands.
    'restore_identity' => env('BACKUP_RESTORE_IDENTITY', '/mnt/e/baradox-stuff.age'),

    // The SSD folders the Backups page counts in its coverage note: none of them is in a dump.
    'media_folders' => ['photos', 'videos', 'video-posters', 'thumbnails'],

    // Dumps and restores run as root: the app user lacks PROCESS, which mysqldump needs.
    // compose.yaml sets the root password to DB_PASSWORD.
    'db_username' => env('BACKUP_DB_USERNAME', 'root'),
    'db_password' => env('BACKUP_DB_PASSWORD', env('DB_PASSWORD')),

    // Left out of every dump, so a restore never rewinds the record of backups and restores.
    'ignore_tables' => ['backups', 'backup_restores'],
];
