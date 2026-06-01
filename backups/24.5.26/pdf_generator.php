<?php
// pdf_generator.php
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

$stmt = $db->prepare("SELECT p.*, c.email, c.phone_mobile, c.address, c.city, c.postal_code FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("Projekt nicht gefunden.");
}

$score = $project['last_score'] !== null ? $project['last_score'] : 38;
// CO2 Formel aus Master-Protokoll
$co2_ersparnis = (1 - ($score / 100)) * 0.9 * 10000 * 12 / 1000;

// Hole Meilensteine
$stmt = $db->prepare("SELECT * FROM interactions WHERE project_id = ? AND type = 'Meilenstein' ORDER BY created_at DESC");
$stmt->execute([$id]);
$milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$phases = ['anfrage', 'analyse', 'kontakt', 'beauftragung', 'umsetzung', 'abgeschlossen'];
$currentPhaseIdx = array_search($project['tunnel'], $phases);
if ($currentPhaseIdx === false) $currentPhaseIdx = 0;
$phaseColors = ['#a3e4d7', '#7ed4c1', '#5cc4ab', '#3bb495', '#1fa47f', '#0d8659'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Status Paper™ - <?= htmlspecialchars($project['customer_name']) ?></title>
  <style>
    :root { 
      --font-mono: 'JetBrains Mono', 'IBM Plex Mono', monospace; 
      --font-sans: 'Impact', sans-serif; 
    }
    body { 
      font-family: var(--font-mono); 
      margin: 0; 
      padding: 0; 
      background: #e0e0e0; 
      color: #000; 
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
      font-family: var(--font-sans); 
      font-size: 48px; 
      letter-spacing: -1px; 
      text-transform: uppercase; 
      margin: 0; 
      line-height: 1; 
    }
    .doc-type { 
      font-size: 16px; 
      font-weight: bold; 
      text-transform: uppercase; 
      letter-spacing: 0.2em; 
    }
    .grid { 
      display: grid; 
      grid-template-columns: 1fr 1fr; 
      gap: 10mm; 
      margin-bottom: 10mm; 
    }
    .label { 
      font-size: 10px; 
      text-transform: uppercase; 
      letter-spacing: 0.1em; 
      color: #000; 
      margin-bottom: 2mm; 
      font-weight: bold;
    }
    .value { 
      font-size: 14px; 
    }
    .visual-control { 
      display: flex; 
      border: 1px solid #000; 
      margin-bottom: 10mm; 
    }
    .vc-segment { 
      flex: 1; 
      height: 12mm; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      font-size: 12px; 
      font-weight: bold; 
      border-right: 1px solid #000; 
    }
    .vc-segment:last-child { border-right: none; }
    .score-box { 
      border: 2px solid #000; 
      padding: 10mm; 
      text-align: center; 
      margin-bottom: 10mm; 
    }
    .score-val { 
      font-family: var(--font-sans); 
      font-size: 100px; 
      line-height: 1; 
      margin: 5mm 0; 
    }
    .milestones { 
      border-top: 2px solid #000; 
      padding-top: 5mm; 
    }
    .milestone-item { 
      padding: 4mm 0; 
      border-bottom: 1px solid #ccc; 
      font-size: 12px; 
      display: flex;
      gap: 10mm;
    }
    .btn-print { 
      position: fixed; 
      bottom: 30px; 
      right: 30px; 
      background: #000; 
      color: #fff; 
      border: none; 
      padding: 16px 24px; 
      font-family: var(--font-mono); 
      font-weight: bold; 
      cursor: pointer; 
      text-transform: uppercase; 
      box-shadow: 0 4px 15px rgba(0,0,0,0.4); 
    }
    .btn-print:hover { background: #333; }
    
    @media print {
      @page { margin: 0; size: A4 portrait; }
      body { background: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .paper { margin: 0; box-shadow: none; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>

<button class="no-print btn-print" onclick="window.print()">ALS PDF SPEICHERN</button>

<div class="paper">
  <div class="header">
    <div>
      <div class="logo">REVISION100™</div>
      <div style="font-size: 11px; margin-top: 2mm; font-weight: bold;">DIAGNOSE & SANIERUNG</div>
    </div>
    <div style="text-align: right;">
      <div class="doc-type">STATUS PAPER™</div>
      <div style="font-size: 11px; margin-top: 2mm;">DATE: <?= date('d.m.Y') ?></div>
      <div style="font-size: 11px;">ID: PRJ-<?= str_pad($project['id'], 6, '0', STR_PAD_LEFT) ?></div>
    </div>
  </div>

  <div class="grid">
    <div>
      <div class="label">Kunde</div>
      <div class="value" style="font-weight: bold;"><?= htmlspecialchars($project['customer_name']) ?></div>
      <div style="font-size: 13px; margin-top: 2mm; line-height: 1.5;">
        <?= htmlspecialchars($project['address']) ?><br>
        <?= htmlspecialchars($project['postal_code'] . ' ' . $project['city']) ?>
      </div>
    </div>
    <div>
      <div class="label">Target URL</div>
      <div class="value"><?= htmlspecialchars($project['target_url']) ?></div>
      <div class="label" style="margin-top: 6mm;">Alert Level</div>
      <div class="value" style="text-transform: uppercase; font-weight: bold; color: <?= $project['alert_level'] === 'eskalation' ? '#B71C1C' : ($project['alert_level'] === 'kritisch' ? '#FB8C00' : '#1FA47F') ?>;">
        <?= htmlspecialchars($project['alert_level']) ?>
      </div>
    </div>
  </div>

  <div class="label">R100 Visual Control™ — Projektphase</div>
  <div class="visual-control">
    <?php for($i = 0; $i < 6; $i++): ?>
      <?php 
        $isActive = $i <= $currentPhaseIdx;
        $bg = $isActive ? $phaseColors[$i] : '#ffffff';
        $color = $isActive ? '#ffffff' : '#cccccc';
      ?>
      <div class="vc-segment" style="background: <?= $bg ?>; color: <?= $color ?>;">0<?= $i+1 ?></div>
    <?php endfor; ?>
  </div>

  <div class="score-box">
    <div class="label">Lighthouse Performance Score</div>
    <div class="score-val" style="<?= $score >= 90 ? 'color: #1FA47F;' : '' ?>"><?= $score ?></div>
    <div style="font-size: 13px; margin-top: 5mm; border-top: 1px solid #000; padding-top: 5mm; text-align: left;">
      <strong style="text-transform: uppercase;">Climate Impact Berechnung:</strong><br>
      Mit dem aktuellen Score generiert das Setup eine jährliche CO₂-Ersparnis von <strong><?= number_format($co2_ersparnis, 2, ',', '.') ?> kg</strong> (Kalkulationsbasis: 10.000 Views/Monat).
    </div>
  </div>

  <div class="label" style="margin-bottom: 5mm;">Meilenstein-Check</div>
  <div class="milestones">
    <?php if(empty($milestones)): ?>
      <div style="font-size: 12px;">Bisher keine verifizierten Meilensteine.</div>
    <?php else: ?>
      <?php foreach($milestones as $m): ?>
        <div class="milestone-item">
          <div style="font-weight: bold; width: 40mm; flex-shrink: 0;">
            <?= date('d.m.Y H:i', strtotime($m['created_at'])) ?>
          </div>
          <div>
            <?= htmlspecialchars($m['content']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

</body>
</html>