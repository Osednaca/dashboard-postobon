<?php

return [
    'base_url' => env('UNIFIED_FLEET_API_URL', 'http://127.0.0.1:4173'),
    'token' => env('UNIFIED_FLEET_API_TOKEN', ''),
    'timeout' => (int) env('UNIFIED_FLEET_TIMEOUT', 30),
    'upload_timeout' => (int) env('UNIFIED_FLEET_UPLOAD_TIMEOUT', 900),
    'connect_timeout' => (int) env('UNIFIED_FLEET_CONNECT_TIMEOUT', 10),
];
