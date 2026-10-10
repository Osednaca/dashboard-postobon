<?php

return [
    'enabled' => env('MEDIA_PREVIEW_ENABLED', true),
    'ffmpeg' => env('MEDIA_PREVIEW_FFMPEG', 'ffmpeg'),
    'ffprobe' => env('MEDIA_PREVIEW_FFPROBE', 'ffprobe'),
    'queue' => 'previews',
    'process_timeout' => 120,
    'download_timeout' => 120,
    'max_source_bytes' => 250 * 1024 * 1024,
    'remote_refresh_seconds' => 3600,
    'retry_seconds' => 600,
    'retention_days' => 7,
];
