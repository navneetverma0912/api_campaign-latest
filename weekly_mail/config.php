<?php

return [
    'api_url' => 'https://triviumedu.com/api-campaign/campaign-dashboard-trivium.php',
    'smtp_host'   => 'smtp.hostinger.com',
    'smtp_port'   => 587,          // 587 for TLS, 465 for SSL
    'smtp_secure' => 'tls',        // 'tls' or 'ssl'
    'smtp_user'   => 'itadmin@navneet.org.in',
    'smtp_pass'   => 'N@vneet.@123aws',
    'from_email' => 'itadmin@navneet.org.in',
    'from_name'  => 'Trivium Campaign Reports',
    'recipients' => [
        'navneet.verma@algoqube.com'
    ],
    // 'bcc' => [
    //     'aditya.bhatia@triviumedu.com',
    //     'tim.connors@triviumedu.com',
    //     'richa.chugh@triviumedu.com',
    //     'harsh.virmani@triviumedu.com',
    //     'saurabh.rawat@triviumedu.com',
    //     'corinne.winston@triviumedu.com',
    //     'suraj.pandey@triviumedu.com',
    // ],
    'report_prefix' => 'Campaign Analytics',
    'dashboard_url' => 'https://triviumedu.com/api-campaign/index.html',
    'logo_path' => 'https://triviumedu.com/api-campaign/assets/trivium-logo.png'
];