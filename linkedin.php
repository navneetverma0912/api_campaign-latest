<?php

$clientId = "77p6scab0e42ns";

$redirectUri =
    urlencode(
        "https://triviumedu.com/api_campaign/callback.php"
    );

$scope =
    urlencode(
        "openid profile email"
    );

$loginUrl =
    "https://www.linkedin.com/oauth/v2/authorization?" .
    "response_type=code" .
    "&client_id=" . $clientId .
    "&redirect_uri=" . $redirectUri .
    "&scope=" . $scope;

header("Location: " . $loginUrl);