<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nube privada (reemplazo de la nube china Z2)
    |--------------------------------------------------------------------------
    |
    | URL base de la API REST de la nube privada (fan-private-cloud) y token
    | de acceso opcional (variable API_TOKEN del servidor Node).
    |
    */

    'base_url' => env('PRIVATE_CLOUD_URL', 'http://127.0.0.1:8080'),
    'token' => env('PRIVATE_CLOUD_TOKEN', ''),
    'timeout' => env('PRIVATE_CLOUD_TIMEOUT', 60),
    'connect_timeout' => env('PRIVATE_CLOUD_CONNECT_TIMEOUT', 10),
];
