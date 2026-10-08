<?php

require_once __DIR__ . '/load-env.php';

function startDashboardSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    session_name('campaign_dashboard');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

function dashboardAdminCredentials(): array
{
    $username = getenv('DASHBOARD_ADMIN_USERNAME');
    $passwordHash = getenv('DASHBOARD_ADMIN_PASSWORD_HASH');

    if ($username === false || $username === '' || $passwordHash === false || $passwordHash === '') {
        return [];
    }

    return ['username' => $username, 'password_hash' => $passwordHash];
}

function dashboardCsrfToken(): string
{
    startDashboardSession();

    if (!isset($_SESSION['dashboard_csrf_token'])) {
        $_SESSION['dashboard_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['dashboard_csrf_token'];
}

function isDashboardAdmin(): bool
{
    startDashboardSession();
    return ($_SESSION['dashboard_role'] ?? null) === 'administrator';
}

function requireDashboardAccess(): void
{
    if (isDashboardAdmin()) {
        return;
    }

    $apiToken = getenv('DASHBOARD_API_TOKEN');
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (
        $apiToken !== false
        && strlen($apiToken) >= 32
        && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1
        && hash_equals($apiToken, $matches[1])
    ) {
        return;
    }

    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'success' => false,
        'message' => 'Administrator login is required.',
    ]);
    exit;
}
