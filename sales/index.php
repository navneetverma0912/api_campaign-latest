<?php
require_once '/home/jtrivium/public_html/api-campaign/dashboard-auth.php';

if (!isDashboardAdmin()) {
    header('Location: ../login.php');
    exit;
}
$dashboardHtml = file_get_contents(__DIR__ . '/index.html');

echo $dashboardHtml;