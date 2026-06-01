<?php
/**
 * REVISION 100 — Dashboard
 * Projekt-Übersicht mit Countdown-Logik
 */

require_once __DIR__ . '/session_handler.php';
requireAuthPage();

try {
    $db = new PDO('sqlite:' . __DIR__ . '/data/rockets.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Datenbankfehler: ' . $e->getMessage());
}

// Hole alle Projekte
$stmt = $db->query("SELECT * FROM projects ORDER BY updated_at DESC");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Berechne Countdown für jedes abgeschlossene Projekt
foreach ($projects as &$p) {
    if ($p['project_status'] === 'abgeschlossen' && $p['completed_at']) {
        $completed = new DateTime($p['completed_at']);
        $now = new DateTime();
        $interval = $completed->diff($now);
        $days_passed = $interval->days;
        $p['days_remaining'] = max(0, 14 - $days_passed);
        $p['countdown_color'] = $p['days_remaining'] > 10 ? 'blue' : ($p['days_remaining'] > 3 ? 'orange' : 'red');
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
      font-family: var(--font-mono);
      font-size: 18px;
      font-weight: 700;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      margin-bottom: 20px;
    }

    .projects-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
      gap: 24px;
      margin-bottom: 48px;
    }

    .project-card {
      border: 1px solid #000;
      background: #fff;
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      transition: box-shadow 0.2s;
    }
    .project-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

    .card-title {
      font-size: 16px;
      font-weight: 700;
      margin: 0;
      color: #000;
    }

    .card-status {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      font-family: var(--font-mono);
      text-transform: uppercase;
      font-weight: 600;
    }

    .phase-icon-sm { width: 24px; height: 24px; }

    .card-scores {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
    }

    .score {
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-mono);
      font-weight: 700;
      padding: 12px 8px;
      border: 1px solid #000;
      font-size: 14px;
      background: #fff;
    }

    .score.complete { color: #4A90E2; }
    .score.incomplete { color: #999; }

    .card-countdown {
      padding: 12px;
      font-family: var(--font-mono);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      border: 1px solid currentColor;
      text-align: center;
    }

    .countdown-blue { color: #4A90E2; }
    .countdown-orange { color: #FF9500; }
    .countdown-red { color: #E74C3C; }

    .card-actions {
      display: flex;
      gap: 8px;
      margin-top: auto;
    }

    .card-actions button {
      flex: 1;
      padding: 10px;
      border: 1px solid #000;
      background: #fff;
      font-family: var(--font-mono);
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      cursor: pointer;
      transition: background 0.1s;
    }
    .card-actions button:hover { background: #000; color: #fff; }

    .empty-state {
      text-align: center;
      padding: 48px 20px;
      color: #888;
    }

    .empty-state p {
      margin: 0 0 20px 0;
    }

    .btn-new-project {
      display: inline-block;
      padding: 12px 24px;
      border: 1px solid #000;
      background: #000;
      color: #fff;
      font-family: var(--font-mono);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      text-decoration: none;
      cursor: pointer;
      transition: background 0.1s;
    }
    .btn-new-project:hover { background: #333; }
  </style>
</head>
<body class="crm-body">

<header>
  <div class="header-inner" style="padding:0 32px;">
    <a href="/" class="logo">REVISION 100</a>
    <nav>
      <a href="crm.php" title="CRM">CRM</a>
      <a href="settings.php" title="Einstellungen">
        <svg width="28" height="28" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="vertical-align:-2px;">
          <path d="M 50,5 L 58,15 L 72,10 L 73,23 L 86,22 L 82,34 L 94,40 L 86,50 L 94,60 L 82,66 L 86,78 L 73,77 L 72,90 L 58,85 L 50,95 L 42,85 L 28,90 L 27,77 L 14,78 L 18,66 L 6,60 L 14,50 L 6,40 L 18,34 L 14,22 L 27,23 L 28,10 L 42,15 Z"
                fill="currentColor" />
          <circle cx="50" cy="50" r="28" fill="white" />
          <circle cx="50" cy="50" r="20" fill="currentColor" />
          <circle cx="50" cy="50" r="8" fill="white" />
        </svg>
      </a>
      <a href="logout.php" title="Logout">
        <svg width="28" height="28" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:-2px;">
          <path d="M 20,10 L 10,10 L 10,90 L 20,90 M 10,50 L 85,50 M 85,50 L 65,30 M 85,50 L 65,70"
                stroke="currentColor" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
    </nav>
  </div>
</header>

<main class="container">

  <div class="dashboard-header">
    <h1 class="dashboard-title">Projekte</h1>
    <a href="crm.php" class="btn-new-project">+ Neues Projekt</a>
  </div>

  <?php if (count($projects) === 0): ?>
    <div class="empty-state">
      <p>Keine Projekte vorhanden.</p>
      <a href="crm.php" class="btn-new-project">+ Erstes Projekt anlegen</a>
    </div>
  <?php else: ?>

    <div class="projects-grid">
      <?php foreach ($projects as $project): ?>
        <div class="project-card">
          <!-- Titel -->
          <h3 class="card-title"><?php echo htmlspecialchars($project['customer_name']); ?></h3>

          <!-- Status mit Phase-Icon -->
          <div class="card-status">
            <?php
            // Wähle Icon basierend auf Phase
            $phase_icons = [
              'sondierung' => 'icons/messschieber.svg',
              'dekontamination' => 'icons/muelltonne.svg',
              'rohbau' => 'icons/abrissbirne.svg',
              'abnahme' => 'icons/chart.svg',
            ];
            $icon = $phase_icons[$project['tunnel']] ?? 'icons/messschieber.svg';
            ?>
            <img src="<?php echo $icon; ?>" alt="Phase" class="phase-icon-sm">
            <span>
              <?php echo ucfirst($project['tunnel']); ?>
              (<?php echo ucfirst($project['project_status']); ?>)
            </span>
          </div>

          <!-- Lighthouse-Scores -->
          <div class="card-scores">
            <div class="score <?php echo ($project['score_performance'] == 100) ? 'complete' : 'incomplete'; ?>">
              <?php echo $project['score_performance'] ?? '—'; ?>
            </div>
            <div class="score <?php echo ($project['score_accessibility'] == 100) ? 'complete' : 'incomplete'; ?>">
              <?php echo $project['score_accessibility'] ?? '—'; ?>
            </div>
            <div class="score <?php echo ($project['score_best_practices'] == 100) ? 'complete' : 'incomplete'; ?>">
              <?php echo $project['score_best_practices'] ?? '—'; ?>
            </div>
            <div class="score <?php echo ($project['score_seo'] == 100) ? 'complete' : 'incomplete'; ?>">
              <?php echo $project['score_seo'] ?? '—'; ?>
            </div>
          </div>

          <!-- 14-Tage-Countdown (nur wenn abgeschlossen) -->
          <?php if ($project['project_status'] === 'abgeschlossen' && isset($project['days_remaining'])): ?>
            <div class="card-countdown countdown-<?php echo $project['countdown_color']; ?>">
              LÖSCH-FRIST: <?php echo $project['days_remaining']; ?> TAGE
            </div>
          <?php endif; ?>

          <!-- Actions -->
          <div class="card-actions">
            <button onclick="editProject(<?php echo $project['id']; ?>)">Bearbeiten</button>
            <button onclick="viewProject(<?php echo $project['id']; ?>)">Details</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</main>

<script>
function editProject(id) {
  // Später: Modal öffnen mit Projekt-Editierformular
  alert('Projekt #' + id + ' bearbeiten (noch nicht implementiert)');
}

function viewProject(id) {
  // Später: Projekt-Details anzeigen
  alert('Projekt #' + id + ' anzeigen (noch nicht implementiert)');
}

// Live-Countdown aktualisieren alle 60 Sekunden
setInterval(function() {
  // Neu laden der Seite oder AJAX-Update
  // Für MVP: Einfaches Auto-Reload nach 60s
}, 60000);
</script>

</body>
</html>
