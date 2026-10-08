<?php


require_once "db-trivium.php";
session_start();

$clientId = "77p6scab0e42ns";
$redirectUri = "https://triviumedu.com/api-campaign/callback.php";

/*
|--------------------------------------------------------------------------
| Generate PKCE Code Verifier
|--------------------------------------------------------------------------
*/

$codeVerifier = bin2hex(random_bytes(32));

$_SESSION['linkedin_code_verifier'] = $codeVerifier;

/*
|--------------------------------------------------------------------------
| Generate PKCE Code Challenge
|--------------------------------------------------------------------------
*/

$codeChallenge = rtrim(
    strtr(
        base64_encode(
            hash('sha256', $codeVerifier, true)
        ),
        '+/',
        '-_'
    ),
    '='
);

/*
|--------------------------------------------------------------------------
| Generate State
|--------------------------------------------------------------------------
*/

$state = bin2hex(random_bytes(16));

$_SESSION['linkedin_state'] = $state;

/*
|--------------------------------------------------------------------------
| LinkedIn Authorization URL
|--------------------------------------------------------------------------
*/

$params = [
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'state' => $state,

    // Permissions
    'scope' => 'openid profile email',

    // PKCE
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => 'S256'
];

$url =
    "https://www.linkedin.com/oauth/v2/authorization?"
    . http_build_query($params);

/*
|--------------------------------------------------------------------------
| Debug session
|--------------------------------------------------------------------------
*/

// Uncomment temporarily if needed
// echo "<pre>";
// print_r($_SESSION);
// echo "</pre>";
// exit;

header("Location: " . $url);
// exit;
