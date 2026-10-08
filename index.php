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
$logoutForm = '<form method="post" action="logout.php" style="position:absolute;top:22px;right:32px;z-index:3">'
    . '<input type="hidden" name="csrf_token" value="' . $csrfToken . '">'
    . '<button type="submit" style="padding:9px 14px;border:1px solid rgba(255,255,255,.45);border-radius:8px;background:rgba(255,255,255,.12);color:#fff;font:600 13px Inter,sans-serif;cursor:pointer">Log out</button>'
    . '</form>';

$dashboardHtml = str_replace('</header>', $logoutForm . '</header>', $dashboardHtml, $headerCount);
if ($headerCount !== 1) {
    http_response_code(500);
    exit('Dashboard header could not be prepared.');
}

echo $dashboardHtml;
