<?php
require_once __DIR__ . '/dashboard-auth.php';
requireDashboardAccess();

header("Content-Type: application/json");

require_once "db-trivium.php";

$response = [];

try {

    // Get campaigns
    $campaignQuery = $pdo->query("
        SELECT
            id,
            name,
            status,
            start_date,
            end_date,
            c_campaign_owner
        FROM campaign
        WHERE deleted = 0 AND campaign.c_campaign_owner != 'test' AND campaign.c_campaign_owner != 'Not Set'
        ORDER BY start_date DESC
    ");

    $campaigns = $campaignQuery->fetchAll(PDO::FETCH_ASSOC);
    $totalSent = 0;
    $totalOpened = 0;
    $totalClicked = 0;
    $totalOptOut = 0;
    foreach($campaigns as $campaign){

        $campaignId = $campaign['id'];

        // Sent
        $stmt = $pdo->prepare("
            SELECT COUNT(*) total
            FROM campaign_log_record
            WHERE campaign_id=?
            AND action='Sent' AND deleted=0
        ");

        $stmt->execute([$campaignId]);
        $sent = $stmt->fetchColumn();

        // Opened
        $stmt = $pdo->prepare("
            SELECT COUNT(*) total
            FROM campaign_log_record
            WHERE campaign_id=?
            AND action='Opened' AND deleted=0
        ");

        $stmt->execute([$campaignId]);
        $opened = $stmt->fetchColumn();
        // 1. Count total tracking URLs in this campaign
        $stmt = $pdo->prepare("
            SELECT COUNT(*) total
            FROM campaign_tracking_url
            WHERE campaign_id = ? AND deleted = 0
        ");
        $stmt->execute([$campaignId]);
        $trackingUrlsCount = (int) $stmt->fetchColumn();

        // 2. Count clicks for each URL
        $stmt = $pdo->prepare("
            SELECT
                ctu.id,
                ctu.name,
                ctu.url,
                COUNT(clr.id) AS total_clicks,
                COUNT(DISTINCT clr.parent_id) AS unique_clicks
            FROM campaign_tracking_url ctu
            LEFT JOIN campaign_log_record clr
                ON clr.object_id = ctu.id
                AND clr.action = 'Clicked'
                AND clr.deleted = 0
            WHERE ctu.campaign_id = ?
              AND ctu.deleted = 0
            GROUP BY ctu.id, ctu.name, ctu.url
            ORDER BY total_clicks DESC
        ");
        $stmt->execute([$campaignId]);
        $trackingUrls = $stmt->fetchAll(PDO::FETCH_ASSOC);
// SELECT COUNT(*) total
//             FROM campaign_tracking_url
//             WHERE campaign_id=? AND deleted=0
        // Clicked
        $stmt = $pdo->prepare("
            SELECT COUNT(*) total
            FROM campaign_log_record
            WHERE campaign_id=? AND action='Clicked' AND deleted=0
        ");

        $stmt->execute([$campaignId]);
        $clicked = $stmt->fetchColumn();

        // Opt Out
        $stmt = $pdo->prepare("
            SELECT COUNT(*) total
            FROM campaign_log_record
            WHERE campaign_id=?
            AND action='OptOut' AND deleted=0
        ");

        $stmt->execute([$campaignId]);
        $optOut = $stmt->fetchColumn();

        // Rates
        $openRate = $sent > 0 ? round(($opened/$sent)*100,2) : 0;

        $clickRate = $sent > 0 ? round(($clicked/$sent)*100,2) : 0;

        $ctor = $opened > 0 ? round(($clicked/$opened)*100,2) : 0;

        $optRate = $sent > 0 ? round(($optOut/$sent)*100,2) : 0;

        // Interested Contacts
       
        $stmt = $pdo->prepare("
            SELECT
                clr.campaign_id,
                clr.created_at AS sent,
                clr.action_date AS clicked,
                c.id AS contact_id,
                CONCAT(
                    COALESCE(c.first_name,''),
                    ' ',
                    COALESCE(c.last_name,'')
                ) AS name,
                c.c_company_name AS org,
                ea.name AS email,
                pn.name AS phone
            FROM campaign_log_record clr
            
            INNER JOIN contact c
                ON c.id = clr.parent_id
                AND c.deleted = 0
            
            LEFT JOIN entity_email_address eea
                ON eea.entity_id = c.id
                AND eea.deleted = 0
                AND eea.primary = 1
            
            LEFT JOIN email_address ea
                ON ea.id = eea.email_address_id
                AND ea.deleted = 0
            
            LEFT JOIN entity_phone_number epn
                ON epn.entity_id = c.id
                AND epn.deleted = 0
                AND epn.primary = 1
            
            LEFT JOIN phone_number pn
                ON pn.id = epn.phone_number_id
                AND pn.deleted = 0
            
            WHERE clr.campaign_id = ? AND clr.deleted=0 AND clr.parent_type = 'Contact' AND action='Clicked' AND clr.is_test = 0
            
            ORDER BY clr.created_at DESC;
            
        ");
        $stmt->execute([$campaignId]);

        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // $stmt = $pdo->prepare("
            
        //     SELECT 
        //         c.name AS campaign_name, 
        //     	ctu.name AS Tacking_URL, 
        //         GROUP_CONCAT( 
        //             DISTINCT ctu.name 
        //             ORDER BY ctu.name 
        //             SEPARATOR ', ' 
        //         ) AS tracking_urls, 
             
        //         CASE 
        //             WHEN clr.parent_type = 'Contact' 
        //                 THEN CONCAT_WS(' ', ct.first_name, ct.last_name) 
        //             WHEN clr.parent_type = 'Lead' 
        //                 THEN CONCAT_WS(' ', ld.first_name, ld.last_name) 
        //             ELSE NULL 
        //         END AS recipient_name, 
             
        //         clr.parent_type, 
        //         clr.parent_id, 
        //         clr.action, 
        //         clr.created_at AS clicked_at 
             
        //     FROM campaign_log_record AS clr 
             
        //     LEFT JOIN campaign AS c 
        //         ON c.id = clr.campaign_id 
        //         AND c.deleted = 0 
             
        //     LEFT JOIN campaign_tracking_url AS ctu 
        //         ON ctu.campaign_id = clr.campaign_id 
        //         AND ctu.deleted = 0 
             
        //     LEFT JOIN contact AS ct 
        //         ON ct.id = clr.parent_id 
        //         AND clr.parent_type = 'Contact' 
        //         AND ct.deleted = 0 
             
        //     LEFT JOIN lead AS ld 
        //         ON ld.id = clr.parent_id 
        //         AND clr.parent_type = 'Lead' 
        //         AND ld.deleted = 0 
             
        //     WHERE clr.campaign_id = ? 
        //       AND clr.is_test = 0 
        //       AND clr.action = 'Clicked' 
        //       AND clr.deleted = 0 
             
        //     GROUP BY 
        //         c.name, 
        //         clr.parent_type, 
        //         clr.parent_id, 
        //         clr.action, 
        //         clr.created_at 
             
        //     ORDER BY clr.created_at DESC;

            
        // ");
        $stmt = $pdo->prepare("
            WITH click_data AS (
                SELECT
                    c.id as campaign_id,
                    c.name AS campaign_name,
                    ct.c_company_name AS org,
                    ea.name AS email,
                    pn.name AS phone,
                    clr.created_at AS sent,
                    clr.action_date AS clicked,
                    CASE
                        WHEN clr.parent_type = 'Contact'
                            THEN CONCAT_WS(' ', ct.first_name, ct.last_name)
                        WHEN clr.parent_type = 'Lead'
                            THEN CONCAT_WS(' ', ld.first_name, ld.last_name)
                        ELSE NULL
                    END AS name,
            
                    clr.parent_type,
                    clr.parent_id as contact_id,
                    ctu.name AS tracking_url_name,
                    clr.created_at AS clicked_at,
                    
                    LAG(clr.created_at) OVER (
                        PARTITION BY clr.parent_type, clr.parent_id
                        ORDER BY clr.created_at
                    ) AS previous_clicked_at
            
                FROM campaign_log_record AS clr
            
                LEFT JOIN campaign AS c
                    ON c.id = clr.campaign_id
                    AND c.deleted = 0
            
                LEFT JOIN campaign_tracking_url AS ctu
                    ON ctu.campaign_id = clr.campaign_id
                    AND ctu.deleted = 0
            
                LEFT JOIN contact AS ct
                    ON ct.id = clr.parent_id
                    AND clr.parent_type = 'Contact'
                    AND ct.deleted = 0
            
                LEFT JOIN lead AS ld
                    ON ld.id = clr.parent_id
                    AND clr.parent_type = 'Lead'
                    AND ld.deleted = 0
                    LEFT JOIN entity_email_address eea
                        ON eea.entity_id = ct.id
                        AND eea.deleted = 0
                        AND eea.primary = 1
                    
                    LEFT JOIN email_address ea
                        ON ea.id = eea.email_address_id
                        AND ea.deleted = 0
                    
                    LEFT JOIN entity_phone_number epn
                        ON epn.entity_id = ct.id
                        AND epn.deleted = 0
                        AND epn.primary = 1
                    
                    LEFT JOIN phone_number pn
                        ON pn.id = epn.phone_number_id
                        AND pn.deleted = 0
            
                WHERE clr.campaign_id = ?
                  AND clr.is_test = 0
                  AND clr.action = 'Clicked'
                  AND clr.deleted = 0
            ),
            
            contact_average AS (
                SELECT
                    campaign_id,
                    campaign_name,
                    name,
                    sent,
                    clicked,
                    parent_type,
                    contact_id,
                    org,email,phone,
                    COUNT(*) AS total_clicks,
            
                    MIN(clicked_at) AS first_click,
                    MAX(clicked_at) AS last_click,
            
                    ROUND(
                        AVG(
                            TIMESTAMPDIFF(
                                SECOND,
                                previous_clicked_at,
                                clicked_at
                            )
                        ),
                        2
                    ) AS avg_click_gap_seconds
            
                FROM click_data
            
                WHERE previous_clicked_at IS NOT NULL
            
                GROUP BY
                    campaign_name,
                    name,
                    parent_type,
                    contact_id
            )
            
            SELECT
                campaign_id,
                campaign_name,
                name,
                email,
                phone,
                sent,
                clicked,
                parent_type,
                contact_id,
                org,
                total_clicks,
                first_click,
                last_click,
            
                avg_click_gap_seconds,
            
                SEC_TO_TIME(
                    ROUND(avg_click_gap_seconds)
                ) AS avg_click_gap,
            
                CASE
                    WHEN avg_click_gap_seconds >= 3
                        THEN 'Genuine'
            
                    WHEN avg_click_gap_seconds < 3
                        THEN 'Confirmed Bot'
            
                    ELSE 'Unknown'
                END AS status
            
            FROM contact_average
            
            ORDER BY avg_click_gap_seconds DESC;
        ");
        $stmt->execute([$campaignId]);

        $contacts_tracking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response[] = [

            "id"=>$campaignId,

            "name"=>$campaign['name'],

            "status"=>$campaign['status'],

            "start"=>$campaign['start_date'],

            // "end"=>$campaign['end_date'],
            "end"=>$campaign['end_date'],
            "c_campaign_owner"=>$campaign['c_campaign_owner'],

            "summary"=>[
                "sent"=>$sent,
                "opened"=>$opened,
                "clicked"=>$clicked,
                "optOut"=>$optOut,
                "trackingUrlsCount" => $trackingUrlsCount,
                "trackingUrls" => $trackingUrls,
            ],
            

            "rates"=>[
                "openRate"=>$openRate,
                "clickRate"=>$clickRate,
                "ctor"=>$ctor,
                "optOutRate"=>$optRate
            ],

            // "contacts"=>$contacts,
            "contacts"=>$contacts_tracking

        ];
        $totalSent += $sent;
        $totalOpened += $opened;
        $totalClicked += $clicked;
        $totalOptOut += $optOut;

    }
    $overallOpenRate = $totalSent > 0
        ? round(($totalOpened / $totalSent) * 100, 2)
        : 0;
    
    $overallClickRate = $totalSent > 0
        ? round(($totalClicked / $totalSent) * 100, 2)
        : 0;
    
    $overallCtor = $totalOpened > 0
        ? round(($totalClicked / $totalOpened) * 100, 2)
        : 0;
    
    $overallOptOutRate = $totalSent > 0
        ? round(($totalOptOut / $totalSent) * 100, 2)
        : 0;

    // echo json_encode([
    //     "success"=>true,
    //     "campaigns"=>$response
    // ]);
    echo json_encode([
        "success" => true,
    
        "summary" => [
            "sent" => $totalSent,
            "opened" => $totalOpened,
            "clicked" => $totalClicked,
            "optOut" => $totalOptOut,
            "campaigns" => count($response),
    
            "rates" => [
                "openRate" => $overallOpenRate,
                "clickRate" => $overallClickRate,
                "ctor" => $overallCtor,
                "optOutRate" => $overallOptOutRate
            ]
        ],
    
        "campaigns" => $response
    ]);

}
catch(Exception $e){

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}