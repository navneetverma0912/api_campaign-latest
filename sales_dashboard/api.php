<?php
/**
 * api.php: all SQL for the Weekly Sales Review lives here.
 * Each query is its own PHP function; the router at the bottom maps ?action=... to one of them.
 * Targets the EspoCRM MySQL schema (tables: user, lead, opportunity, task, account).
 */
header('Content-Type: application/json; charset=utf-8');

/* ---------- connection ---------- */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=espocrm;charset=utf8mb4',
            'DB_USER',      // TODO: move to env/config
            'DB_PASSWORD',  // TODO: move to env/config
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
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
    COALESCE(NULLIF(CONCAT_WS(' ', pl.first_name, pl.last_name), ''), po.name) AS parent_name";
const TASK_FROM = "FROM task t
    LEFT JOIN lead pl ON t.parent_type = 'Lead' AND pl.id = t.parent_id AND pl.deleted = 0
    LEFT JOIN opportunity po ON t.parent_type = 'Opportunity' AND po.id = t.parent_id AND po.deleted = 0";

function taskRows(string $where, array $p, ?string $rep, string $order, string $extra = ''): array {
    $sql = "SELECT " . TASK_COLS . $extra . " " . TASK_FROM . " WHERE t.deleted = 0 AND $where";
    if ($rep) { $sql .= " AND t.assigned_user_id = :rep"; $p['rep'] = $rep; }
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
                WHERE deleted = 0 AND is_active = 1 AND type IN ('regular','admin')
                ORDER BY first_name");
}

/** Open leads (New / Assigned / In Process), optionally for one rep. */
function qOpenLeads(?string $rep): array {
    $sql = "SELECT l.id, CONCAT_WS(' ', l.first_name, l.last_name) AS name, l.status, l.source,
                   l.created_at, l.modified_at AS last_activity, l.assigned_user_id AS rep_id
            FROM lead l
            WHERE l.deleted = 0 AND l.status IN ('New','Assigned','In Process')";
    $p = [];
    if ($rep) { $sql .= " AND l.assigned_user_id = :rep"; $p['rep'] = $rep; }
    return run($sql . " ORDER BY l.created_at DESC", $p);
}

/** Leads created inside a date range. */
function qNewLeads(string $from, string $to, ?string $rep): array {
    $sql = "SELECT l.id, CONCAT_WS(' ', l.first_name, l.last_name) AS name, l.status, l.source,
                   l.created_at, l.assigned_user_id AS rep_id
            FROM lead l
            WHERE l.deleted = 0 AND DATE(l.created_at) BETWEEN :f AND :t";
    $p = ['f' => $from, 't' => $to];
    if ($rep) { $sql .= " AND l.assigned_user_id = :rep"; $p['rep'] = $rep; }
    return run($sql . " ORDER BY l.created_at DESC", $p);
}

/** Open opportunities (not Closed Won / Closed Lost), optional rep and stage. */
function qOpenOpportunities(?string $rep, ?string $stage): array {
    $sql = "SELECT o.id, o.name, a.name AS account, o.stage, o.amount, o.amount_currency AS currency,
                   o.close_date, o.created_at, o.modified_at AS last_activity, o.assigned_user_id AS rep_id
            FROM opportunity o
            LEFT JOIN account a ON a.id = o.account_id AND a.deleted = 0
            WHERE o.deleted = 0 AND o.stage NOT IN ('Closed Won','Closed Lost')";
    $p = [];
    if ($rep)   { $sql .= " AND o.assigned_user_id = :rep"; $p['rep'] = $rep; }
    if ($stage) { $sql .= " AND o.stage = :stage";          $p['stage'] = $stage; }
    return run($sql . " ORDER BY o.close_date ASC", $p);
}

/** Opportunities created inside a date range. */
function qNewOpportunities(string $from, string $to, ?string $rep): array {
    $sql = "SELECT o.id, o.name, o.stage, o.amount, o.amount_currency AS currency,
                   o.created_at, o.assigned_user_id AS rep_id
            FROM opportunity o
            WHERE o.deleted = 0 AND DATE(o.created_at) BETWEEN :f AND :t";
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
    $sql = "(SELECT COALESCE(MAX(DATEDIFF(:today_a, DATE(t.date_end))),0) FROM task t
           WHERE t.deleted=0 AND t.assigned_user_id=u.id AND t.status NOT IN ('Completed','Canceled')
             AND DATE(t.date_end) < :today_a2)                                   AS oldest_overdue_days,
        (SELECT COUNT(*) FROM opportunity o WHERE o.deleted=0 AND o.assigned_user_id=u.id
           AND o.stage NOT IN ('Closed Won','Closed Lost') AND (:s4 = '' OR o.stage = :s4b)
           AND o.close_date < :today_b)                                          AS past_close,
        (SELECT COUNT(*) FROM opportunity o WHERE o.deleted=0 AND o.assigned_user_id=u.id
           AND o.stage NOT IN ('Closed Won','Closed Lost') AND (:s5 = '' OR o.stage = :s5b)
           AND o.modified_at < DATE_SUB(:today_c, INTERVAL 14 DAY))              AS stale_opps,
        (SELECT COUNT(*) FROM opportunity o WHERE o.deleted=0 AND o.assigned_user_id=u.id
           AND o.stage NOT IN ('Closed Won','Closed Lost') AND (:s6 = '' OR o.stage = :s6b)
           AND o.close_date BETWEEN :today_d AND DATE_ADD(:today_e, INTERVAL 7 DAY)) AS closing_soon,
        (SELECT COUNT(*) FROM task t WHERE t.deleted=0 AND t.assigned_user_id=u.id
           AND t.status NOT IN ('Completed','Canceled')
           AND DATE(t.date_end) BETWEEN DATE_ADD(:to5, INTERVAL 1 DAY) AND DATE_ADD(:to6, INTERVAL 7 DAY)) AS next_week_tasks";
    $s = $stage ?: '';
    // return run($sql, [
    //     'f1'=>$from,'t1'=>$to,'f2'=>$from,'t2'=>$to,'f3'=>$from,'t3'=>$to,'f4'=>$from,'t4'=>$to,
    //     's1'=>$s,'s1b'=>$s,'s2'=>$s,'s2b'=>$s,'s3'=>$s,'s3b'=>$s,'today'=>$today,
    // ]);
    $extra = ['today_a'=>$today,'today_a2'=>$today,'today_b'=>$today,'today_c'=>$today,'today_d'=>$today,'today_e'=>$today,
          'to5'=>$to,'to6'=>$to,'s4'=>$s,'s4b'=>$s,'s5'=>$s,'s5b'=>$s,'s6'=>$s,'s6b'=>$s];
    return run($sql, array_merge([
        'f1'=>$from,'t1'=>$to,'f2'=>$from,'t2'=>$to,'f3'=>$from,'t3'=>$to,'f4'=>$from,'t4'=>$to,
        's1'=>$s,'s1b'=>$s,'s2'=>$s,'s2b'=>$s,'s3'=>$s,'s3b'=>$s,'today'=>$today,
    ], $extra));
}

/** Single record for the side panel. $type is one of Lead | Opportunity | Task. */
function qRecord(string $type, string $id): ?array {
    switch ($type) {
        case 'Lead':
            $sql = "SELECT l.id, CONCAT_WS(' ', l.first_name, l.last_name) AS name, l.status, l.source,
                           l.created_at, l.modified_at AS last_activity,
                           CONCAT_WS(' ', u.first_name, u.last_name) AS rep
                    FROM lead l LEFT JOIN user u ON u.id = l.assigned_user_id
                    WHERE l.id = :id AND l.deleted = 0";
            break;
        case 'Opportunity':
            $sql = "SELECT o.id, o.name, a.name AS account, o.stage, o.amount, o.amount_currency AS currency,
                           o.close_date, o.created_at, o.modified_at AS last_activity,
                           CONCAT_WS(' ', u.first_name, u.last_name) AS rep
                    FROM opportunity o
                    LEFT JOIN account a ON a.id = o.account_id
                    LEFT JOIN user u ON u.id = o.assigned_user_id
                    WHERE o.id = :id AND o.deleted = 0";
            break;
        case 'Task':
            $sql = "SELECT t.id, t.name AS subject, t.status, t.date_end AS due, t.date_completed AS done,
                           t.parent_type, t.parent_id, CONCAT_WS(' ', u.first_name, u.last_name) AS rep
                    FROM task t LEFT JOIN user u ON u.id = t.assigned_user_id
                    WHERE t.id = :id AND t.deleted = 0";
            break;
        default:
            throw new InvalidArgumentException('Unknown record type');
    }
    $rows = run($sql, ['id' => $id]);
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
        'openLeads'     => qOpenLeads($rep),
        'newLeads'      => qNewLeads($from(), $to(), $rep),
        'openOpps'      => qOpenOpportunities($rep, $stage),
        'newOpps'       => qNewOpportunities($from(), $to(), $rep),
        'tasksDue'      => qTasksDue($from(), $to(), $rep),
        'tasksDone'     => qTasksCompleted($from(), $to(), $rep),
        'overdueTasks'  => qOverdueTasks($today(), $rep),
        'upcomingTasks' => qUpcomingTasks($from(), $to(), $rep),
        'nextActions'   => qNextActions($rep),
        'teamSummary'   => qTeamSummary($from(), $to(), $today(), $stage),
        'record'        => qRecord($_GET['type'] ?? '', $_GET['id'] ?? ''),
        default         => throw new InvalidArgumentException('Unknown action'),
    };
    echo json_encode($out);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['error' => 'Server error']);
}