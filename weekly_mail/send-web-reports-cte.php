<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

// $apiUrl = 'https://triviumedu.com/api-campaign/ga4-trivium.php?start_date=2026-09-11&end_date=2026-09-18';
$apiUrl = 'https://triviumedu.com/api-campaign/ga4-triviumCTE.php';

$templateFile = __DIR__ . '/templates/GA4-dashboard-email.html';

$logoFile = __DIR__ . '/assets/cte.png';


/*
|--------------------------------------------------------------------------
| DATE RANGE
|--------------------------------------------------------------------------
|
| Example:
| Previous 14 complete days
|
*/

$endDate = date('Y-m-d', strtotime('-1 day'));
$startDate = date('Y-m-d', strtotime('-15 days'));

/*
|--------------------------------------------------------------------------
| DATE RANGE - TESTING
|--------------------------------------------------------------------------
*/

// $startDate = '2026-09-13';
// $endDate   = '2026-09-21';
/*
|--------------------------------------------------------------------------
| FETCH GA4 API
|--------------------------------------------------------------------------
*/

$url = $apiUrl . '?' . http_build_query([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);

if ($response === false) {
    throw new Exception(
        'GA4 API request failed: ' . curl_error($ch)
    );
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);


if ($httpCode !== 200) {
    throw new Exception(
        "GA4 API returned HTTP {$httpCode}: {$response}"
    );
}


/*
|--------------------------------------------------------------------------
| DECODE API RESPONSE
|--------------------------------------------------------------------------
*/

$data = json_decode($response, true);

if (
    !$data ||
    empty($data['success']) ||
    empty($data['report'])
) {
    throw new Exception(
        'Invalid GA4 API response: ' . $response
    );
}

$report = $data['report'];


/*
|--------------------------------------------------------------------------
| LOAD HTML TEMPLATE
|--------------------------------------------------------------------------
*/

if (!file_exists($templateFile)) {
    throw new Exception(
        'Email template not found: ' . $templateFile
    );
}

$html = file_get_contents($templateFile);


/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/

$logoBase64 = '';

if (file_exists($logoFile)) {

    $logoBase64 = base64_encode(
        file_get_contents($logoFile)
    );
}

// $html = str_replace(
//     '__TRIVIUM_LOGO_B64__',
//     $logoBase64,
//     $html
// );
// $logoUrl = 'https://triviumedu.com/api-campaign/assets/cte.png';

// $html = str_replace(
//     '__TRIVIUM_LOGO_URL__',
//     $logoUrl,
//     $html
// );
$logoUrl = 'https://triviumedu.com/api-campaign/assets/cte.png';

$logoBgColor = '#FFFFFF';

$html = str_replace(
    '__TRIVIUM_LOGO_URL__',
    $logoUrl,
    $html
);

$html = str_replace(
    '__TRIVIUM_LOGO_BG_COLOR__',
    $logoBgColor,
    $html
);

/*
|--------------------------------------------------------------------------
| BASIC REPORT DATA
|--------------------------------------------------------------------------
*/

$reportTitle = 'Trivium CTE · Website Analytics (GA4)';

$displayStart = formatDate($report['start'] ?? $startDate);
$displayEnd   = formatDate($report['end'] ?? $endDate);

$dateRange = $displayStart . ' → ' . $displayEnd;


/*
|--------------------------------------------------------------------------
| KPI ROWS
|--------------------------------------------------------------------------
*/

$kpiRows = '';

foreach ($report['kpis'] ?? [] as $index => $kpi) {

    $label = e($kpi['label'] ?? '');
    $value = e($kpi['value'] ?? '0');
    $desc  = e($kpi['desc'] ?? '');

    /*
     * Currently your API returns delta = 0.
     * Therefore don't show fake percentage changes.
     */

    $change = '';

    if (
        isset($kpi['delta']) &&
        is_numeric($kpi['delta']) &&
        (float)$kpi['delta'] != 0
    ) {

        $delta = (float)$kpi['delta'];

        $arrow = $delta >= 0 ? '▲' : '▼';

        $change = $arrow . ' ' . abs($delta) . '%';
    }

    $border = (
        $index === count($report['kpis']) - 1
    )
        ? 'border-bottom:none;'
        : '';

    $kpiRows .= '
        <tr>
            <td class="td" style="' . $border . '">
                <strong>' . $label . '</strong>
                <span style="color:#9CA0B3;">
                    — ' . $desc . '
                </span>
            </td>

            <td class="td"
                align="right"
                style="' . $border . 'font-family:Poppins,Arial,sans-serif;font-weight:800;">
                ' . $value . '
            </td>

            <td class="td"
                align="right"
                style="' . $border . 'font-weight:700;">
                ' . $change . '
            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| DAILY TRAFFIC
|--------------------------------------------------------------------------
*/

$dailyRows = '';

$daily = $report['daily'] ?? [];

$maxVisits = 0;

foreach ($daily as $day) {
    $maxVisits = max(
        $maxVisits,
        (int)($day['total'] ?? 0)
    );
}

// foreach ($daily as $day) {

//     $label = e($day['label'] ?? '');
//     $total = (int)($day['total'] ?? 0);

//     $percentage = $maxVisits > 0
//         ? round(($total / $maxVisits) * 100)
//         : 0;

//     $isBest =
//         !empty($report['bestDay']) &&
//         ($report['bestDay']['label'] ?? '') === ($day['label'] ?? '');

//     $isQuietest =
//         !empty($report['quietestDay']) &&
//         ($report['quietestDay']['label'] ?? '') === ($day['label'] ?? '');

//     $suffix = '';

//     if ($isBest) {
//         $suffix = ' — best day';
//     } elseif ($isQuietest) {
//         $suffix = ' — quietest';
//     }

//     $barColor = '#F0640A';

//     if ($isBest) {
//         $barColor = '#0E8FB5';
//     } elseif ($isQuietest) {
//         $barColor = '#E24B4B';
//     }

//     $dailyRows .= '
//         <tr>
//             <td class="bar-lbl">
//                 ' . $label . '
//                 <span style="float:right;font-weight:700;">
//                     ' . number_format($total) . $suffix . '
//                 </span>
//             </td>
//         </tr>

//         <tr>
//             <td>
//                 <table width="100%" cellpadding="0" cellspacing="0">
//                     <tr>
//                         <td width="' . $percentage . '%"
//                             bgcolor="' . $barColor . '"
//                             style="font-size:0;line-height:7px;">
//                             &nbsp;
//                         </td>

//                         <td bgcolor="#EDEDEB"
//                             style="font-size:0;line-height:7px;">
//                             &nbsp;
//                         </td>
//                     </tr>
//                 </table>
//             </td>
//         </tr>
//     ';
// }

// Sort daily data by total - highest first
usort($daily, function ($a, $b) {
    return (int)($b['total'] ?? 0) <=> (int)($a['total'] ?? 0);
});

foreach ($daily as $day) {

    $label = e($day['label'] ?? '');
    $total = (int)($day['total'] ?? 0);

    $percentage = $maxVisits > 0
        ? round(($total / $maxVisits) * 100)
        : 0;

    $isBest =
        !empty($report['bestDay']) &&
        ($report['bestDay']['label'] ?? '') === ($day['label'] ?? '');

    $isQuietest =
        !empty($report['quietestDay']) &&
        ($report['quietestDay']['label'] ?? '') === ($day['label'] ?? '');

    $suffix = '';

    if ($isBest) {
        $suffix = ' — best day';
    } elseif ($isQuietest) {
        $suffix = ' — quietest';
    }

    $barColor = '#F0640A';

    if ($isBest) {
        $barColor = '#0E8FB5';
    } elseif ($isQuietest) {
        $barColor = '#E24B4B';
    }

    $dailyRows .= '
        <tr>
            <td class="bar-lbl">
                ' . $label . '
                <span style="float:right;font-weight:700;">
                    ' . number_format($total) . $suffix . '
                </span>
            </td>
        </tr>

        <tr>
            <td>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="' . $percentage . '%"
                            bgcolor="' . $barColor . '"
                            style="font-size:0;line-height:7px;">
                            &nbsp;
                        </td>

                        <td bgcolor="#EDEDEB"
                            style="font-size:0;line-height:7px;">
                            &nbsp;
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    ';
}

/*
|--------------------------------------------------------------------------
| DEVICE ROWS
|--------------------------------------------------------------------------
*/

$deviceRows = '';

$device = $report['device'] ?? [];

$totalDeviceUsers = array_sum(
    array_column($device, 'value')
);

foreach ($device as $index => $item) {

    $label = e($item['label'] ?? '');
    $value = (int)($item['value'] ?? 0);

    $share = $totalDeviceUsers > 0
        ? round(($value / $totalDeviceUsers) * 100, 1)
        : 0;

    $border = (
        $index === count($device) - 1
    )
        ? 'border-bottom:none;'
        : '';

    $deviceRows .= '
        <tr>
            <td class="td" style="' . $border . '">
                ' . $label . '
            </td>

            <td class="td"
                align="right"
                style="' . $border . 'font-weight:700;">
                ' . number_format($value) . '
            </td>

            <td class="td"
                align="right"
                style="' . $border . '">
                ' . $share . '%
            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| COUNTRY ROWS
|--------------------------------------------------------------------------
*/

$countryRows = '';

$countries = $report['countries'] ?? [];

usort(
    $countries,
    fn($a, $b) =>
        ($b['value'] ?? 0) <=> ($a['value'] ?? 0)
);

foreach (array_slice($countries, 0, 10) as $index => $country) {

    $countryRows .= '
        <tr>
            <td class="td">' . ($index + 1) . '</td>

            <td class="td">
                ' . e($country['label'] ?? '') . '
            </td>

            <td class="td"
                align="right"
                style="font-weight:700;">
                ' . number_format((int)($country['value'] ?? 0)) . '
            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| ACQUISITION
|--------------------------------------------------------------------------
*/

$acquisitionRows = '';

foreach ($report['acquisition'] ?? [] as $item) {

    $acquisitionRows .= '
        <tr>
            <td class="td">
                <strong>' . e($item['label'] ?? '') . '</strong>
                <br>

                <span style="color:#9CA0B3;font-size:10.5px;">
                    ' . e($item['desc'] ?? '') . '
                </span>
            </td>

            <td class="td"
                align="right"
                style="font-weight:700;">
                ' . number_format((int)($item['visits'] ?? 0)) . '
            </td>

            <td class="td"
                align="right">
                ' . e($item['pct'] ?? 0) . '%
            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| PAGE VIEWS
|--------------------------------------------------------------------------
*/

$pageViewRows = '';

foreach ($report['pageViews'] ?? [] as $item) {

    $pageViewRows .= '
        <tr>
            <td class="td">
                ' . e($item['name'] ?? '') . '
            </td>

            <td class="td"
                align="right"
                style="font-weight:700;">
                ' . number_format((int)($item['views'] ?? 0)) . '
            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| TOP PAGES
|--------------------------------------------------------------------------
*/

$topPageRows = '';

foreach (array_slice($report['topPages'] ?? [], 0, 5) as $item) {

    $topPageRows .= '
        <tr>
            <td class="td">
                ' . e($item['name'] ?? '') . '
            </td>

            <td class="td"
                align="right">
                ' . number_format((int)($item['sessions'] ?? 0)) . '
            </td>

            <td class="td"
                align="right"
                style="font-weight:700;">
                ' . e($item['share'] ?? 0) . '%
            </td>

            <td class="td"
                align="right">
                ' . e($item['bounce'] ?? 0) . '%
            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| HIGHLIGHTS
|--------------------------------------------------------------------------
*/

$highlightCards = '';

foreach ($report['highlights'] ?? [] as $item) {

    $highlightCards .= '
        <td class="kpi-cell"
            width="25%"
            align="center"
            style="padding:8px 4px;">

            <div class="kpi-num" style="font-size:16px;">
                ' . e($item['value'] ?? 0) . '
            </div>

            <div class="kpi-lbl">
                ' . e($item['label'] ?? '') . '
            </div>

            <div class="kpi-desc">
                ' . e($item['note'] ?? '') . '
            </div>

        </td>
    ';
}


/*
|--------------------------------------------------------------------------
| BEHAVIOR
|--------------------------------------------------------------------------
*/

$behaviorRows = '';

foreach ($report['behavior'] ?? [] as $item) {

    $behaviorRows .= '
        <tr>
            <td class="td">
                ' . e($item['label'] ?? '') . '
            </td>

            <td class="td"
                align="right"
                style="font-weight:700;">
                ' . number_format((int)($item['value'] ?? 0)) . '
            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| VIDEO
|--------------------------------------------------------------------------
*/

$videoRows = '';

foreach ($report['video']['stages'] ?? [] as $index => $item) {

    $border = (
        $index === count($report['video']['stages']) - 1
    )
        ? 'border-bottom:none;'
        : '';

    $videoRows .= '
        <tr>
            <td class="td" style="' . $border . '">
                ' . e($item['label'] ?? '') . '

                <span style="color:#9CA0B3;">
                    — ' . e($item['note'] ?? '') . '
                </span>
            </td>

            <td class="td"
                align="right"
                style="' . $border . 'font-weight:700;">
                ' . number_format((int)($item['viewers'] ?? 0)) . '
            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| TOP CLICKS
|--------------------------------------------------------------------------
*/

$clickRows = '';

foreach ($report['topClicks'] ?? [] as $item) {

    $clickRows .= '
        <tr>
            <td style="padding:14px 16px;border-bottom:1px solid #EDEDEB;">

                <table width="100%" cellpadding="0" cellspacing="0">

                    <tr>

                        <td width="34" valign="top">

                            <div style="
                                width:30px;
                                height:30px;
                                background-color:#FDEEE0;
                                color:#F0640A;
                                border-radius:8px;
                                text-align:center;
                                line-height:30px;
                                font-family:Poppins,Arial,sans-serif;
                                font-weight:800;
                                font-size:13px;
                            ">
                                ' . number_format((int)($item['count'] ?? 0)) . '
                            </div>

                        </td>

                        <td style="padding-left:12px;">

                            <p style="
                                margin:0;
                                font-family:Poppins,Arial,sans-serif;
                                font-weight:700;
                                font-size:12.5px;
                                color:#0B0B0C;
                            ">
                                ' . e($item['label'] ?? '') . '
                            </p>

                            <p style="
                                margin:2px 0 0;
                                font-family:Arial,sans-serif;
                                font-size:11px;
                                color:#6B7085;
                            ">
                                ' . e($item['note'] ?? '') . '
                            </p>

                        </td>

                    </tr>

                </table>

            </td>
        </tr>
    ';
}


/*
|--------------------------------------------------------------------------
| BEST / QUIETEST
|--------------------------------------------------------------------------
*/

$bestDay = $report['bestDay'] ?? null;
$quietestDay = $report['quietestDay'] ?? null;

$bestDayLabel = $bestDay
    ? formatDay($bestDay['label'])
    : 'N/A';

$bestDayValue = $bestDay
    ? number_format((int)$bestDay['value'])
    : '0';

$quietestDayLabel = $quietestDay
    ? formatDay($quietestDay['label'])
    : 'N/A';

$quietestDayValue = $quietestDay
    ? number_format((int)$quietestDay['value'])
    : '0';


/*
|--------------------------------------------------------------------------
| REPLACE TEMPLATE VARIABLES
|--------------------------------------------------------------------------
*/

$variables = [

    '{{REPORT_TITLE}}'      => e($reportTitle),
    '{{DATE_RANGE}}'        => e($dateRange),

    '{{KPI_ROWS}}'          => $kpiRows,
    '{{DAILY_ROWS}}'        => $dailyRows,

    '{{BEST_DAY}}'          => e($bestDayLabel),
    '{{BEST_DAY_VALUE}}'    => $bestDayValue,

    '{{QUIETEST_DAY}}'      => e($quietestDayLabel),
    '{{QUIETEST_DAY_VALUE}}'=> $quietestDayValue,

    '{{TOTAL_VISITS}}'      => number_format(
        (int)($report['totalVisits'] ?? 0)
    ),

    '{{DEVICE_ROWS}}'       => $deviceRows,
    '{{COUNTRY_ROWS}}'      => $countryRows,
    '{{ACQUISITION_ROWS}}'  => $acquisitionRows,
    '{{PAGE_VIEW_ROWS}}'    => $pageViewRows,
    '{{TOP_PAGE_ROWS}}'     => $topPageRows,
    '{{HIGHLIGHT_CARDS}}'   => $highlightCards,
    '{{BEHAVIOR_ROWS}}'     => $behaviorRows,
    '{{VIDEO_ROWS}}'        => $videoRows,
    '{{CLICK_ROWS}}'        => $clickRows,
    // Optional
    '{{VIDEO_EVENT_TOTAL}}' => number_format(
        (int)($report['videoEventTotal'] ?? 0)
    ),
];

$html = str_replace(
    array_keys($variables),
    array_values($variables),
    $html
);
// print_r($html);
if (isset($_GET['view']) && $_GET['view'] === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}
/*
|--------------------------------------------------------------------------
| SEND EMAIL
|--------------------------------------------------------------------------
*/
$html = str_replace(
    array_keys($variables),
    array_values($variables),
    $html
);


/*
|--------------------------------------------------------------------------
| VIEW DYNAMIC DASHBOARD
|--------------------------------------------------------------------------
*/

if (isset($_GET['view']) && $_GET['view'] === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}


/*
|--------------------------------------------------------------------------
| SEND EMAIL
|--------------------------------------------------------------------------
*/

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'itadmin@navneet.org.in';
    $mail->Password   = 'N@vneet.@123aws';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->CharSet = 'UTF-8';

    $mail->setFrom(
        'itadmin@navneet.org.in',
        'TriviumCTE Website Analytics'
    );

    $mail->addAddress('tapas.mishra@algoqube.com');
    $mail->addAddress('navneet.verma@algoqube.com');
    // $mail->addAddress('aditya.bhatia@triviumedu.com');
    // $mail->addAddress('tim.connors@triviumedu.com');
    // $mail->addAddress('ridhima.moza@triviumedu.com');
    
    $ccRecipients = [
        'richa.chugh@triviumedu.com',
        'vikrant.singh@triviumedu.com',
        'ruchika.singh@triviumedu.com',
        'saurabh.rawat@triviumedu.com',
        'harsh.virmani@triviumedu.com',
        'sonam.kumari@triviumedu.com',
        'suraj.pandey@triviumedu.com'
    ];
    
    foreach ($ccRecipients as $cc) {
        // $mail->addCC($cc);
    }

    $mail->isHTML(true);

    $mail->Subject =
        'Trivium CTE — Website Analytics — ' . $dateRange;

    $mail->Body = $html;

    $mail->AltBody =
        'Trivium CTE Website Analytics Report: ' . $dateRange;

    $mail->send();

    echo "Analytics email sent successfully.";

} catch (Exception $e) {

    echo "Email failed: " . $mail->ErrorInfo;
}
/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatDate($date)
{
    $timestamp = strtotime($date);

    if (!$timestamp) {
        return $date;
    }

    return date('d M Y', $timestamp);
}


function formatDay($label)
{
    $timestamp = strtotime($label);

    if (!$timestamp) {
        return $label;
    }

    return date('M d (D)', $timestamp);
}