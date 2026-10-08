<?php

date_default_timezone_set('Asia/Kolkata');

$today = date('Y-m-d');
$day   = (int) date('j');

echo "========================================\n";
echo "Master Cron Started: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

$php = '/usr/local/bin/php';

$campaignJobs = [
    '/home/jtrivium/public_html/api-campaign/weekly_mail/cron_15_days.php',
    '/home/jtrivium/public_html/api-campaign/weekly_mail/cron_15_days_eurekii.php'
];

$webJobs = [
    '/home/jtrivium/public_html/api-campaign/weekly_mail/send-web-reports.php',
    '/home/jtrivium/public_html/api-campaign/weekly_mail/send-web-reports-eurekii.php',
    '/home/jtrivium/public_html/api-campaign/weekly_mail/send-web-reports-cte.php'
];

function runJob($php, $job)
{
    echo "\n----------------------------------------\n";
    echo "Running: {$job}\n";
    echo "Started: " . date('Y-m-d H:i:s') . "\n";

    if (!file_exists($job)) {
        echo "ERROR: File does not exist\n";
        return false;
    }

    $command = $php . ' ' . escapeshellarg($job) . ' 2>&1';

    $output = [];
    $returnCode = 0;

    exec($command, $output, $returnCode);

    if (!empty($output)) {
        echo implode("\n", $output) . "\n";
    }

    if ($returnCode === 0) {
        echo "SUCCESS: {$job}\n";
        return true;
    }

    echo "FAILED: {$job}\n";
    echo "Return Code: {$returnCode}\n";

    return false;
}
echo "\n========== CAMPAIGN JOBS ==========\n";

foreach ($campaignJobs as $job) {
    runJob($php, $job);
}

if ($day === 2 || $day === 16) {

    echo "\n========== WEB JOBS ==========\n";
    echo "Today is {$day}th - running web analytics.\n";

    foreach ($webJobs as $job) {
        runJob($php, $job);
    }

} else {

    echo "\n========== WEB JOBS ==========\n";
    echo "Today is {$day}th - web jobs skipped.\n";
}

echo "\n========================================\n";
echo "Master Cron Finished: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

?>