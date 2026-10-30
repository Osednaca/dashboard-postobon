<?php

return [
    // The operator must mount a durable local disk before enabling backups.
    'backup_disk' => env('CAMPAIGN_BACKUP_DISK'),
    'backup_persistent' => env('CAMPAIGN_BACKUP_PERSISTENT', false),
];
