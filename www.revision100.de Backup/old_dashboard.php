<?php
/**
 * REVISION 100 — Dashboard
 * Projekt-Übersicht mit korrigierter SQLite-Struktur und sauberer Session-Validierung
 */

// ZWINGEND ZUERST: Session und Authentifizierung prüfen (Keine Ausgaben davor!)
require_once __DIR__ . '/session_handler.php';

if (function_exists('check_auth')) {
    check_auth();
} elseif (function_exists('requireAuthPage')) {
    requireAuthPage();
}

// Falls die Authentifizierung fehlschlägt, hat der Session-Handler bereits umgeleitet.
// Ab hier ist der Zugriff autorisiert.

try {
    $db = new PDO('sqlite:' . __DIR__ . '/data/rockets.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Datenbankfehler: ' . $e->getMessage());
}

// Spalten-Mapping: Holt die real existierenden Felder aus der Tabellenstruktur
$stmt = $db->query("SELECT id, customer_name, target_url, tunnel, betrag, status, updated_at, last_score FROM projects ORDER BY updated_at DESC");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Berechne Countdown und mappt Variablen für jedes Projekt
foreach ($projects as &$p) {
    // Brücke für das HTML-Template: tunnel (z.B. anfrage, abgeschlossen) wird zu project_status
    $p['project_status'] = $p['tunnel'] ?? 'anfrage';
    
    // Lighthouse-Scores absichern (last_score mappen, Rest als Platzhalter)
    $p['score_performance'] = $p['last_score'] ?? '—';
    $p['score_best_practices'] = '—'; 
    $p['score_seo'] = '—';            

    // 14-Tage-Frist berechnen (Nutzt updated_at als Basis, falls completed_at fehlt)
    if ($p['project_status'] === 'abgeschlossen' && !empty($p['updated_at'])) {
        try {
            $completed = new DateTime($p['updated_at']);
            $now = new DateTime();
            $interval = $completed->diff($now);
            $days_passed = $interval->days;
            $p['days_remaining'] = max(0, 14 - $days_passed);
            $p['countdown_color'] = $p['days_remaining'] > 10 ? 'blue' : ($p['days_remaining'] > 3 ? 'orange' : 'red');
        } catch (Exception $e) {
            $p['days_remaining'] = 14;
            $p['countdown_color'] = 'blue';
        }
    }
}
unset($p);

?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — REVISION 100</title>
  <link rel="stylesheet" href="style-crm.css">
  <style>
    .dashboard-header {
      padding: 32px 0;
      border-bottom: 1px solid #000;
      margin-bottom: 32px;
    }
    .dashboard-title {
      font-family: monospace;
      font-size: 18px;
      font-weight: 700;
      letter-spacing: -0.5px;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 24px;
      margin-top: 24px;
    }
    .card {
      border: 1px solid #000;
      background: #fff;
      padding: 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .card-meta {
      font-size: 12px;
      text-transform: uppercase;
      margin-bottom: 8px;
      color: #666;
    }
    .card-title {
      font-size: 20px;
      font-weight: 700;
      margin: 0 0 4px 0;
    }
    .card-url {
      font-size: 13px;
      color: #0066cc;
      text-decoration: none;
      margin-bottom: 16px;
      word-break: break-all;
    }
    .card-scores {
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
    }
    .score {
      font-size: 12px;
      padding: 4px 8px;
      border: 1px solid #000;
      font-weight: bold;
    }
    .score.complete { background: #d4edda; border-color: #c3e6cb; color: #155724; }
    .score.incomplete { background: #fff3cd; border-color: #ffeeba; color: #856404; }
    
    .card-countdown {
      font-size: 12px;
      font-weight: bold;
      padding: 6px;
      text-align: center;
      margin-bottom: 16px;
      border: 1px solid #000;
    }
    .countdown-blue { background: #e3f2fd; color: #0d47a1; }
    .countdown-orange { background: #fff3e0; color: #e65100; }
    .countdown-red { background: #ffebee; color: #b71c1c; animation: blink 2s infinite; }
    
    .card-actions {
      display: flex;
      gap: 8px;
    }
    .card-actions button {
      flex: 1;
      padding: 8px;
      background: #000;
      color: #fff;
      border: none;
      cursor: pointer;
      font-weight: bold;
    }
    .card-actions button:hover { background: #333; }
    
    @keyframes blink {
      0% { opacity: 1; }
      50% { opacity: 0.5; }
      100% { opacity: 1; }
    }
  </style>
</head>
<body class="crm-body">

<header style="padding:16px 32px; border-bottom: 1px solid #000;">
  <div class="header-inner" style="padding:0; display:flex; justify-content:space-between; align-items:center;">
    <a href="crm.php" class="logo" style="text-decoration:none; color:#000;">
      <strong style="font-size:24px; letter-spacing:-1px;">REVISION100™ Dashboard</strong>
    </a>
    <nav>
      <a href="crm.php" style="color:#000; font-weight:bold; text-decoration:none;">zur Werkbank (CRM) →</a>
    </nav>
  </div>
</header>

<main style="padding:32px;">

  <?php if (empty($projects)): ?>
    <p>Keine Projekte im System vorhanden.</p>
  <?php else: ?>

    <div class="grid">
      <?php foreach ($projects as $project): ?>
        <div class="card">
          <div>
            <div class="card-meta">
              Phase: <?php echo htmlspecialchars(strtoupper($project['project_status'])); ?> 
              <?php if(!empty($project['betrag'])): ?>
                | <?php echo number_format($project['betrag'], 2, ',', '.'); ?> €
              <?php endif; ?>
            </div>
            <h2 class="card-title"><?php echo htmlspecialchars($project['customer_name'] ?? 'Unbekannte Firma'); ?></h2>
            <div style="margin-bottom:12px;">
              <a href="<?php echo htmlspecialchars($project['target_url'] ?? '#'); ?>" target="_blank" class="card-url">
                <?php echo htmlspecialchars($project['target_url'] ?? 'keine URL'); ?>
              </a>
            </div>
          </div>

          <div class="card-scores">
            <div class="score <?php echo ($project['score_performance'] == 100) ? 'complete' : 'incomplete'; ?>">
              Perf: <?php echo $project['score_performance']; ?>
            </div>
            <div class="score">
              BP: <?php echo $project['score_best_practices']; ?>
            </div>
            <div class="score">
              SEO: <?php echo $project['score_seo']; ?>
            </div>
          </div>

          <?php if ($project['project_status'] === 'abgeschlossen' && isset($project['days_remaining'])): ?>
            <div class="card-countdown countdown-<?php echo $project['countdown_color']; ?>">
              LÖSCH-FRIST: <?php echo $project['days_remaining']; ?> TAGE
            </div>
          <?php endif; ?>

          <div class="card-actions">
            <button onclick="location.href='project.php?id=<?php echo $project['id']; ?>'">Details</button>
            <button onclick="location.href='edit_data.php?id=<?php echo $project['id']; ?>'">Edit</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</main>

</body>
</html>