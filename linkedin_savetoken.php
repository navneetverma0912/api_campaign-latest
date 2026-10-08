<?php

require_once "db-trivium.php";

$accessToken = $_POST['access_token'] ?? '';
$expiresIn   = $_POST['expires_in'] ?? null;

if (empty($accessToken)) {
    die("Access token is required.");
}

$expiresAt = null;

if (!empty($expiresIn)) {
    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + (int)$expiresIn
    );
}

// Check if token already exists
$stmt = $pdo->query("SELECT id FROM linkedin_tokens LIMIT 1");
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {

    $stmt = $pdo->prepare("
        UPDATE linkedin_tokens
        SET access_token = ?,
            expires_at = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $accessToken,
        $expiresAt,
        $existing['id']
    ]);

} else {

    $stmt = $pdo->prepare("
        INSERT INTO linkedin_tokens
        (access_token, expires_at)
        VALUES (?, ?)
    ");

    $stmt->execute([
        $accessToken,
        $expiresAt
    ]);
}

echo "LinkedIn access token saved successfully.";