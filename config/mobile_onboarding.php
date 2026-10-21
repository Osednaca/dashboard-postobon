<?php

return [
    'enabled' => (bool) env('Z2_MOBILE_ONBOARDING_ENABLED', true),
    'session_minutes' => 30,
    'snapshot_seconds' => 2,
    'heartbeat_freshness_seconds' => 90,
    // Preserve each fan's network.cfg unless a request explicitly chooses a target.
    'advertisers_code' => env('Z2_MOBILE_ADVERTISERS_CODE', ''),
    // Enable only after establishing that the enrollment ID is not a privileged credential.
    'public_enrollment_identifier' => (bool) env('Z2_MOBILE_PUBLIC_ENROLLMENT_IDENTIFIER', false),
];
