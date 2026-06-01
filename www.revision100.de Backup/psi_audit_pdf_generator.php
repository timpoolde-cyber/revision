<?php
require_once __DIR__ . '/session_handler.php';
check_auth();

$id = $_GET['id'] ?? null;
if (!$id) {
    die('Projekt ID erforderlich');
}

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get project + customer
$stmt = $db->prepare("
    SELECT p.id, p.customer_name, p.target_url, c.email, c.customer_name as cname
    FROM projects p
    LEFT JOIN customers c ON p.customer_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die('Projekt nicht gefunden');
}

// Get latest mobile + desktop PSI with raw_response
$stmt = $db->prepare("
    SELECT strategy, raw_response, fetch_timestamp
    FROM psi_results
    WHERE project_id = ? AND raw_response IS NOT NULL AND LENGTH(COALESCE(raw_response, '')) > 100
    ORDER BY strategy DESC, fetch_timestamp DESC
");
$stmt->execute([$id]);
$psiRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

$psiData = [];
foreach ($psiRecords as $rec) {
    $strategy = $rec['strategy'];
    if (!isset($psiData[$strategy])) {
        $decoded = json_decode($rec['raw_response'], true);
        if ($decoded) {
            $psiData[$strategy] = [
                'raw' => $decoded,
                'timestamp' => $rec['fetch_timestamp']
            ];
        }
    }
}

function getAuditStatus($audit) {
    $score = $audit['score'] ?? null;
    $scoreMode = $audit['scoreDisplayMode'] ?? 'numeric';

    if ($scoreMode === 'manual') return 'manual';
    if ($scoreMode === 'notApplicable') return 'notApplicable';
    if ($scoreMode === 'error') return 'error';

    if ($score === null) return 'unknown';
    if ($score >= 0.9) return 'passed';
    if ($score >= 0.5) return 'warning';
    return 'failed';
}

function getStatusLabel($status) {
    $labels = [
        'passed' => '✅ Bestanden',
        'warning' => '⚠️ Verbesserungen',
        'failed' => '❌ Fehlgeschlagen',
        'notApplicable' => '◯ Nicht anwendbar',
        'error' => '⚠️ Fehler',
        'manual' => '🔍 Manuell'
    ];
    return $labels[$status] ?? $status;
}

function getStatusColor($status) {
    $colors = [
        'passed' => '#0d8659',
        'warning' => '#FF9529',
        'failed' => '#FF3131',
        'notApplicable' => '#999',
        'error' => '#FF9529',
        'manual' => '#999'
    ];
    return $colors[$status] ?? '#666';
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PSI Audit Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.5; }

        @page { size: A4; margin: 20mm; }
        @media print { body { margin: 0; padding: 0; } }

        .container { width: 210mm; margin: 0 auto; padding: 20mm; background: white; }

        .header { margin-bottom: 30mm; border-bottom: 3px solid #0066cc; padding-bottom: 10mm; }
        .header h1 { font-size: 28px; margin-bottom: 5mm; color: #0066cc; }
        .header-info { font-size: 11px; color: #666; }
        .header-info p { margin: 2mm 0; }

        .strategy-section { page-break-inside: avoid; margin-bottom: 20mm; }
        .strategy-title { font-size: 18px; font-weight: bold; color: #0066cc; margin: 10mm 0 5mm 0; border-left: 4px solid #0066cc; padding-left: 5mm; }

        .status-group { margin-bottom: 10mm; }
        .status-group-title {
            font-size: 13px;
            font-weight: bold;
            padding: 5mm;
            background: #f5f5f5;
            border-left: 4px solid #999;
            margin-bottom: 5mm;
        }
        .status-group-title.passed { border-left-color: #0d8659; background: #f0f9f6; }
        .status-group-title.warning { border-left-color: #FF9529; background: #fff9f5; }
        .status-group-title.failed { border-left-color: #FF3131; background: #fff5f5; }

        .audit-item {
            margin-bottom: 8mm;
            padding: 8mm;
            background: #fafafa;
            border-left: 3px solid #ddd;
            page-break-inside: avoid;
        }
        .audit-item.passed { border-left-color: #0d8659; background: #f9fdf8; }
        .audit-item.warning { border-left-color: #FF9529; background: #fffaf5; }
        .audit-item.failed { border-left-color: #FF3131; background: #fff9f8; }

        .audit-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 3mm; }
        .audit-title { font-size: 12px; font-weight: bold; flex: 1; }
        .audit-badge {
            font-size: 9px;
            padding: 2mm 4mm;
            border-radius: 2mm;
            background: #e0e0e0;
            color: #333;
            white-space: nowrap;
            margin-left: 5mm;
        }
        .audit-description { font-size: 10px; color: #666; margin-bottom: 2mm; line-height: 1.4; }

        .timestamp { font-size: 10px; color: #999; margin-top: 2mm; }

        .footer {
            margin-top: 30mm;
            padding-top: 10mm;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #999;
        }

        .no-data { padding: 20mm; background: #f9f9f9; border: 1px solid #ddd; text-align: center; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔍 PSI Audit Report</h1>
        <div class="header-info">
            <p><strong><?= htmlspecialchars($project['cname'] ?? $project['customer_name']) ?></strong></p>
            <p><strong>URL:</strong> <?= htmlspecialchars($project['target_url']) ?></p>
            <p><strong>Projekt ID:</strong> <?= $id ?></p>
            <p><strong>Bericht generiert:</strong> <?= date('d.m.Y H:i') ?></p>
        </div>
    </div>

    <?php if (empty($psiData)): ?>
        <div class="no-data">
            Keine PSI-Messdaten verfügbar
        </div>
    <?php else: ?>
        <?php foreach (['mobile', 'desktop'] as $strategy): ?>
            <?php if (!isset($psiData[$strategy])): continue; endif; ?>

            <div class="strategy-section">
                <div class="strategy-title"><?= ucfirst($strategy) ?> Audit</div>
                <div class="timestamp">Messung: <?= $psiData[$strategy]['timestamp'] ?></div>

                <?php
                $data = $psiData[$strategy]['raw'];
                if (!$data || !isset($data['lighthouseResult']['audits'])) {
                    echo '<div class="no-data">Keine Audit-Daten verfügbar</div>';
                    continue;
                }

                $audits = $data['lighthouseResult']['audits'];

                // Group audits by status
                $grouped = ['passed' => [], 'warning' => [], 'failed' => [], 'notApplicable' => [], 'error' => [], 'manual' => []];
                foreach ($audits as $auditKey => $audit) {
                    $status = getAuditStatus($audit);
                    $grouped[$status][] = ['key' => $auditKey, 'data' => $audit];
                }
                ?>

                <?php foreach (['passed', 'warning', 'failed', 'notApplicable', 'error', 'manual'] as $statusType): ?>
                    <?php if (empty($grouped[$statusType])) continue; ?>

                    <div class="status-group">
                        <div class="status-group-title <?= $statusType ?>">
                            <?= getStatusLabel($statusType) ?> (<?= count($grouped[$statusType]) ?>)
                        </div>

                        <?php foreach ($grouped[$statusType] as $item): ?>
                            <?php
                            $audit = $item['data'];
                            $status = getAuditStatus($audit);
                            $score = $audit['score'] ?? null;
                            $scoreText = ($score !== null && is_numeric($score)) ? round($score * 100) . '%' : '—';
                            ?>

                            <div class="audit-item <?= $status ?>">
                                <div class="audit-header">
                                    <div class="audit-title"><?= htmlspecialchars($audit['title'] ?? $item['key']) ?></div>
                                    <div class="audit-badge"><?= $scoreText ?></div>
                                </div>

                                <?php if (!empty($audit['description'])): ?>
                                    <div class="audit-description">
                                        <?= htmlspecialchars(substr($audit['description'], 0, 200)) ?>
                                        <?php if (strlen($audit['description']) > 200): ?>...<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        <p>REVISION100™ — Automatisierter PSI Audit Report</p>
        <p>Dieser Bericht wurde automatisch generiert und enthält Daten von Google PageSpeed Insights.</p>
    </div>
</div>

<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 1000);
    });
</script>
</body>
</html>
