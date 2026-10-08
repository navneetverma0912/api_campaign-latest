<?php

require_once __DIR__ . '/dashboard-api-auth.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------

function fetchCampaignData(string $apiUrl): array
{
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => dashboardApiAuthHeaders(),
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("API request failed: {$err}");
    }
    curl_close($ch);

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['success'])) {
        $msg = $data['message'] ?? 'Unknown error from campaign API';
        throw new RuntimeException("Campaign API returned an error: {$msg}");
    }
    return $data;
}

function initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function formatDate(?string $raw): string
{
    if (!$raw) {
        return '—';
    }
    $ts = strtotime($raw);
    return $ts ? date('d M Y', $ts) : $raw;
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function pct($value): string
{
    return number_format((float)$value, 1) . '%';
}

function buildContactRows(array $contacts): string
{
    if (empty($contacts)) {
        return '<tr><td style="padding:16px 18px;font-family:Arial,sans-serif;font-size:12px;color:#9CA0B3;">No contacts have clicked yet.</td></tr>';
    }

    $rowTpl = file_get_contents(__DIR__ . '/templates/contact-row.html');
    $rows   = '';

    foreach ($contacts as $c) {
        $name  = trim($c['name'] ?? '') ?: 'Unknown contact';
        $email = trim($c['email'] ?? '');
        $phone = trim($c['phone'] ?? '');

        $row = strtr($rowTpl, [
            '{{INITIALS}}'     => e(initials($name)),
            '{{NAME}}'         => e($name),
            '{{ORG}}'          => e($c['org'] ?: '-'),
            '{{EMAIL}}'        => e($email),
            '{{EMAIL_HREF}}'   => e($email ?: '#'),
            '{{PHONE}}'        => e($phone),
            '{{PHONE_HREF}}'   => e(preg_replace('/[^0-9+]/', '', $phone)),
            '{{SENT_DATE}}'    => e(formatDate($c['sent'] ?? null)),
            '{{CLICKED_DATE}}' => e(formatDate($c['clicked'] ?? null)),
            '{{STATUS}}' => e($c['status'] ?? ''),
        ]);

        if ($email === '') {
            $row = preg_replace('#<p[^>]*>\s*<a href="mailto:.*?</a>\s*</p>#s', '', $row);
        }
        if ($phone === '') {
            $row = preg_replace('#<p[^>]*>\s*<a href="tel:.*?</a>\s*</p>#s', '', $row);
        }
        if (($c['status'] ?? '') === 'Genuine') {
            $rows .= $row;
        }
    }
    return $rows;
}
function buildTrackingUrlRows(array $trackingUrls): string
{
    if (empty($trackingUrls)) {
        return '<tr>
            <td style="padding:16px 18px;font-family:Arial,sans-serif;font-size:12px;color:#9CA0B3;">
                No tracking URLs found.
            </td>
        </tr>';
    }

    $rowTpl = file_get_contents(__DIR__ . '/templates/tracking-url-row.html');
    $rows = '';

    foreach ($trackingUrls as $url) {
        $name = trim($url['name'] ?? '') ?: 'Unknown URL';

        $rows .= strtr($rowTpl, [
            '{{URL_NAME}}'      => e($name),
            '{{TOTAL_CLICKS}}'  => (string)($url['total_clicks'] ?? 0),
            '{{UNIQUE_CLICKS}}' => (string)($url['unique_clicks'] ?? 0),
            '{{URL}}'           => e($url['url'] ?? '#'),
        ]);
    }

    return $rows;
}
function buildReportHtml(array $campaign, array $config): string
{
    $tpl = file_get_contents(__DIR__ . '/templates/report-template.html');

    $summary = $campaign['summary'];
    $rates   = $campaign['rates'];
    $engaged = count($campaign['contacts']);
    $trackingUrls = $summary['trackingUrls'] ?? [];

    // $logo = '';
    // if (!empty($config['logo_path']) && is_file($config['logo_path'])) {
    //     $logo = base64_encode(file_get_contents($config['logo_path']));
    // }
    // if ($logo === '') {
    //     $tpl = preg_replace('/<!-- LOGO -->.*?<!-- \/LOGO -->/s', '', $tpl);
    // }
    $logo = 'https://triviumedu.com/api-campaign/assets/eurekii-logo.png';
    // $logoUrl = 'https://triviumedu.com/api-campaign/assets/trivium-logo.png';

    $logoBgColor = '#000000';
    
    // $html = str_replace(
    //     '__TRIVIUM_LOGO_URL__',
    //     $logo,
    //     $html
    // );
    
    // $html = str_replace(
    //     '__TRIVIUM_LOGO_BG_COLOR__',
    //     $logoBgColor,
    //     $html
    // );
    $replacements = [
        '__TRIVIUM_LOGO_BG_COLOR__'=>$logoBgColor,
        '__TRIVIUM_LOGO_URL__'   => $logo,
        '{{CAMPAIGN_NAME}}'      => e($campaign['name']),
        '{{CAMPAIGN_STATUS}}'    => e($campaign['status']),
        '{{DATE_RANGE}}'         => e(formatDate($campaign['start']) . ' - ' . formatDate($campaign['end'])),

        '{{KPI_SENT}}'           => (string)$summary['sent'],
        '{{KPI_OPEN_RATE}}'      => pct($rates['openRate']),
        '{{KPI_CTOR}}'           => pct($rates['ctor']),
        '{{KPI_OPTOUT}}'         => pct($rates['optOutRate']),
        '{{KPI_ENGAGED}}'        => (string)$engaged,

        '{{FUNNEL_SENT}}'        => (string)$summary['sent'],
        '{{FUNNEL_OPENED}}'      => (string)$summary['opened'],
        '{{FUNNEL_OPENED_PCT}}'  => pct($rates['openRate']) . ' of prior',
        '{{FUNNEL_CLICKED}}'     => (string)$summary['clicked'],
        '{{FUNNEL_CLICKED_PCT}}' => pct($rates['clickRate']) . ' of prior',
        '{{FUNNEL_OPTOUT}}'      => (string)$summary['optOut'],
        '{{FUNNEL_OPTOUT_PCT}}'  => pct($rates['optOutRate']) . ' of prior',

        '{{RATE_OPEN}}'          => pct($rates['openRate']),
        '{{RATE_CLICK}}'         => pct($rates['clickRate']),
        '{{RATE_CTOR}}'          => pct($rates['ctor']),
        '{{RATE_OPTOUT}}'        => pct($rates['optOutRate']),

        '{{CONTACT_ROWS}}'       => buildContactRows($campaign['contacts']),
        '{{CONTACT_COUNT}}'      => (string)$engaged,

        '{{DASHBOARD_URL}}'      => e($config['dashboard_url'] ?? '#'),
        '{{TRACKING_URL_ROWS}}' => buildTrackingUrlRows($trackingUrls),
        '{{TRACKING_URL_COUNT}}' => (string)count($trackingUrls),
        '{{PREHEADER}}'          => e(sprintf(
            '%s: %d sent, %d opened (%s), %d clicked, %d engaged contact(s) inside.',
            $campaign['name'],
            $summary['sent'],
            $summary['opened'],
            pct($rates['openRate']),
            $summary['clicked'],
            $engaged
        )),
    ];

    return strtr($tpl, $replacements);
}

function sendReport(array $to, string $subject, string $html, array $config): void
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $config['smtp_pass'];
        $mail->SMTPSecure = $config['smtp_secure']; // 'tls' or 'ssl'
        $mail->Port       = $config['smtp_port'];

        $mail->setFrom($config['from_email'], $config['from_name']);
        foreach ($to as $recipient) {
            $mail->addAddress(trim($recipient));
        }
        foreach ((array)($config['bcc'] ?? []) as $bcc) {
            $mail->addBCC(trim($bcc));
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = 'This email contains an HTML campaign report. Please view it in an HTML-capable email client.';

        $mail->send();
        echo "Sent: {$subject} -> " . implode(', ', $to) . PHP_EOL;
    } catch (Exception $e) {
        fwrite(STDERR, "Failed to send '{$subject}': {$mail->ErrorInfo}" . PHP_EOL);
    }
}

// ---------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------

// new
function sendCampaignReportById(string $campaignId, array $recipients, array $config): bool
{
    error_log("Sending report for campaign: {$campaignId}");
    error_log("Recipients: " . implode(', ', $recipients));

    if (empty($recipients)) {
        // fwrite(STDERR, "No recipients for campaign {$campaignId}, skipping.\n");
        return false;
    }

    try {
        $data = fetchCampaignData($config['api_url']);

        $campaigns = array_values(array_filter(
            $data['campaigns'],
            fn($c) => (string)$c['id'] === (string)$campaignId
        ));

        if (empty($campaigns)) {
            // fwrite(STDERR, "Campaign {$campaignId} not found in API response.\n");
            return false;
        }

        $campaign = $campaigns[0];

        $html = buildReportHtml($campaign, $config);

        $subject = sprintf(
            '%s Report - %s',
            $config['report_prefix'] ?? 'Campaign Analytics',
            $campaign['name']
        );

        echo "Sending Report: {$subject}" . PHP_EOL;

        sendReport($recipients, $subject, $html, $config);

        return true;

    } catch (Throwable $e) {
        fwrite(
            STDERR,
            "Failed campaign {$campaignId}: " . $e->getMessage() . PHP_EOL
        );

        error_log(
            "Failed campaign {$campaignId}: " . $e->getMessage()
        );

        return false;
    }
}

    if (!is_file(__DIR__ . '/config-eurekii.php')) {
        fwrite(STDERR, "Missing config-eurekii.php — copy config.example.php to config-eurekii.php and fill it in first.\n");
        exit(1);
    }
    $config = require __DIR__ . '/config-eurekii.php';

    $options = getopt('', ['campaign::', 'to::', 'dry-run', 'days::']);
    $dryRun  = array_key_exists('dry-run', $options);

    $data      = fetchCampaignData($config['api_url']);
    $campaigns = $data['campaigns'];

    if (!empty($options['campaign'])) {
        $campaigns = array_values(array_filter(
            $campaigns,
            fn($c) => (string)$c['id'] === (string)$options['campaign']
        ));
    }

    $days = isset($options['days']) ? (int)$options['days'] : ($config['days_back'] ?? 60);
    if ($days > 0) {
        $cutoff = strtotime("-{$days} days");
        $campaigns = array_values(array_filter(
            $campaigns,
            function ($c) use ($cutoff) {
                $ts = strtotime($c['start'] ?? '');
                return $ts !== false && $ts >= $cutoff;
            }
        ));
    }

    if (empty($campaigns)) {
        fwrite(STDERR, "No matching campaigns in the selected window.\n");
        exit(1);
    }

    $recipients = !empty($options['to'])
        ? explode(',', $options['to'])
        : $config['recipients'];

    if ($dryRun) {
        $outDir = __DIR__ . '/preview';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
    }

if (!defined('CAMPAIGN_REPORT_INCLUDE_ONLY')) {

    foreach ($campaigns as $campaign) {
        $html    = buildReportHtml($campaign, $config);
        $subject = sprintf('%s Report - %s', $config['report_prefix'] ?? 'Campaign Analytics', $campaign['name']);

        if ($dryRun) {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $campaign['name']);
            $path = "{$outDir}/{$campaign['id']}-{$slug}.html";
            file_put_contents($path, $html);
            echo "Rendered: {$subject} -> {$path}" . PHP_EOL;
            continue;
        }
        sendReport($recipients, $subject, $html, $config);
    }
}