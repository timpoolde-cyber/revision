<?php
// psi_history_pdf_generator.php
require_once __DIR__ . '/session_handler.php';
check_auth();

$dbPath = __DIR__ . '/data/rockets.db';
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Datenbankfehler.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Projekt-ID fehlt.");
}

$stmt = $db->prepare("SELECT p.*, c.email FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("Projekt nicht gefunden.");
}

// Load all PSI measurements ordered by date
$stmt = $db->prepare("SELECT * FROM psi_results WHERE project_id = ? ORDER BY fetch_timestamp ASC");
$stmt->execute([$id]);
$psi_all = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by strategy and sort
$psi_mobile_history = [];
$psi_desktop_history = [];

foreach ($psi_all as $record) {
    if ($record['strategy'] === 'mobile') {
        $psi_mobile_history[] = $record;
    } elseif ($record['strategy'] === 'desktop') {
        $psi_desktop_history[] = $record;
    }
}

function getColorHex($score) {
    if (!$score) return '#eee';
    if ($score >= 90) return '#0d8659';
    if ($score >= 75) return '#FF9529';
    if ($score >= 50) return '#FF9529';
    return '#FF3131';
}

function getTrend($current, $previous) {
    if (!$previous || !$current) return '–';
    $diff = $current - $previous;
    if ($diff > 0) return '↑ +' . $diff;
    if ($diff < 0) return '↓ ' . $diff;
    return '→ ±0';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>PSI Historie - <?= htmlspecialchars($project['customer_name']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'JetBrains Mono', 'IBM Plex Mono', monospace;
      background: #e0e0e0;
      color: #000;
    }
    @media print {
      body { background: #fff; }
      .paper { margin: 0; box-shadow: none; }
      .no-print { display: none; }
      .page-break { page-break-after: always; }
    }
    .paper {
      width: 210mm;
      min-height: 297mm;
      margin: 10mm auto;
      padding: 20mm;
      background: #fff;
      box-sizing: border-box;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #000;
      padding-bottom: 10mm;
      margin-bottom: 10mm;
    }
    .logo {
      font-family: 'Impact', sans-serif;
      font-size: 32px;
      letter-spacing: -1px;
      text-transform: uppercase;
      font-weight: 900;
    }
    .header-right { text-align: right; font-size: 11px; }
    .header-right div { margin-bottom: 3px; }
    h1 { font-size: 20px; font-weight: bold; margin: 10mm 0 5mm 0; text-transform: uppercase; }
    h2 { font-size: 14px; font-weight: bold; margin: 8mm 0 4mm 0; border-bottom: 1px solid #000; padding-bottom: 2mm; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 8mm;
      font-size: 10px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 3mm;
      text-align: center;
    }
    th {
      background: #f5f5f5;
      font-weight: bold;
      text-transform: uppercase;
      font-size: 9px;
    }
    td { padding: 4mm 3mm; }
    .date { text-align: left; font-size: 9px; }
    .score-badge {
      display: inline-block;
      width: 20px;
      height: 20px;
      border-radius: 2px;
      color: #fff;
      font-weight: bold;
      font-size: 9px;
      line-height: 20px;
      text-align: center;
    }
    .trend-up { color: #0d8659; font-weight: bold; }
    .trend-down { color: #FF3131; font-weight: bold; }
    .trend-neutral { color: #666; }
    .empty { padding: 5mm; color: #999; font-style: italic; }
    .footer { margin-top: 10mm; padding-top: 5mm; border-top: 1px solid #ccc; font-size: 9px; color: #666; }
    .no-print { background: #f0f0f0; padding: 10mm; margin-bottom: 10mm; border: 1px solid #ccc; text-align: center; }
    .no-print button { padding: 5mm 10mm; background: #000; color: #fff; border: none; cursor: pointer; font-weight: bold; }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">🖨️ Drucken / Als PDF speichern</button>
</div>

<div class="paper">
  <div class="header">
    <div class="logo">REVISION100™</div>
    <div class="header-right">
      <div><strong><?= htmlspecialchars($project['customer_name']) ?></strong></div>
      <div><?= htmlspecialchars($project['target_url']) ?></div>
      <div style="margin-top: 3mm; font-size: 9px;">Historie der PSI-Messungen</div>
    </div>
  </div>

  <h1>📊 Messung Progress</h1>

  <?php if (!empty($psi_mobile_history)): ?>
  <h2>📱 Mobile History</h2>
  <table>
    <thead>
      <tr>
        <th class="date">Datum</th>
        <th>Performance</th>
        <th>Trend</th>
        <th>Accessibility</th>
        <th>Best Practice</th>
        <th>SEO</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $prev_perf = null;
      foreach ($psi_mobile_history as $idx => $record):
        $trend = getTrend($record['performance_score'], $prev_perf);
        $prev_perf = $record['performance_score'];
        $trend_class = '';
        if (strpos($trend, '↑') !== false) $trend_class = 'trend-up';
        elseif (strpos($trend, '↓') !== false) $trend_class = 'trend-down';
        else $trend_class = 'trend-neutral';
      ?>
      <tr>
        <td class="date"><?= htmlspecialchars(substr($record['fetch_timestamp'], 0, 16)) ?></td>
        <td>
          <span class="score-badge" style="background: <?= getColorHex($record['performance_score']) ?>;">
            <?= $record['performance_score'] ?>
          </span>
        </td>
        <td class="<?= $trend_class ?>"><?= $trend ?></td>
        <td><?= $record['accessibility_score'] ?></td>
        <td><?= $record['best_practices_score'] ?></td>
        <td><?= $record['seo_score'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty">📱 Mobile: Keine Messungen verfügbar</div>
  <?php endif; ?>

  <?php if (!empty($psi_desktop_history)): ?>
  <h2>🖥️ Desktop History</h2>
  <table>
    <thead>
      <tr>
        <th class="date">Datum</th>
        <th>Performance</th>
        <th>Trend</th>
        <th>Accessibility</th>
        <th>Best Practice</th>
        <th>SEO</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $prev_perf = null;
      foreach ($psi_desktop_history as $idx => $record):
        $trend = getTrend($record['performance_score'], $prev_perf);
        $prev_perf = $record['performance_score'];
        $trend_class = '';
        if (strpos($trend, '↑') !== false) $trend_class = 'trend-up';
        elseif (strpos($trend, '↓') !== false) $trend_class = 'trend-down';
        else $trend_class = 'trend-neutral';
      ?>
      <tr>
        <td class="date"><?= htmlspecialchars(substr($record['fetch_timestamp'], 0, 16)) ?></td>
        <td>
          <span class="score-badge" style="background: <?= getColorHex($record['performance_score']) ?>;">
            <?= $record['performance_score'] ?>
          </span>
        </td>
        <td class="<?= $trend_class ?>"><?= $trend ?></td>
        <td><?= $record['accessibility_score'] ?></td>
        <td><?= $record['best_practices_score'] ?></td>
        <td><?= $record['seo_score'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty">🖥️ Desktop: Keine Messungen verfügbar</div>
  <?php endif; ?>

  <div class="footer">
    <strong>Projekt ID:</strong> <?= htmlspecialchars($id) ?> |
    <strong>Messungen:</strong> <?= count($psi_mobile_history) + count($psi_desktop_history) ?> |
    <strong>Zeitraum:</strong> <?php
      if (!empty($psi_mobile_history)) {
        echo htmlspecialchars(substr($psi_mobile_history[0]['fetch_timestamp'], 0, 10)) . ' bis ' . htmlspecialchars(substr($psi_mobile_history[count($psi_mobile_history)-1]['fetch_timestamp'], 0, 10));
      } elseif (!empty($psi_desktop_history)) {
        echo htmlspecialchars(substr($psi_desktop_history[0]['fetch_timestamp'], 0, 10)) . ' bis ' . htmlspecialchars(substr($psi_desktop_history[count($psi_desktop_history)-1]['fetch_timestamp'], 0, 10));
      } else {
        echo '–';
      }
    ?>
  </div>
</div>

<script>
  const firmennname = '<?= htmlspecialchars(mb_strtoupper(str_replace([' ', '.', '-'], '', $project['customer_name']))) ?>';
  const domain = '<?= htmlspecialchars(mb_strtoupper(str_replace(['.'], '', parse_url($project['target_url'], PHP_URL_HOST) ?: $project['target_url']))) ?>';
  const datum = '<?= date('Y-m-d') ?>';
  const filename = `${firmennname}_${domain}_REVISION100_HISTORY_${datum}.pdf`;
  document.title = filename;
</script>

</body>
</html>
