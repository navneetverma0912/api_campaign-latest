<?php

require_once __DIR__ . '/dashboard-auth.php';

if (!isDashboardAdmin()) {
    header('Location: login.php');
    exit;
}

$dashboardHtml = file_get_contents(__DIR__ . '/index.html');
if ($dashboardHtml === false) {
    http_response_code(500);
    exit('Dashboard page could not be loaded.');
}

$csrfToken = htmlspecialchars(dashboardCsrfToken(), ENT_QUOTES, 'UTF-8');
$dashboardHtml = str_replace('__DASHBOARD_CSRF_TOKEN__', $csrfToken, $dashboardHtml, $tokenCount);
if ($tokenCount !== 1) {
    http_response_code(500);
    exit('Dashboard logout form could not be prepared.');
}

echo $dashboardHtml;
