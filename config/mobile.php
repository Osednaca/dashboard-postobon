<?php

return [
    'token_days' => (int) env('MOBILE_TOKEN_DAYS', 7),
    'status_cache_seconds' => 15,
    'status_stale_seconds' => 180,
];
