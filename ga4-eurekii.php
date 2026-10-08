<?php

require_once __DIR__ . '/vendor/autoload.php';

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;

header('Content-Type: application/json');

// $propertyId = '260338115';
// $propertyId = '524099388';//Trivium
$propertyId = '524089786';//Eurekii
// $propertyId = '524097368';//TriviumCTE

try {

    $client = new BetaAnalyticsDataClient([
        'credentials' => __DIR__ . '/credentials-ga.json'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Helper function
    |--------------------------------------------------------------------------
    string $startDate = '30daysAgo',
    string $endDate = 'today'
    */

    function runGA4Report(
        BetaAnalyticsDataClient $client,
        string $propertyId,
        array $dimensions,
        array $metrics,
        string $startDate,
        string $endDate 
    ) {

        $request = new RunReportRequest();

        $request->setProperty(
            'properties/' . $propertyId
        );

        $request->setDateRanges([
            new DateRange([
                'start_date' => $startDate,
                'end_date'   => $endDate
            ])
        ]);

        $dimensionObjects = [];

        foreach ($dimensions as $dimension) {
            $dimensionObjects[] = new Dimension([
                'name' => $dimension
            ]);
        }

        $metricObjects = [];

        foreach ($metrics as $metric) {
            $metricObjects[] = new Metric([
                'name' => $metric
            ]);
        }

        $request->setDimensions($dimensionObjects);
        $request->setMetrics($metricObjects);

        return $client->runReport($request);
    }


    /*
    |--------------------------------------------------------------------------
    | Date range
    |--------------------------------------------------------------------------
    */

    $startDate = $_GET['start_date'];// ?? '30daysAgo'; 
    $endDate   = $_GET['end_date'];// ?? 'today';
    


    /*
    |--------------------------------------------------------------------------
    | 1. KPI DATA
    |--------------------------------------------------------------------------
    */

    $kpiResponse = runGA4Report(
        $client,
        $propertyId,
        [],
        [
            'totalUsers',
            'newUsers',
            'engagedSessions',
            'sessions',
            'screenPageViews',
            'averageSessionDuration'
        ],
        $startDate,
        $endDate
    );

    $kpiRow = $kpiResponse->getRows()[0] ?? null;

    $totalUsers = 0;
    $newUsers = 0;
    $engagedSessions = 0;
    $sessions = 0;
    $screenPageViews = 0;
    $averageSessionDuration = 0;

    if ($kpiRow) {

        $metrics = $kpiRow->getMetricValues();

        $totalUsers = (int) $metrics[0]->getValue();
        $newUsers = (int) $metrics[1]->getValue();
        $engagedSessions = (int) $metrics[2]->getValue();
        $sessions = (int) $metrics[3]->getValue();
        $screenPageViews = (int) $metrics[4]->getValue();
        $averageSessionDuration = (float) $metrics[5]->getValue();
    }

    $newVisitorRate = $totalUsers > 0
        ? round(($newUsers / $totalUsers) * 100, 1)
        : 0;

    $engagedRate = $sessions > 0
        ? round(($engagedSessions / $sessions) * 100, 1)
        : 0;

    $pagesPerVisit = $sessions > 0
        ? round($screenPageViews / $sessions, 1)
        : 0;


    /*
    |--------------------------------------------------------------------------
    | Format duration
    |--------------------------------------------------------------------------
    */

    $minutes = floor($averageSessionDuration / 60);
    $seconds = round($averageSessionDuration % 60);

    $timeSpent = sprintf(
        '%02d:%02d',
        $minutes,
        $seconds
    );


    /*
    |--------------------------------------------------------------------------
    | 2. DAILY DATA
    |--------------------------------------------------------------------------
    */

    $dailyResponse = runGA4Report(
        $client,
        $propertyId,
        ['date'],
        [
            'totalUsers',
            'engagedSessions',
            'sessions',
            'screenPageViews'
        ],
        $startDate,
        $endDate
    );

    $daily = [];

    foreach ($dailyResponse->getRows() as $row) {

        $dimensions = $row->getDimensionValues();
        $metrics = $row->getMetricValues();

        $date = $dimensions[0]->getValue();

        $dateObject = DateTime::createFromFormat(
            'Ymd',
            $date
        );

        $label = $dateObject
            ? $dateObject->format('M d')
            : $date;

        $daily[] = [
            'label'   => $label,
            'unique'  => (int) $metrics[0]->getValue(),
            'engaged' => (int) $metrics[1]->getValue(),
            'total'   => (int) $metrics[3]->getValue()
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 3. DEVICE DATA
    |--------------------------------------------------------------------------
    */

    $deviceResponse = runGA4Report(
        $client,
        $propertyId,
        ['deviceCategory'],
        ['totalUsers'],
        $startDate,
        $endDate
    );

    $device = [];

    foreach ($deviceResponse->getRows() as $row) {

        $dimensions = $row->getDimensionValues();
        $metrics = $row->getMetricValues();

        $deviceName = $dimensions[0]->getValue();

        $labelMap = [
            'desktop' => 'Computer / Laptop',
            'mobile'  => 'Mobile Phone',
            'tablet'  => 'Tablet'
        ];

        $device[] = [
            'label' => $labelMap[$deviceName] ?? ucfirst($deviceName),
            'value' => (int) $metrics[0]->getValue()
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 4. COUNTRY DATA
    |--------------------------------------------------------------------------
    */

    $countryResponse = runGA4Report(
        $client,
        $propertyId,
        ['country'],
        ['totalUsers'],
        $startDate,
        $endDate
    );

    $countries = [];

    foreach ($countryResponse->getRows() as $row) {

        $dimensions = $row->getDimensionValues();
        $metrics = $row->getMetricValues();

        $countries[] = [
            'label' => $dimensions[0]->getValue(),
            'value' => (int) $metrics[0]->getValue()
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 5. ACQUISITION DATA
    |--------------------------------------------------------------------------
    */

    $acquisitionResponse = runGA4Report(
        $client,
        $propertyId,
        ['sessionDefaultChannelGroup'],
        ['sessions'],
        $startDate,
        $endDate
    );

    $acquisition = [];

    foreach ($acquisitionResponse->getRows() as $row) {

        $dimensions = $row->getDimensionValues();
        $metrics = $row->getMetricValues();

        $channel = $dimensions[0]->getValue();
        $visits = (int) $metrics[0]->getValue();

        $pct = $sessions > 0
            ? round(($visits / $sessions) * 100, 1)
            : 0;

        $descriptions = [
            'Direct' =>
                'They already know us — typed our URL directly or have us saved',

            'Organic Search' =>
                'Searched something on Google and clicked our result. Free traffic — no ads!',

            'Referral' =>
                'Another site linked to us and someone followed that link',

            'Organic Social' =>
                'Visitors came from social media',

            'Paid Search' =>
                'Visitors came through paid search advertising',

            'Email' =>
                'Visitors came from email campaigns'
        ];

        $description = 'Traffic source identified by Google Analytics.';

        foreach ($descriptions as $key => $desc) {

            if (stripos($channel, $key) !== false) {
                $description = $desc;
                break;
            }
        }

        $acquisition[] = [
            'label'  => $channel,
            'visits' => $visits,
            'pct'    => $pct,
            'desc'   => $description
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 6. PAGE VIEWS
    |--------------------------------------------------------------------------
    */

    $pageResponse = runGA4Report(
        $client,
        $propertyId,
        ['pageTitle'],
        ['screenPageViews'],
        $startDate,
        $endDate
    );

    $pageViews = [];

    foreach ($pageResponse->getRows() as $row) {

        $dimensions = $row->getDimensionValues();
        $metrics = $row->getMetricValues();

        $pageViews[] = [
            'name'  => $dimensions[0]->getValue(),
            'views' => (int) $metrics[0]->getValue()
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 7. TOP PAGES
    |--------------------------------------------------------------------------
    */

    $topPageResponse = runGA4Report(
        $client,
        $propertyId,
        ['pageTitle'],
        [
            'screenPageViews',
            'sessions',
            'bounceRate'
        ],
        $startDate,
        $endDate
    );

    $topPages = [];

    foreach ($topPageResponse->getRows() as $row) {

        $dimensions = $row->getDimensionValues();
        $metrics = $row->getMetricValues();

        $views = (int) $metrics[0]->getValue();

        $share = $screenPageViews > 0
            ? round(($views / $screenPageViews) * 100, 1)
            : 0;

        $topPages[] = [
            'name'    => $dimensions[0]->getValue(),
            'share'   => $share,
            'bounce'  => round((float) $metrics[2]->getValue() * 100, 1),
            'sessions'=> (int) $metrics[1]->getValue()
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 8. CUSTOM EVENTS
    |--------------------------------------------------------------------------
    */

    $eventResponse = runGA4Report(
        $client,
        $propertyId,
        ['eventName'],
        ['eventCount'],
        $startDate,
        $endDate
    );

    $events = [];

    foreach ($eventResponse->getRows() as $row) {

        $dimensions = $row->getDimensionValues();
        $metrics = $row->getMetricValues();

        $events[$dimensions[0]->getValue()] =
            (int) $metrics[0]->getValue();
    }


    /*
    |--------------------------------------------------------------------------
    | Event values
    |--------------------------------------------------------------------------
    */

    $formStarted =
        $events['form_start'] ?? 0;

    $formSubmitted =
        $events['form_submit'] ?? 0;

    $videoStarted =
        $events['video_start'] ?? 0;

    $videoProgress =
        $events['video_progress'] ?? 0;

    $videoCompleted =
        $events['video_complete'] ?? 0;

    $fileDownloads =
        $events['file_download'] ?? 0;


    /*
    |--------------------------------------------------------------------------
    | 9. HIGHLIGHTS
    |--------------------------------------------------------------------------
    */

    $highlights = [

        [
            'label' => 'Page Views',
            'value' => $screenPageViews,
            'note' => 'Times any page was opened'
        ],

        [
            'label' => 'Scrolled Down',
            'value' => $events['scroll'] ?? 0,
            'note' => 'People read far enough to scroll'
        ],

        [
            'label' => 'Form Started',
            'value' => $formStarted,
            'note' => 'Began filling out a contact form'
        ],

        [
            'label' => 'Form Submitted',
            'value' => $formSubmitted,
            'note' => 'Actually sent us a message'
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | 10. BEHAVIOR
    |--------------------------------------------------------------------------
    */

    $behavior = [

        [
            'label' => 'Started a Session',
            'value' => $sessions
        ],

        [
            'label' => 'Viewed a Page',
            'value' => $screenPageViews
        ],

        [
            'label' => 'First Visit',
            'value' => $newUsers
        ],

        [
            'label' => 'Engaged with Content',
            'value' => $engagedSessions
        ],

        [
            'label' => 'Scrolled Down',
            'value' => $events['scroll'] ?? 0
        ],

        [
            'label' => 'Watching the Video',
            'value' => $videoStarted
        ],

        [
            'label' => 'Clicked Something',
            'value' => $events['click'] ?? 0
        ],

        [
            'label' => 'File Download',
            'value' => $fileDownloads
        ],

        [
            'label' => 'Started a Video',
            'value' => $videoStarted
        ],

        [
            'label' => 'Started a Form',
            'value' => $formStarted
        ],

        [
            'label' => 'Submitted a Form',
            'value' => $formSubmitted
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | 11. VIDEO
    |--------------------------------------------------------------------------
    */

    $video = [

        'totalEvents' =>
            $videoStarted +
            $videoProgress +
            $videoCompleted,

        'stages' => [

            [
                'label' => 'Started Watching',
                'viewers' => $videoStarted,
                'note' => 'Pressed play on the video'
            ],

            [
                'label' => 'Watched Part of It',
                'viewers' => $videoProgress,
                'note' => 'Watched 25–75% of the video'
            ],

            [
                'label' => 'Watched It Fully',
                'viewers' => $videoCompleted,
                'note' => 'Watched the entire video to the end'
            ]
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | 12. TOP CLICKS
    |--------------------------------------------------------------------------
    |
    | This uses eventName for now.
    |
    | For detailed URLs/pages, you should additionally send
    | event parameters such as link_url and page_location.
    |
    */

    $clickEvents = [
        'contact_sales_click' => 'Contact Sales Button',
        'outbound_click'      => 'Outbound Link',
        'file_download'       => 'File Download'
    ];

    $topClicks = [];

    foreach ($clickEvents as $eventName => $label) {

        if (!isset($events[$eventName])) {
            continue;
        }

        $topClicks[] = [
            'label' => $label,
            'count' => $events[$eventName],
            'url'   => '',
            'pages' => [],
            'note'  => 'Tracked using Google Analytics event'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 13. BEST / QUIETEST DAY
    |--------------------------------------------------------------------------
    */

    $bestDay = null;
    $quietestDay = null;

    if (!empty($daily)) {

        $best = $daily[0];
        $quiet = $daily[0];

        foreach ($daily as $day) {

            if ($day['total'] > $best['total']) {
                $best = $day;
            }

            if ($day['total'] < $quiet['total']) {
                $quiet = $day;
            }
        }

        $bestDate = DateTime::createFromFormat(
            'M d',
            $best['label']
        );

        $quietDate = DateTime::createFromFormat(
            'M d',
            $quiet['label']
        );

        $bestDay = [
            'label' => $best['label'],
            'value' => $best['total']
        ];

        $quietestDay = [
            'label' => $quiet['label'],
            'value' => $quiet['total']
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 14. REPORT OBJECT
    |--------------------------------------------------------------------------
    */

    $report = [

        'id' => 'triv-ga4-report',

        'name' =>$startDate . ' - ' . $endDate,
            //date('M d', strtotime('-30 days'))
            //. ' – '
            //. date('M d, Y'),

        'status' => 'completed',

        'start' => $startDate,
            //date('Y-m-d', strtotime('-30 days')),

        'end' =>$endDate,
            //date('Y-m-d'),

        'placeholder' => false,


        /*
        |--------------------------------------------------------------------------
        | KPIs
        |--------------------------------------------------------------------------
        */

        'kpis' => [

            [
                'label' => 'People Who Visited',
                'value' => (string) $totalUsers,
                'delta' => 0,
                'desc' => 'Unique people who visited our site'
            ],

            [
                'label' => 'First-Time Visitors',
                'value' => (string) $newUsers,
                'delta' => 0,
                'desc' => 'People visiting for the very first time'
            ],

            [
                'label' => 'New Visitor Rate',
                'value' => $newVisitorRate . '%',
                'delta' => 0,
                'desc' => 'Share of visitors that were first-time visitors'
            ],

            [
                'label' => 'Visitors Who Engaged',
                'value' => $engagedRate . '%',
                'delta' => 0,
                'desc' => 'Share of sessions that were engaged'
            ],

            [
                'label' => 'Pages per Visit',
                'value' => (string) $pagesPerVisit,
                'delta' => 0,
                'desc' => 'Average number of pages viewed per session'
            ],

            [
                'label' => 'Time Spent on Site',
                'value' => $timeSpent,
                'delta' => 0,
                'desc' => 'Average time a visitor spent on the site'
            ]
        ],


        /*
        |--------------------------------------------------------------------------
        | Other dashboard sections
        |--------------------------------------------------------------------------
        */

        'daily' => $daily,

        'totalVisits' => $sessions,

        'bestDay' => $bestDay,

        'quietestDay' => $quietestDay,

        'device' => $device,

        'countries' => $countries,

        'acquisition' => $acquisition,

        'pageViews' => $pageViews,

        'topPages' => $topPages,

        'highlights' => $highlights,

        'behavior' => $behavior,

        'video' => $video,

        'topClicks' => $topClicks
    ];


    /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'report' => $report
    ], JSON_PRETTY_PRINT);


} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

// require_once __DIR__ . '/vendor/autoload.php';

// use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
// use Google\Analytics\Data\V1beta\DateRange;
// use Google\Analytics\Data\V1beta\Dimension;
// use Google\Analytics\Data\V1beta\Metric;
// use Google\Analytics\Data\V1beta\RunReportRequest;

// header('Content-Type: application/json');

// $propertyId = '260338115';

// try {

//     $client = new BetaAnalyticsDataClient([
//         'credentials' => __DIR__ . '/credentials.json'
//     ]);

//     /*
//      * Create RunReportRequest object
//      */
//     $request = new RunReportRequest();

//     $request->setProperty(
//         'properties/' . $propertyId
//     );

//     /*
//      * Date range
//      */
//     $request->setDateRanges([
//         new DateRange([
//             'start_date' => '30daysAgo',
//             'end_date'   => 'today'
//         ])
//     ]);

//     /*
//      * Dimensions
//      */
//     $request->setDimensions([
//         new Dimension([
//             'name' => 'date'
//         ])
//     ]);

//     /*
//      * Metrics
//      */
//     $request->setMetrics([
//         new Metric([
//             'name' => 'activeUsers'
//         ]),
//         new Metric([
//             'name' => 'sessions'
//         ]),
//         new Metric([
//             'name' => 'screenPageViews'
//         ])
//     ]);

//     /*
//      * Run report
//      */
//     $response = $client->runReport($request);

//     $data = [];

//     foreach ($response->getRows() as $row) {

//         $dimensions = $row->getDimensionValues();
//         $metrics = $row->getMetricValues();

//         $data[] = [
//             'date' => $dimensions[0]->getValue(),

//             'activeUsers' =>
//                 (int) $metrics[0]->getValue(),

//             'sessions' =>
//                 (int) $metrics[1]->getValue(),

//             'pageViews' =>
//                 (int) $metrics[2]->getValue()
//         ];
//     }

//     echo json_encode([
//         'success' => true,
//         'data' => $data
//     ]);

// } catch (Throwable $e) {

//     http_response_code(500);

//     echo json_encode([
//         'success' => false,
//         'error' => $e->getMessage()
//     ]);
// }