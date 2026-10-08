<?php
// // cron_send_campaign_mails.php

// define('CAMPAIGN_REPORT_INCLUDE_ONLY', true);

// require_once '../db-trivium.php';
// require_once 'send-campaign-reports.php';
// if (!function_exists('sendCampaignReportById')) {
//     die("ERROR: sendCampaignReportById() is NOT loaded.\n");
// }

// echo "sendCampaignReportById() loaded successfully.\n";

// if (!is_file(__DIR__ . '/config.php')) {
//     fwrite(STDERR, "Missing config.php\n");
//     exit(1);
// }
// $config_c = require __DIR__ . '/config.php';
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
//     print_r($campaigns);
//     if (empty($campaigns)) {
//         echo "No active campaigns for {$today}.\n";
//         exit;
//     }
//     foreach ($campaigns as $campaign) {
//         print_r('recipients:--' . $campaign['from_addresses']); 
//         $recipients = [];
//         if (!empty($campaign['from_addresses'])) {
//             $recipients = array_values(array_unique(array_filter(array_map(
//                 'trim',
//                 explode(',', $campaign['from_addresses'])
//             ))));
//         }
    
//         // Schedule check: start day, end day, or every 15 days in between
//         $startStr = substr($campaign['start_date'], 0, 10);
//         $endStr   = !empty($campaign['end_date']) ? substr($campaign['end_date'], 0, 10) : null;
    
//         $daysSinceStart = (new DateTime($startStr))->diff(new DateTime($today))->days;
    
//         $isStartDay    = ($daysSinceStart === 0);
//         $isEndDay      = ($endStr !== null && $endStr === $today);
//         $isEndDay_between = (
//             $endStr !== null &&
//             $endStr >= $today
//         );
//         // $isIntervalDay = ($daysSinceStart > 0 && $daysSinceStart % 15 === 0);
//         echo "Start Day- ". $startStr . $isStartDay ."\n";
//             echo "End Day- ". $endStr . $isEndDay ."\n";
//             echo "Interval Day- ". $daysSinceStart . $isIntervalDay ."\n";
            
//         print_r("Recipients:----" . $recipients);
        
//         $intervalBaseDate = $endStr ?? $startStr;
        
//         $daysSinceIntervalBase = (new DateTime($intervalBaseDate))
//             ->diff(new DateTime($today))
//             ->days;
//         $isIntervalDay = (
//             $endStr !== null &&
//             date('Y-m-d', strtotime($endStr . ' +15 days')) === $today
//         );
//         print_r("isIntervalDay for Trivium:-" . $isIntervalDay);
//         print_r("isEndDay for Trivium:-:-" . $isEndDay);
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
//         // if (!($isStartDay || $isEndDay || $isIntervalDay)) {
//         //     continue;
//         // }
//         if (empty($campaign['from_addresses'])) {
//             echo "Skipping campaign {$campaign['id']} ({$campaign['name']}): no from_address on file.\n";
//             continue;
//         }
        
//         $sendTo = ['navneet.verma@algoqube.com','tapas.mishra@algoqube.com'];
//         // $sendTo = $campaign['from_addresses'];
    
//         try {
//             print_r($sendTo);
//             echo $campaign['id'];
//             echo $config_c;
//             $ok = sendCampaignReportById((string)$campaign['id'], $sendTo, $config_c);
            
//             echo $ok
//                 ? "Mail sent for campaign_id: {$campaign['id']} -> " . implode(', ', $sendTo) . "\n"
//                 : "FAILED for campaign_id: {$campaign['id']}\n";
//         } catch (Throwable $e) {
//             error_log("Failed to send mail for campaign_id {$campaign['id']}: " . $e->getMessage());
//         }
//     }
// } catch (PDOException $e) {
//     error_log("DB error in cron_send_campaign_mails.php: " . $e->getMessage());
// }

// cron_send_campaign_mails.php

define('CAMPAIGN_REPORT_INCLUDE_ONLY', true);

require_once '../db-trivium.php';
require_once 'send-campaign-reports.php';

if (!function_exists('sendCampaignReportById')) {
    die("ERROR: sendCampaignReportById() is NOT loaded.\n");
}

echo "sendCampaignReportById() loaded successfully.\n";

if (!is_file(__DIR__ . '/config.php')) {
    fwrite(STDERR, "Missing config.php\n");
    exit(1);
}

$config_c = require __DIR__ . '/config.php';

try {

    $today = date('Y-m-d');

    /*
     * Get all campaigns that have started.
     *
     * IMPORTANT:
     * Do NOT filter by end_date here because we need to
     * check end_date + 15 days even after the campaign ends.
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

    
    foreach ($campaigns as $campaign) {
// $sendTo = [$campaign['from_addresses']];
        $startStr = substr($campaign['start_date'], 0, 10);

        $endStr = !empty($campaign['end_date'])
            ? substr($campaign['end_date'], 0, 10)
            : null;


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


        /*
         * Debug
         */
        echo "\n--------------------------------------\n";
        echo "Campaign ID: {$campaign['id']}\n";
        echo "Campaign: {$campaign['name']}\n";
        echo "Start Date: {$startStr}\n";
        echo "End Date: " . ($endStr ?? 'NULL') . "\n";
        echo "Today: {$today}\n";
        echo "Daily Day: " . ($isDailyDay ? 'YES' : 'NO') . "\n";
        echo "15 Days After End: " . ($isIntervalDay ? 'YES' : 'NO') . "\n";


        /*
         * --------------------------------------------
         * 3. SHOULD WE SEND?
         * --------------------------------------------
         */
        if (!$isDailyDay && !$isIntervalDay) {

            echo "Skipping campaign {$campaign['id']}\n";
            continue;
        }


        /*
         * --------------------------------------------
         * 4. CHECK FROM ADDRESS
         * --------------------------------------------
         */
        if (empty($campaign['from_addresses'])) {

            echo "Skipping campaign {$campaign['id']} "
               . "({$campaign['name']}): no from_address on file.\n";

            continue;
        }


        /*
         * --------------------------------------------
         * 5. SEND REPORT
         * --------------------------------------------
         */
        try {

            echo "Sending report...\n";

            $ok = sendCampaignReportById(
                (string)$campaign['id'],
                $sendTo,
                $config_c
            );

            echo $ok
                ? "Mail sent for campaign_id: {$campaign['id']} -> "
                    . implode(', ', $sendTo) . "\n"
                : "FAILED for campaign_id: {$campaign['id']}\n";

        } catch (Throwable $e) {

            error_log(
                "Failed to send mail for campaign_id "
                . "{$campaign['id']}: "
                . $e->getMessage()
            );
        }
    }

} catch (PDOException $e) {

    error_log(
        "DB error in cron_send_campaign_mails.php: "
        . $e->getMessage()
    );
}