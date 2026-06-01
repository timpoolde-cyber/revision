<?php
// psi_pdf_generator.php
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

// Load latest mobile PSI
$stmt = $db->prepare("SELECT * FROM psi_results WHERE project_id = ? AND strategy = 'mobile' ORDER BY fetch_timestamp DESC LIMIT 1");
$stmt->execute([$id]);
$psi_mobile = $stmt->fetch(PDO::FETCH_ASSOC);

// Load latest desktop PSI
$stmt = $db->prepare("SELECT * FROM psi_results WHERE project_id = ? AND strategy = 'desktop' ORDER BY fetch_timestamp DESC LIMIT 1");
$stmt->execute([$id]);
$psi_desktop = $stmt->fetch(PDO::FETCH_ASSOC);

function getLHColor($score) {
    if (!$score) return '#eee';
    if ($score >= 90) return '#0d8659';
    if ($score >= 75) return '#FF9529';
    if ($score >= 50) return '#FF9529';
    return '#FF3131';
}

function getColorHex($score) {
    if ($score >= 90) return '#0d8659';
    if ($score >= 75) return '#FF9529';
    if ($score >= 50) return '#FF9529';
    return '#FF3131';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>PSI Report - <?= htmlspecialchars($project['customer_name']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'JetBrains Mono', 'IBM Plex Mono', monospace;
      background: #e0e0e0;
      color: #000;
      line-height: 1.6;
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
    h1 { font-size: 24px; font-weight: bold; margin: 10mm 0 5mm 0; text-transform: uppercase; }
    .score-section { margin-bottom: 15mm; }
    .score-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; margin-top: 5mm; }
    .score-card {
      border: 1px solid #000;
      padding: 5mm;
      border-radius: 2px;
      text-align: center;
    }
    .score-label { font-size: 9px; text-transform: uppercase; font-weight: bold; margin-bottom: 3mm; }
    .score-value {
      font-size: 48px;
      font-weight: 900;
      color: #fff;
      padding: 8mm;
      margin: 0 -5mm -5mm -5mm;
      border-radius: 2px;
    }
    .score-name { font-size: 10px; margin-top: 3mm; font-weight: bold; }
    .raw-json { margin-top: 15mm; border-top: 2px solid #000; padding-top: 10mm; }
    .raw-json-title { font-weight: bold; text-transform: uppercase; font-size: 11px; margin-bottom: 5mm; }
    .raw-json-content {
      font-size: 8px;
      background: #f5f5f5;
      padding: 5mm;
      border: 1px solid #ddd;
      border-radius: 2px;
      max-height: 300px;
      overflow-y: auto;
      white-space: pre-wrap;
      word-break: break-all;
      font-family: 'Courier New', monospace;
    }
    .footer { margin-top: 15mm; padding-top: 5mm; border-top: 1px solid #ccc; font-size: 9px; color: #666; }
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
      <div style="margin-top: 3mm; font-size: 9px;"><?= date('d.m.Y H:i') ?></div>
    </div>
  </div>

  <?php if ($psi_mobile): ?>
  <div class="score-section">
    <h1>📱 Mobile Performance</h1>
    <div class="score-grid">
      <div class="score-card">
        <div class="score-label">Performance</div>
        <div class="score-value" style="background: <?= getColorHex($psi_mobile['performance_score']) ?>;">
          <?= $psi_mobile['performance_score'] ?>
        </div>
        <div class="score-name">Score</div>
      </div>
      <div class="score-card">
        <div class="score-label">Accessibility</div>
        <div class="score-value" style="background: <?= getColorHex($psi_mobile['accessibility_score']) ?>;">
          <?= $psi_mobile['accessibility_score'] ?>
        </div>
        <div class="score-name">Score</div>
      </div>
      <div class="score-card">
        <div class="score-label">Best Practices</div>
        <div class="score-value" style="background: <?= getColorHex($psi_mobile['best_practices_score']) ?>;">
          <?= $psi_mobile['best_practices_score'] ?>
        </div>
        <div class="score-name">Score</div>
      </div>
      <div class="score-card">
        <div class="score-label">SEO</div>
        <div class="score-value" style="background: <?= getColorHex($psi_mobile['seo_score']) ?>;">
          <?= $psi_mobile['seo_score'] ?>
        </div>
        <div class="score-name">Score</div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10mm; margin-bottom: 10mm; border-radius: 2px;">
    <strong>⚠️ Mobile-Messung:</strong> Keine Daten verfügbar. Führen Sie zuerst eine Messung durch.
  </div>
  <?php endif; ?>

  <?php if ($psi_desktop): ?>
  <div class="score-section">
    <h1>🖥️ Desktop Performance</h1>
    <div class="score-grid">
      <div class="score-card">
        <div class="score-label">Performance</div>
        <div class="score-value" style="background: <?= getColorHex($psi_desktop['performance_score']) ?>;">
          <?= $psi_desktop['performance_score'] ?>
        </div>
        <div class="score-name">Score</div>
      </div>
      <div class="score-card">
        <div class="score-label">Accessibility</div>
        <div class="score-value" style="background: <?= getColorHex($psi_desktop['accessibility_score']) ?>;">
          <?= $psi_desktop['accessibility_score'] ?>
        </div>
        <div class="score-name">Score</div>
      </div>
      <div class="score-card">
        <div class="score-label">Best Practices</div>
        <div class="score-value" style="background: <?= getColorHex($psi_desktop['best_practices_score']) ?>;">
          <?= $psi_desktop['best_practices_score'] ?>
        </div>
        <div class="score-name">Score</div>
      </div>
      <div class="score-card">
        <div class="score-label">SEO</div>
        <div class="score-value" style="background: <?= getColorHex($psi_desktop['seo_score']) ?>;">
          <?= $psi_desktop['seo_score'] ?>
        </div>
        <div class="score-name">Score</div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10mm; margin-bottom: 10mm; border-radius: 2px;">
    <strong>⚠️ Desktop-Messung:</strong> Keine Daten verfügbar. Führen Sie zuerst eine Messung durch.
  </div>
  <?php endif; ?>

  <?php if ($psi_mobile && $psi_mobile['raw_response']): ?>
  <div class="raw-json">
    <div class="raw-json-title">📋 Raw Data (Mobile API Response)</div>
    <div class="raw-json-content"><?= htmlspecialchars(json_encode(json_decode($psi_mobile['raw_response']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></div>
  </div>
  <?php endif; ?>

  <div class="footer">
    <strong>Projekt ID:</strong> <?= htmlspecialchars($id) ?> |
    <strong>Gemessen:</strong> <?= $psi_mobile ? htmlspecialchars($psi_mobile['fetch_timestamp']) : '–' ?> |
    <strong>Kunde:</strong> <?= htmlspecialchars($project['email'] ?? '–') ?>
  </div>
</div>

<script>
  const firmennname = '<?= htmlspecialchars(mb_strtoupper(str_replace([' ', '.', '-'], '', $project['customer_name']))) ?>';
  const domain = '<?= htmlspecialchars(mb_strtoupper(str_replace(['.'], '', parse_url($project['target_url'], PHP_URL_HOST) ?: $project['target_url']))) ?>';
  const datum = '<?= date('Y-m-d') ?>';
  const filename = `${firmennname}_${domain}_REVISION100_${datum}.pdf`;
  document.title = filename;
</script>

</body>
</html>
