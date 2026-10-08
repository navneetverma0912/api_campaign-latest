<?php

// $accessToken = "YOUR_ACCESS_TOKEN";

$organizationId = "14533222";

$url =
"https://api.linkedin.com/rest/posts?author=urn%3Ali%3Aorganization%3A"
. $organizationId;

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_HTTPHEADER, [

    "Authorization: Bearer $accessToken",

    "Linkedin-Version: 202509",

    "X-Restli-Protocol-Version: 2.0.0"

]);

$response = curl_exec($ch);

curl_close($ch);

echo $response;