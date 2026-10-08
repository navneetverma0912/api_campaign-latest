<?php

require_once __DIR__ . '/dashboard-auth.php';
startDashboardSession();

if (isDashboardAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$credentials = dashboardAdminCredentials();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!is_string($csrfToken) || !hash_equals(dashboardCsrfToken(), $csrfToken)) {
        http_response_code(400);
        $error = 'Your login session expired. Refresh the page and try again.';
    } elseif ($credentials === []) {
        http_response_code(503);
        $error = 'Administrator login is not configured on this server.';
    } elseif (
        is_string($username)
        && is_string($password)
        && hash_equals($credentials['username'], $username)
        && password_verify($password, $credentials['password_hash'])
    ) {
        session_regenerate_id(true);
        $_SESSION['dashboard_role'] = 'administrator';
        unset($_SESSION['dashboard_csrf_token']);
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

$csrfToken = htmlspecialchars(dashboardCsrfToken(), ENT_QUOTES, 'UTF-8');
$errorHtml = $error === ''
    ? ''
    : '<p class="error" role="alert">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrator login | Campaign Analytics</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f0eff5; color: #2b2740; font: 16px Arial, sans-serif; }
        main { width: min(100%, 420px); padding: 36px; border-radius: 18px; background: #fff; box-shadow: 0 12px 36px rgba(46, 34, 96, .12); }
        h1 { margin: 0 0 8px; color: #2e2260; font-size: 25px; }
        p { color: #6b7085; line-height: 1.5; }
        label { display: block; margin: 18px 0 7px; font-weight: 600; }
        input { width: 100%; padding: 12px; border: 1px solid #d8d5e4; border-radius: 8px; font: inherit; }
        button { width: 100%; margin-top: 24px; padding: 13px; border: 0; border-radius: 8px; background: #2e2260; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        button:hover { background: #3a2e7a; }
        .error { padding: 11px; border-radius: 7px; background: #fbe7e7; color: #9e2929; }
    </style>
</head>
<body>
<main>
    <h1>Campaign Analytics</h1>
    <p>Sign in with your administrator account to view the dashboard.</p>
    <?= $errorHtml ?>
    <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <label for="username">Administrator username</label>
        <input id="username" name="username" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Sign in</button>
    </form>
</main>
</body>
</html>
