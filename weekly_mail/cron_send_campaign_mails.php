<?php
// cron_send_campaign_mails.php

define('CAMPAIGN_REPORT_INCLUDE_ONLY', true); // prevent the CLI block from running

require_once '../db-trivium.php';              // your PDO $pdo connection
require_once 'send-campaign-reports.php';   // now safe to include — only defines functions

if (!is_file(__DIR__ . '/config.php')) {
    fwrite(STDERR, "Missing config.php\n");
    exit(1);
}
$config = require __DIR__ . '/config.php';
// print_r($config);die;
try {
    $today = date('Y-m-d');

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
          AND (c.end_date >= :today OR c.end_date IS NULL)
          AND c.c_campaign_owner != 'Test'
          AND c.c_campaign_owner != 'Not Set'
        GROUP BY c.id
    ");
    $stmt->execute([':today' => $today]);

    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // print_r($campaigns);die;
    if (empty($campaigns)) {
        echo "No active campaigns for {$today}.\n";
        exit;
    }

    foreach ($campaigns as $campaign) {
        print_r($campaign);
        $recipients = [];
        if (!empty($campaign['from_addresses'])) {
            $recipients = array_values(array_filter(array_map(
                'trim',
                explode(',', $campaign['from_addresses'])
            )));
        }

        if (empty($recipients)) {
            echo "Skipping campaign {$campaign['id']} ({$campaign['name']}) — no from_address on file.\n";
            continue;
        }
        $recipients_n = ['navneet.verma@algoqube.com'];
        try {
                $ok = sendCampaignReportById((string)$campaign['id'], $recipients_n, $config);
                echo $ok
                    ? "Mail sent for campaign_id: {$campaign['id']}\n"
                    : "FAILED for campaign_id: {$campaign['id']}\n";
            // sendCampaignReportById((string)$campaign['id'], $recipients_n, $config);
            // echo "Mail triggered for campaign_id: {$campaign['id']} ({$campaign['name']}) -> " . implode(', ', $recipients_n) . "\n";
        } catch (Throwable $e) {
            error_log("Failed to send mail for campaign_id {$campaign['id']}: " . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    error_log("DB error in cron_send_campaign_mails.php: " . $e->getMessage());
}