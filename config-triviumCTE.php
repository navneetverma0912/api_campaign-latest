<?php

require_once __DIR__ . '/load-env.php';

$dbUser = 'jtrivium_TMS';
$dbPassword = 'Test@2024';

if ($dbUser === false || $dbUser === '' || $dbPassword === false || $dbPassword === '') {
    throw new RuntimeException(
        'Set TRIVIUM_CTE_DB_USER and TRIVIUM_CTE_DB_PASSWORD in the project .env before using Trivium CTE Campaign Analytics.'
    );
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=jtrivium_TMS;charset=utf8mb4',
        $dbUser,
        $dbPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $error) {
    throw new RuntimeException('Could not connect to the Trivium CTE campaign database.', 0, $error);
}
