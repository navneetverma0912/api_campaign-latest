<?php
/**
 * api.php: all SQL for the Weekly Sales Review lives here.
 * Each query is its own PHP function; the router at the bottom maps ?action=... to one of them.
 * Targets the EspoCRM MySQL schema (tables: user, lead, opportunity, task, account).
 */
require_once '/home/jtrivium/public_html/api-campaign/dashboard-auth.php';

if (!isDashboardAdmin()) {
    header('Location: ../login.php');
    exit;
}
header('Content-Type: application/json; charset=utf-8');

/* ---------- connection ---------- */

function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=jtrivium_TMS;charset=utf8mb4',
            'jtrivium_TMS',
            'Test@2024',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }

    return $pdo;
}
function run(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function validDate(?string $s): string {
    if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        throw new InvalidArgumentException('Invalid date');
    }
    return $s;
}

const TASK_COLS = "t.id, t.name AS subject, t.status, t.date_end AS due, t.date_completed AS done,
    t.parent_type, t.parent_id, t.assigned_user_id AS rep_id,
    COALESCE(NULLIF(CONCAT_WS(' ', pl.first_name, pl.last_name), ''), po.name) AS parent_name,CONCAT_WS(' ', pl.first_name, pl.last_name) as lead_name, pl.c_designation as designation,pl.c_school_category as school_category,pl.c_company_name as company_name,pl.c_csource as source";
const TASK_FROM = "FROM task t
    LEFT JOIN lead pl ON t.parent_type = 'Lead' AND pl.id = t.parent_id AND pl.deleted = 0 
    LEFT JOIN opportunity po ON t.parent_type = 'Opportunity' AND po.id = t.parent_id AND po.deleted = 0 AND po.c_parent = 'trivium'";

function taskRows(string $where, array $p, ?string $rep, string $order, string $extra = ''): array {
    $sql = "SELECT " . TASK_COLS . $extra . " " . TASK_FROM . " WHERE t.deleted = 0 AND $where";
    if ($rep) { $sql .= " AND pl.c_parent = 'trivium' AND t.assigned_user_id = :rep"; $p['rep'] = $rep; }
    return run($sql . " ORDER BY $order", $p);
}
function qTasksDue(string $from, string $to, ?string $rep): array {
    return taskRows("DATE(t.date_end) BETWEEN :f AND :t", ['f'=>$from,'t'=>$to], $rep, "t.date_end");
}
function qTasksCompleted(string $from, string $to, ?string $rep): array {
    return taskRows("t.status = 'Completed' AND DATE(t.date_completed) BETWEEN :f AND :t",
                    ['f'=>$from,'t'=>$to], $rep, "t.date_completed");
}
function qOverdueTasks(string $today, ?string $rep): array {
    return taskRows("t.status NOT IN ('Completed','Canceled') AND DATE(t.date_end) < :today2",
                    ['today'=>$today,'today2'=>$today], $rep, "t.date_end ASC",
                    ", DATEDIFF(:today, DATE(t.date_end)) AS days_late");
}
function qUpcomingTasks(string $from, string $to, ?string $rep): array {
    return taskRows("t.status NOT IN ('Completed','Canceled') AND DATE(t.date_end) BETWEEN :f AND :t",
                    ['f'=>$from,'t'=>$to], $rep, "t.date_end");
}
/* ---------- queries ---------- */

/** Active salespeople. */
function qReps(): array {
    return run("SELECT id, user_name, CONCAT_WS(' ', first_name, last_name) AS name
                FROM user
                WHERE deleted = 0 AND is_active = 1 AND type IN ('regular','admin') AND c_parent='trivium'
                ORDER BY first_name");
}

/** Open leads (New / Assigned / In Process), optionally for one rep. */
// function qOpenLeads(?string $rep): array {
//     $sql = "SELECT l.id, CONCAT_WS(' ', l.first_name, l.last_name) AS name, l.status, l.source,
//                   l.created_at, l.modified_at AS last_activity, l.assigned_user_id AS rep_id
//             FROM lead l
//             WHERE l.deleted = 0 AND l.status IN ('New','Assigned','In Process') AND l.c_parent = 'trivium'";
//     $p = [];
//     if ($rep) { $sql .= " AND l.assigned_user_id = :rep"; $p['rep'] = $rep; }
//     return run($sql . " ORDER BY l.created_at DESC", $p);
// }
/** Open leads (New / Assigned / In Process), optionally for one rep and a created date range. */
function qOpenLeads(?string $rep, ?string $from = null, ?string $to = null): array {
    $sql = "SELECT l.id, CONCAT_WS(' ', l.first_name, l.last_name) AS name, l.status, l.source,
                   l.created_at, l.modified_at AS last_activity, l.assigned_user_id AS rep_id,l.c_lead_value
            FROM lead l
            WHERE l.deleted = 0 AND l.status IN ('New','Assigned','In Process') AND l.c_parent = 'trivium'";
    $p = [];
    if ($rep) { $sql .= " AND l.assigned_user_id = :rep"; $p['rep'] = $rep; }
    // if ($from) { $sql .= " AND l.created_at >= :from"; $p['from'] = $from; }
    // if ($to)   { $sql .= " AND l.created_at < DATE_ADD(:to, INTERVAL 1 DAY)"; $p['to'] = $to; }
    return run($sql . " ORDER BY l.created_at DESC", $p);
}
/** Leads created inside a date range. */
function qNewLeads(string $from, string $to, ?string $rep): array {
    $sql = "SELECT l.id, CONCAT_WS(' ', l.first_name, l.last_name) AS name, l.status, l.source,
                   l.created_at, l.modified_at AS last_activity, l.assigned_user_id AS rep_id,l.c_lead_value
            FROM lead l
            WHERE l.deleted = 0 AND l.status IN ('New','Assigned','In Process') AND DATE(l.created_at) BETWEEN :f AND :t AND l.c_parent = 'trivium' AND l.c_lead_status= 'New Lead'";
    $p = ['f' => $from, 't' => $to];
    if ($rep) { $sql .= " AND l.assigned_user_id = :rep"; $p['rep'] = $rep; }
    return run($sql . " ORDER BY l.created_at DESC", $p);
}


/** Closed (Won/Lost) amount per rep, optionally within a created date range. Returns [rep_id => total]. */
function qClosePipelineByRep(?string $rep = null, ?string $from = null, ?string $to = null): array {
    $sql = "SELECT co.assigned_user_id AS rep_id, COALESCE(SUM(co.amount), 0) AS total
            FROM opportunity co
            WHERE co.deleted = 0
              AND co.stage IN ('Closed Won', 'Closed Lost')
              AND co.c_parent = 'trivium'";
    $p = [];

    if ($rep)  { $sql .= " AND co.assigned_user_id = :rep"; $p['rep'] = $rep; }
    if ($from) { $sql .= " AND co.created_at >= :from"; $p['from'] = $from; }
    if ($to)   { $sql .= " AND co.created_at < DATE_ADD(:to, INTERVAL 1 DAY)"; $p['to'] = $to; }

    $rows = run($sql . " GROUP BY co.assigned_user_id", $p);

    $map = [];
    foreach ($rows as $r) {
        $map[$r['rep_id']] = (float)$r['total'];
    }
    return $map;
}
function qOpenOpportunities(?string $rep, ?string $stage, ?string $from = null, ?string $to = null): array {
    $sql = "SELECT
                o.id,
                o.name,
                a.name AS account,
                o.stage,
                o.amount,
                o.amount_currency AS currency,
                o.close_date,
                o.created_at,
                o.modified_at AS last_activity,
                o.assigned_user_id AS rep_id
            FROM opportunity o
            LEFT JOIN account a
                ON a.id = o.account_id AND a.deleted = 0
            WHERE o.deleted = 0
              AND o.stage NOT IN ('Closed Won', 'Closed Lost')
              AND o.c_parent = 'trivium'";

    $p = [];

    if ($rep)   { $sql .= " AND o.assigned_user_id = :rep"; $p['rep'] = $rep; }
    if ($stage) { $sql .= " AND o.stage = :stage"; $p['stage'] = $stage; }
    // if ($from)  { $sql .= " AND o.created_at >= :from"; $p['from'] = $from; }
    // if ($to)    { $sql .= " AND o.created_at < DATE_ADD(:to, INTERVAL 1 DAY)"; $p['to'] = $to; }

    $rows = run($sql . " ORDER BY o.close_date ASC", $p);

    // Closed totals per rep
    $closeMap = qClosePipelineByRep($rep, $from, $to);

    // Merge close_pipeline into the open opportunity rows
    $repsWithOpen = [];
    foreach ($rows as &$row) {
        $row['close_pipeline'] = $closeMap[$row['rep_id']] ?? 0;
        $row['placeholder']    = false;
        $repsWithOpen[$row['rep_id']] = true;
    }
    unset($row);

    // Reps with closed deals but no open opportunities still get a row
    foreach ($closeMap as $repId => $total) {
        if (!isset($repsWithOpen[$repId])) {
            $rows[] = [
                'id'             => null,
                'name'           => null,
                'account'        => null,
                'stage'          => null,
                'amount'         => null,
                'currency'       => null,
                'close_date'     => null,
                'created_at'     => null,
                'last_activity'  => null,
                'rep_id'         => $repId,
                'close_pipeline' => $total,
                'placeholder'    => true,
            ];
        }
    }

    return $rows;
}

/** Opportunities created inside a date range. */
function qNewOpportunities(string $from, string $to, ?string $rep): array {
    $sql = "SELECT o.id, o.name, o.stage, o.amount, o.amount_currency AS currency,
                o.close_date,o.created_at,
                o.modified_at AS last_activity,
                    o.assigned_user_id AS rep_id
            FROM opportunity o
            WHERE o.deleted = 0 AND DATE(o.created_at) BETWEEN :f AND :t AND o.c_parent = 'trivium' AND o.stage NOT IN ('Closed Won', 'Closed Lost')";
    $p = ['f' => $from, 't' => $to];
    if ($rep) { $sql .= " AND o.assigned_user_id = :rep"; $p['rep'] = $rep; }
    return run($sql, $p);
}



/** Next open task per lead/opportunity (for the "Next action" column). */
function qNextActions(?string $rep): array {
    $sql = "SELECT t.parent_type, t.parent_id, MIN(t.date_end) AS next_due
            FROM task t
            WHERE t.deleted = 0 AND t.status NOT IN ('Completed','Canceled') AND t.parent_id IS NOT NULL";
    $p = [];
    if ($rep) { $sql .= " AND t.assigned_user_id = :rep"; $p['rep'] = $rep; }
    return run($sql . " GROUP BY t.parent_type, t.parent_id", $p);
}


/** Team overview: one aggregated row per salesperson for the date range. */
function qTeamSummary(string $from, string $to, string $today, ?string $stage): array {
    $sql = "SELECT u.id AS rep_id, CONCAT_WS(' ', u.first_name, u.last_name) AS name,
        (SELECT COUNT(*) FROM lead l WHERE l.deleted=0 AND l.assigned_user_id=u.id
            AND l.status IN ('New','Assigned','In Process') AND l.c_parent = 'trivium')
            AS total_leads ,
        (SELECT COUNT(*) FROM lead l WHERE l.deleted=0 AND l.assigned_user_id=u.id
            AND l.status IN ('New','Assigned','In Process')
            AND DATE(l.created_at) BETWEEN :f1 AND :t1 AND l.c_parent = 'trivium')
            AS new_leads ,
        (SELECT COUNT(*) FROM opportunity o WHERE o.deleted=0 AND o.assigned_user_id=u.id
            AND DATE(o.created_at) 
            AND o.stage NOT IN ('Closed Won','Closed Lost') AND (:s1 = '' OR o.stage = :s1b) AND o.c_parent = 'trivium') AS open_opps,
        (SELECT COUNT(*) FROM opportunity o WHERE o.deleted=0 AND o.assigned_user_id=u.id
            AND o.stage NOT IN ('Closed Won','Closed Lost')
            AND DATE(o.created_at) BETWEEN :f2 AND :t2 AND (:s2 = '' OR o.stage = :s2b) AND o.c_parent = 'trivium')  AS new_opps,
        (SELECT COALESCE(SUM(o.amount),0) FROM opportunity o WHERE o.deleted=0 AND o.assigned_user_id=u.id
            AND DATE(o.created_at) BETWEEN :f2 AND :t2
            AND o.stage NOT IN ('Closed Won','Closed Lost') AND (:s3 = '' OR o.stage = :s3b) AND o.c_parent = 'trivium') AS pipeline,
        (
            SELECT COALESCE(SUM(l.c_lead_value), 0)
            FROM lead l
            WHERE l.deleted = 0
              AND l.assigned_user_id = u.id
              AND DATE(l.created_at) BETWEEN :f2 AND :t2
              AND l.c_parent = 'trivium'
        ) AS total_lead_value,
        (SELECT COALESCE(SUM(o.amount), 0)
             FROM opportunity o
             WHERE o.deleted = 0
               AND o.assigned_user_id = u.id
               AND DATE(o.created_at) BETWEEN :f2 AND :t2
               AND o.stage IN ('Closed Won', 'Closed Lost')
               AND o.c_parent = 'trivium'
            ) AS close_pipeline,
        (SELECT COUNT(*) FROM task t LEFT JOIN lead l ON l.id = t.parent_id   WHERE t.deleted=0 AND t.assigned_user_id=u.id
            AND DATE(t.date_end) BETWEEN :f3 AND :t3 AND l.c_parent = 'trivium')
            AS tasks_due,
        (SELECT COUNT(*) FROM task t LEFT JOIN lead l ON l.id = t.parent_id WHERE t.deleted=0 AND t.assigned_user_id=u.id AND t.status='Completed'
            AND DATE(t.date_completed) BETWEEN :f4 AND :t4 AND l.c_parent = 'trivium')
            AS tasks_completed,
        (SELECT COUNT(*) FROM task t LEFT JOIN lead l ON l.id = t.parent_id WHERE t.deleted=0 AND t.assigned_user_id=u.id
            AND t.status NOT IN ('Completed','Canceled') AND DATE(t.date_end) < :today AND l.c_parent = 'trivium')   AS overdue
        FROM user u
        WHERE u.deleted=0 AND u.is_active=1 AND u.c_parent='trivium' AND u.type IN ('regular','admin')
        ORDER BY u.first_name";
    $s = $stage ?: '';
    return run($sql, [
        'f1'=>$from,'t1'=>$to,'f2'=>$from,'t2'=>$to,'f3'=>$from,'t3'=>$to,'f4'=>$from,'t4'=>$to,
        's1'=>$s,'s1b'=>$s,'s2'=>$s,'s2b'=>$s,'s3'=>$s,'s3b'=>$s,'today'=>$today,
    ]);
}

function qRecord(string $type, string $id, ?string $from = null, ?string $to = null): ?array {
    switch ($type) {
        case 'Lead':
            $sql = "SELECT l.id, CONCAT_WS(' ', l.first_name, l.last_name) AS name, l.status, l.source,
                           l.created_at, l.modified_at AS last_activity,
                           CONCAT_WS(' ', u.first_name, u.last_name) AS rep
                    FROM lead l LEFT JOIN user u ON u.id = l.assigned_user_id
                    WHERE l.id = :id AND l.deleted = 0 AND l.c_parent = 'trivium' AND u.c_parent='trivium'";
            $dateCol = 'l.created_at';
            break;
        case 'Opportunity':
            $sql = "SELECT o.id, o.name, a.name AS account, o.stage, o.amount, o.amount_currency AS currency,
                           o.close_date, o.created_at, o.modified_at AS last_activity,
                           CONCAT_WS(' ', u.first_name, u.last_name) AS rep
                    FROM opportunity o
                    LEFT JOIN account a ON a.id = o.account_id
                    LEFT JOIN user u ON u.id = o.assigned_user_id
                    WHERE o.id = :id AND o.deleted = 0 AND o.c_parent = 'trivium' AND u.c_parent='trivium'";
            $dateCol = 'o.created_at';
            break;
        case 'Task':
            $sql = "SELECT t.id, t.name AS subject, t.status, t.date_end AS due, t.date_completed AS done,
                           t.parent_type, t.parent_id, CONCAT_WS(' ', u.first_name, u.last_name) AS rep,CONCAT_WS(' ', l.first_name, l.last_name) as lead_name, l.c_designation as designation,l.c_school_category as school_category,l.c_company_name as company_name,l.c_csource as source
                    FROM task t LEFT JOIN lead l ON l.id = t.parent_id LEFT JOIN user u ON u.id = t.assigned_user_id
                    WHERE t.id = :id AND t.deleted = 0 AND l.c_parent = 'trivium' AND u.c_parent='trivium'";
            $dateCol = 't.date_end';
            break;
        default:
            throw new InvalidArgumentException('Unknown record type');
    }

    $params = ['id' => $id];
    if($type != 'Task'){
        if ($from !== null && $from !== '') {
            $sql .= " AND $dateCol >= :from";
            $params['from'] = $from;
        }
        if ($to !== null && $to !== '') {
            // include the whole "to" day
            $sql .= " AND $dateCol < DATE_ADD(:to, INTERVAL 1 DAY)";
            $params['to'] = $to;
        }
    }
    

    $rows = run($sql, $params);
    return $rows[0] ?? null;
}
/* ---------- router ---------- */
try {
    // TODO: add authentication/authorization here before exposing this endpoint.
    $a     = $_GET['action'] ?? '';
    $rep   = ($_GET['rep'] ?? '') !== '' ? $_GET['rep'] : null;
    $stage = ($_GET['stage'] ?? '') !== '' && $_GET['stage'] !== 'all' ? $_GET['stage'] : null;
    $from  = fn() => validDate($_GET['from'] ?? null);
    $to    = fn() => validDate($_GET['to'] ?? null);
    $today = fn() => validDate($_GET['today'] ?? date('Y-m-d'));

    $out = match ($a) {
        'reps'          => qReps(),
        'openLeads'     => qOpenLeads($rep, $from(), $to()),
        'newLeads'      => qNewLeads($from(), $to(), $rep),
        'openOpps'      => qOpenOpportunities($rep, $stage, $from(), $to()),
        'newOpps'       => qNewOpportunities($from(), $to(), $rep),
        'tasksDue'      => qTasksDue($from(), $to(), $rep),
        'tasksDone'     => qTasksCompleted($from(), $to(), $rep),
        'overdueTasks'  => qOverdueTasks($today(), $rep),
        'upcomingTasks' => qUpcomingTasks($from(), $to(), $rep),
        'nextActions'   => qNextActions($rep),
        'teamSummary'   => qTeamSummary($from(), $to(), $today(), $stage),
        // 'record'        => qRecord($_GET['type'] ?? '', $_GET['id'] ?? ''),
        'record' => qRecord($_GET['type'] ?? '', $_GET['id'] ?? '', $from(), $to()),
        default         => throw new InvalidArgumentException('Unknown action'),
    };
    echo json_encode($out);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Sales dashboard API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}