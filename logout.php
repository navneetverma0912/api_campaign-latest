<?php

require_once __DIR__ . '/dashboard-auth.php';
startDashboardSession();

$csrfToken = $_POST['csrf_token'] ?? '';
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !is_string($csrfToken)
    || !isset($_SESSION['dashboard_csrf_token'])
    || !hash_equals($_SESSION['dashboard_csrf_token'], $csrfToken)
) {
    http_response_code(400);
    exit('Invalid logout request.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('Location: login.php');
exit;
