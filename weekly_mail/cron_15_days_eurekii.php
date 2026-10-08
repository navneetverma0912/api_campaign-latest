<?php

// define('CAMPAIGN_REPORT_INCLUDE_ONLY', true); 

// require_once '../db.php';              // your PDO $pdo connection
// require_once 'send-campaign-reports-eurekii.php';  

// if (!function_exists('sendCampaignReportById')) {
//     die("ERROR: sendCampaignReportById() is NOT loaded.\n");
// }

// echo "sendCampaignReportById() loaded successfully.\n";

// if (!is_file(__DIR__ . '/config-eurekii.php')) {
//     fwrite(STDERR, "Missing config-eurekii.php\n");
//     exit(1);
// }
// $config = require __DIR__ . '/config-eurekii.php';
// // print_r($config);die;
// try {
//     $today = date('Y-m-d');

//     $stmt = $pdo->prepare("
//         SELECT
//             c.id,
//             c.name,
//             c.start_date,
//             c.end_date,
//             c.c_campaign_owner,
//             GROUP_CONCAT(DISTINCT me.from_address) AS from_addresses
//         FROM campaign c
//         LEFT JOIN mass_email me ON me.campaign_id = c.id
//         WHERE c.start_date <= :today
//           AND (c.end_date >= :today OR c.end_date IS NULL)
//           AND c.c_campaign_owner != 'Test'
//           AND c.c_campaign_owner != 'Not Set'
//         GROUP BY c.id
//     ");
//     $stmt->execute([':today' => $today]);

//     $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
//     if (empty($campaigns)) {
//         echo "No active campaigns for {$today}.\n";
//         exit;
//     }
    
//     foreach ($campaigns as $campaign) {
//         $recipients = [];
//         if (!empty($campaign['from_addresses'])) {
//             $recipients = array_values(array_unique(array_filter(array_map(
//                 'trim',
//                 explode(',', $campaign['from_addresses'])
//             ))));
//         }
        
//         $startStr = substr($campaign['start_date'], 0, 10);
//         $endStr   = !empty($campaign['end_date']) ? substr($campaign['end_date'], 0, 10) : null;
    
//         $daysSinceStart = (new DateTime($startStr))->diff(new DateTime($today))->days;
    
//         $isStartDay    = ($daysSinceStart === 0);
//         $isEndDay      = ($endStr !== null && $endStr === $today);
//         $isEndDay_between = (
//             $endStr !== null &&
//             $endStr >= $today
//         );
//         echo "Start Day- ". $startStr . $isStartDay ."\n";
//         echo "End Day- ". $endStr . $isEndDay ."\n";
//         echo "Interval Day- ". $daysSinceStart . $isIntervalDay ."\n";
//         $intervalBaseDate = $endStr ?? $startStr;
        
//         $daysSinceIntervalBase = (new DateTime($intervalBaseDate))
//             ->diff(new DateTime($today))
//             ->days;
//         $isIntervalDay = (
//             $endStr !== null &&
//             date('Y-m-d', strtotime($endStr . ' +15 days')) === $today
//         );
//         print_r("isIntervalDay:-" . $isIntervalDay);
//         print_r("isEndDay:-" . $isEndDay);
//         if ($isIntervalDay) {
//             $ok = sendCampaignReportById((string)$campaign['id'], $sendTo, $config);
//             echo $ok
//                 ? "Mail sent for campaign_id: {$campaign['id']} -> " . implode(', ', $sendTo) . "\n"
//                 : "FAILED for campaign_id: {$campaign['id']}\n";
//             continue;
//         }
//         if ($isEndDay_between) {
//             $ok = sendCampaignReportById((string)$campaign['id'], $sendTo, $config);
//             echo $ok
//                 ? "Mail sent for campaign_id: {$campaign['id']} -> " . implode(', ', $sendTo) . "\n"
//                 : "FAILED for campaign_id: {$campaign['id']}\n";
//             continue;
//         }
//         if (empty($campaign['from_addresses'])) {
//             echo "Skipping campaign {$campaign['id']} ({$campaign['name']}): no from_address on file.\n";
//             continue;
//         } 
//         print_r('recipients:--' . $recipients);
//         $sendTo = ['navneet.verma@algoqube.com','tapas.mishra@algoqube.com'];
//         // $sendTo = [$campaign['from_addresses']];
//         // $sendTo = array_map(
//         //     'trim',
//         //     explode(',', $campaign['from_addresses'])
//         // );
        
    
//         try {
//             $ok = sendCampaignReportById((string)$campaign['id'], $sendTo, $config);
//             echo $ok
//                 ? "Mail sent for campaign_id: {$campaign['id']} -> " . implode(', ', $sendTo) . "\n"
//                 : "FAILED for campaign_id: {$campaign['id']}\n";
//         } catch (Throwable $e) {
//             error_log("Failed to send mail for campaign_id {$campaign['id']}: " . $e->getMessage());
//         }
//     }
// } catch (PDOException $e) {
//     error_log("DB error in cron_send_campaign_mails_eurekii.php: " . $e->getMessage());
// }



define('CAMPAIGN_REPORT_INCLUDE_ONLY', true);

require_once '../db.php';
require_once 'send-campaign-reports-eurekii.php';

if (!function_exists('sendCampaignReportById')) {
    die("ERROR: sendCampaignReportById() is NOT loaded.\n");
}

echo "sendCampaignReportById() loaded successfully.\n";

if (!is_file(__DIR__ . '/config-eurekii.php')) {
    fwrite(STDERR, "Missing config-eurekii.php\n");
    exit(1);
}

$config = require __DIR__ . '/config-eurekii.php';

try {

    $today = date('Y-m-d');

    /*
     * IMPORTANT:
     * Do not filter by end_date here.
     * We need campaigns whose end_date has already passed
     * so that we can check end_date + 15 days.
     */
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.name,
            c.start_date,
            c.end_date,
            c.c_campaign_owner,
            GROUP_CONCAT(DISTINCT me.from_address) AS from_addresses
        FROM campaign c
        LEFT JOIN mass_email me ON me.campaign_id = c.id
        WHERE c.start_date <= :today
          AND c.c_campaign_owner != 'Test'
          AND c.c_campaign_owner != 'Not Set'
        GROUP BY c.id
    ");

    $stmt->execute([
        ':today' => $today
    ]);

    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($campaigns)) {
        echo "No campaigns found for {$today}.\n";
        exit;
    }

    /*
     * Recipients
     */
    $sendTo = [
        'navneet.verma@algoqube.com',
        'tapas.mishra@algoqube.com'
    ];
        // $sendTo = [$campaign['from_addresses']];
        // $sendTo = array_map(
        //     'trim',
        //     explode(',', $campaign['from_addresses'])
        // );

    foreach ($campaigns as $campaign) {

        $startStr = substr($campaign['start_date'], 0, 10);

        $endStr = !empty($campaign['end_date'])
            ? substr($campaign['end_date'], 0, 10)
            : null;


        /*
         * --------------------------------------------------
         * 1. DAILY MAIL
         * --------------------------------------------------
         *
         * If end_date exists:
         *   start_date <= today <= end_date
         *
         * If end_date is NULL:
         *   start_date <= today
         */
        $isDailyDay = false;

        if ($endStr !== null) {

            $isDailyDay = (
                $today >= $startStr &&
                $today <= $endStr
            );

        } else {

            $isDailyDay = (
                $today >= $startStr
            );
        }


        /*
         * --------------------------------------------------
         * 2. 15 DAYS AFTER END DATE
         * --------------------------------------------------
         *
         * Example:
         *
         * end_date = 2026-09-15
         * today    = 2026-09-30
         *
         * TRUE
         */
        $isIntervalDay = false;

        if ($endStr !== null) {

            $endPlus15 = date(
                'Y-m-d',
                strtotime($endStr . ' +15 days')
            );

            $isIntervalDay = (
                $endPlus15 === $today
            );
        }


        echo "\n--------------------------------------\n";
        echo "Campaign: {$campaign['name']}\n";
        echo "Start Date: {$startStr}\n";
        echo "End Date: " . ($endStr ?? 'NULL') . "\n";
        echo "Today: {$today}\n";
        echo "Daily Day: " . ($isDailyDay ? 'YES' : 'NO') . "\n";
        echo "15 Days After End: " . ($isIntervalDay ? 'YES' : 'NO') . "\n";


        /*
         * --------------------------------------------------
         * SEND MAIL
         * --------------------------------------------------
         */

        if (!$isDailyDay && !$isIntervalDay) {
            echo "Skipping campaign {$campaign['id']}\n";
            continue;
        }


        /*
         * Optional: check recipient/source email
         */
        if (empty($campaign['from_addresses'])) {
            echo "Skipping campaign {$campaign['id']} ({$campaign['name']}): no from_address on file.\n";
            continue;
        }


        try {

            $ok = sendCampaignReportById(
                (string)$campaign['id'],
                $sendTo,
                $config
            );

            echo $ok
                ? "Mail sent for campaign_id: {$campaign['id']} -> "
                    . implode(', ', $sendTo) . "\n"
                : "FAILED for campaign_id: {$campaign['id']}\n";

        } catch (Throwable $e) {

            error_log(
                "Failed to send mail for campaign_id {$campaign['id']}: "
                . $e->getMessage()
            );
        }
    }

} catch (PDOException $e) {

    error_log(
        "DB error in cron_send_campaign_mails_eurekii.php: "
        . $e->getMessage()
    );
}