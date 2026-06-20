<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Z2 API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Z2 external API integration.
    |
    */

    'base_url' => env('Z2_BASE_URL', 'http://www.holographicdisplay.cn:8088'),
    'username' => env('Z2_USERNAME', ''),
    'password' => env('Z2_PASSWORD', ''),
    'rsa_public_key' => env('Z2_RSA_PUBLIC_KEY', ''),
    'timeout' => env('Z2_TIMEOUT', 30),
    'connect_timeout' => env('Z2_CONNECT_TIMEOUT', 10),
    'retries' => env('Z2_RETRIES', 3),
    'retry_delay' => [1, 5, 10],
    'session_cache_key' => 'z2_jsessionid',
    'session_cache_ttl' => 3600,
];
