<?php

return [
    'enabled' => (bool) env('Z2_MOBILE_ONBOARDING_ENABLED', false),
    'session_minutes' => 30,
    'snapshot_seconds' => 2,
    'heartbeat_freshness_seconds' => 90,
    // This is the firmware's host:port, never the Laravel HTTPS or private REST URL.
    'cloud_domain' => env('Z2_MOBILE_CLOUD_DOMAIN', ''),
    'requires_bind' => (bool) env('Z2_MOBILE_REQUIRES_BIND', false),
    'advertisers_code' => env('Z2_MOBILE_ADVERTISERS_CODE', ''),
    // Enable only after establishing that the enrollment ID is not a privileged credential.
    'public_enrollment_identifier' => (bool) env('Z2_MOBILE_PUBLIC_ENROLLMENT_IDENTIFIER', false),
];
