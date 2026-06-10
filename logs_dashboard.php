<?php
// logs_dashboard.php - Stufe 4: Monitoring Dashboard
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

require_once __DIR__ . '/session_handler.php';
check_auth();

header('Content-Type: text/html; charset=utf-8');

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$filter_level = $_GET['level'] ?? '';
$filter_event = $_GET['event'] ?? '';
$filter_minutes = (int)($_GET['minutes'] ?? 60);

$query = "SELECT * FROM system_logs WHERE 1=1";
$params = [];

if ($filter_level) {
    $query .= " AND level = ?";
    $params[] = $filter_level;
}

if ($filter_event) {
    $query .= " AND event_type = ?";
    $params[] = $filter_event;
}

if ($filter_minutes > 0) {
    $query .= " AND timestamp > datetime('now', '-' || ? || ' minutes')";
    $params[] = $filter_minutes;
}

$query .= " ORDER BY timestamp DESC LIMIT 100";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique event types for filter dropdown
$eventStmt = $db->query("SELECT DISTINCT event_type FROM system_logs ORDER BY event_type");
$eventTypes = $eventStmt->fetchAll(PDO::FETCH_COLUMN);

// Get log statistics
$statsStmt = $db->query("
    SELECT level, COUNT(*) as count FROM system_logs
    WHERE timestamp > datetime('now', '-24 hours')
    GROUP BY level
");
$stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: monospace; background: #0b0b0c; color: #16c784; padding: 20px; }
        h1 { margin-bottom: 20px; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat { background: #1a1a1b; padding: 10px 15px; border: 1px solid #16c784; border-radius: 4px; }
        .stat-label { font-size: 12px; opacity: 0.7; }
        .stat-value { font-size: 18px; font-weight: bold; }
        .filters { background: #1a1a1b; padding: 15px; margin-bottom: 20px; border: 1px solid #16c784; border-radius: 4px; display: flex; gap: 10px; flex-wrap: wrap; }
        select, input { background: #0b0b0c; color: #16c784; border: 1px solid #16c784; padding: 6px 10px; border-radius: 3px; font-family: monospace; }
        button { background: #16c784; color: #000; border: none; padding: 8px 16px; border-radius: 3px; cursor: pointer; font-weight: bold; }
        button:hover { opacity: 0.8; }
        .logs { background: #1a1a1b; border: 1px solid #16c784; border-radius: 4px; overflow: auto; }
        .log-entry { padding: 12px 15px; border-bottom: 1px solid #16c784; display: grid; grid-template-columns: 150px 80px 150px 1fr; gap: 10px; align-items: start; font-size: 12px; }
        .log-entry:last-child { border-bottom: none; }
        .log-timestamp { opacity: 0.7; }
        .log-level { font-weight: bold; }
        .log-level.ERROR { color: #ff3131; }
        .log-level.WARN { color: #ff9529; }
        .log-level.INFO { color: #16c784; }
        .log-message { white-space: pre-wrap; word-break: break-word; max-height: 60px; overflow: hidden; text-overflow: ellipsis; }
        .log-context { font-size: 11px; opacity: 0.6; }
        .empty { padding: 20px; text-align: center; opacity: 0.7; }
    </style>
</head>
<body>
    <h1>System Logs Dashboard</h1>

    <div class="stats">
        <?php foreach ($stats as $stat): ?>
            <div class="stat">
                <div class="stat-label"><?= htmlspecialchars($stat['level']) ?> (24h)</div>
                <div class="stat-value"><?= (int)$stat['count'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="filters">
        <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%; align-items: center;">
            <label style="display: flex; gap: 5px; align-items: center;">
                Level:
                <select name="level">
                    <option value="">All</option>
                    <option value="INFO" <?= $filter_level === 'INFO' ? 'selected' : '' ?>>INFO</option>
                    <option value="WARN" <?= $filter_level === 'WARN' ? 'selected' : '' ?>>WARN</option>
                    <option value="ERROR" <?= $filter_level === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                </select>
            </label>
            <label style="display: flex; gap: 5px; align-items: center;">
                Event:
                <select name="event">
                    <option value="">All</option>
                    <?php foreach ($eventTypes as $event): ?>
                        <option value="<?= htmlspecialchars($event) ?>" <?= $filter_event === $event ? 'selected' : '' ?>>
                            <?= htmlspecialchars($event) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="display: flex; gap: 5px; align-items: center;">
                Last:
                <input type="number" name="minutes" value="<?= (int)$filter_minutes ?>" min="1" max="1440" style="width: 60px;">
                min
            </label>
            <button type="submit">Filter</button>
        </form>
    </div>

    <div class="logs">
        <?php if (empty($logs)): ?>
            <div class="empty">No logs found matching the criteria.</div>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <div class="log-entry">
                    <div class="log-timestamp"><?= htmlspecialchars($log['timestamp']) ?></div>
                    <div class="log-level <?= htmlspecialchars($log['level']) ?>"><?= htmlspecialchars($log['level']) ?></div>
                    <div><?= htmlspecialchars($log['event_type']) ?></div>
                    <div>
                        <div class="log-message"><?= htmlspecialchars($log['message']) ?></div>
                        <?php if ($log['duration_ms']): ?>
                            <div class="log-context">⏱ <?= (int)$log['duration_ms'] ?>ms</div>
                        <?php endif; ?>
                        <?php if ($log['context_json']): ?>
                            <div class="log-context"><?= htmlspecialchars($log['context_json']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
