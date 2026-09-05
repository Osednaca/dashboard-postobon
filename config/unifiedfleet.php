<?php

$privateCloudUrl = (string) env('PRIVATE_CLOUD_URL', 'http://127.0.0.1:8080');
$privateCloudParts = parse_url($privateCloudUrl) ?: [];
$gatewayScheme = $privateCloudParts['scheme'] ?? 'http';
$gatewayHost = $privateCloudParts['host'] ?? '127.0.0.1';
$derivedGatewayUrl = $gatewayScheme.'://'.$gatewayHost.':4173';
$configuredGatewayUrl = trim((string) env('UNIFIED_FLEET_API_URL', ''));

return [
    // Si no se define explícitamente, ambos servicios se consideran
    // desplegados en el mismo host y solo cambia el puerto.
    'base_url' => $configuredGatewayUrl !== '' ? $configuredGatewayUrl : $derivedGatewayUrl,
    'fallback_base_url' => $derivedGatewayUrl,
    'token' => env('UNIFIED_FLEET_API_TOKEN', ''),
    'timeout' => (int) env('UNIFIED_FLEET_TIMEOUT', 30),
    'upload_timeout' => (int) env('UNIFIED_FLEET_UPLOAD_TIMEOUT', 900),
    'connect_timeout' => (int) env('UNIFIED_FLEET_CONNECT_TIMEOUT', 10),
];
