<?php

require_once __DIR__ . '/../load-env.php';

function dashboardApiAuthHeaders(): array
{
    $token = getenv('DASHBOARD_API_TOKEN');
    if ($token === false || strlen($token) < 32) {
        throw new RuntimeException(
            'DASHBOARD_API_TOKEN must be configured with at least 32 characters for dashboard API access.'
        );
    }

    return ['Authorization: Bearer ' . $token];
}
