<?php
// project.php

// Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

require_once __DIR__ . '/session_handler.php';
check_auth();

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: crm.php');
    exit;
}

$stmt = $db->prepare("SELECT p.*, c.email, c.phone_mobile, c.address, c.city, c.postal_code FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("Projekt nicht gefunden.");
}

// Load default contact
$defaultContact = null;
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? AND is_default = 1 LIMIT 1");
$stmt->execute([$id]);
$defaultContact = $stmt->fetch(PDO::FETCH_ASSOC);

// Data cascade: Define active variables with fallback logic
$active_name = $defaultContact['name'] ?? $project['customer_name'];
$active_email = $defaultContact['email'] ?? $project['email'];
$active_phone = $defaultContact['phone_mobile'] ?? $project['phone_mobile'];

$stmt = $db->prepare("SELECT * FROM interactions WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$interactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lighthouse API Key aus Master-Protokoll
$lighthouseKey = getenv('LIGHTHOUSE_KEY');

function formatPhoneNumber($phone) {
    $phone = trim($phone);
    if (empty($phone)) { return ''; }

    if (strpos($phone, '+49') === 0) {
        return $phone;
    }

    $cleaned = preg_replace('/[^0-9]/', '', $phone);

    if (strpos($cleaned, '0049') === 0) {
        $cleaned = substr($cleaned, 4);
    } elseif (strpos($cleaned, '49') === 0) {
        $cleaned = substr($cleaned, 2);
    } elseif (strpos($cleaned, '0') === 0) {
        $cleaned = substr($cleaned, 1);
    }

    if (strlen($cleaned) < 3) { return $phone; }

    $vorwahl = substr($cleaned, 0, 3);
    $rest = substr($cleaned, 3);

    return '+49 ' . $vorwahl . ' ' . $rest;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Projekt: <?= htmlspecialchars($project['customer_name']) ?></title>
  <style>
    :root { --font-mono: 'JetBrains Mono', monospace; --font-sans: 'Impact', sans-serif; }
    html { overflow-y: scroll; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important; background: #fff !important; background-image: none !important; margin: 0; padding: 0; color: #000; }
    header { background: #fff !important; background-image: none !important; padding: 45px 32px 35px 32px !important; border-bottom: 1px solid #000 !important; margin-bottom: 40px !important; width: 100% !important; box-sizing: border-box !important; display: block !important; }
    .brand { display: flex !important; align-items: center !important; gap: 16px !important; margin: 0 !important; padding: 0 !important; }
    .brand-name { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important; font-size: 32px !important; font-weight: 700 !important; letter-spacing: -1px !important; line-height: 1.0 !important; color: #000 !important; margin: 0 !important; padding: 0 !important; display: inline-block !important; }
    .status-led { width: 12px !important; height: 12px !important; display: inline-block !important; background-color: #2ecc71 !important; border: 1px solid #000 !important; }
    .status-led.unsaved { background-color: #e74c3c !important; }
    .status-led.loading { background-color: #f1c40f !important; }
    .header-claim { font-family: monospace !important; font-size: 11px !important; color: #666 !important; margin-top: 8px !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; display: block !important; }
    .header-logo { text-decoration: none; display: block; margin-bottom: 8px; }
    .header-logo svg { width: 220px; height: 66px; display: block; }
    .header-left, .header-right { display: flex; flex-direction: column; gap: 2px; font-size: 12px; }
    .header-left > div:first-child { font-weight: bold; margin-bottom: 4px; }
    .header-left > div, .header-right > div { line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .header-right > div:first-child { white-space: normal; word-break: break-word; }
    .header-left > a { color: #0066cc; text-decoration: underline; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .header-left-col { display: flex; flex-direction: column; }
    .header-right-col { display: flex; flex-direction: column; justify-content: flex-end; align-self: flex-end; }
    .container { width: 100%; margin: 0; background: #fff; padding: 0; box-sizing: border-box; }
    .content { padding: 16px 20px; margin: 0; }
    .input-with-button { display: flex; gap: 8px; align-items: stretch; }
    .input-with-button input { flex: 1; }
    .btn-square { width: 48px; min-width: 48px; height: 48px; padding: 0; display: flex; align-items: center; justify-content: center; background: #000; color: #fff; border: 1px solid #000; cursor: pointer; font-family: var(--font-mono); flex-shrink: 0; }
    .btn-square:hover { background: #333; }
    h1 { font-family: var(--font-sans); font-size: 48px; margin: 0 0 16px 0; letter-spacing: -1px; text-transform: uppercase; }
    .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 32px; }
    .box { border: 1px solid #000; padding: 12px; margin-bottom: 16px; }
    .box-title { font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 6px; margin-bottom: 10px; font-size: 13px; }
    .btn { background: #000; color: #fff; padding: 10px 16px; border: none; cursor: pointer; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; }
    .btn:hover { background: #333; }
    .interaction { border-bottom: 1px solid #ccc; padding: 12px 0; font-size: 13px; }
    .interaction:last-child { border-bottom: none; }
    .interaction-meta { color: #666; font-size: 11px; margin-bottom: 4px; }
    #lh-result { margin-top: 16px; font-size: 24px; font-weight: bold; }
    a { color: #000; text-decoration: underline; }
    .header-left-title { font-weight: bold; }
    .header-left-url { color: #0066cc; text-decoration: underline; cursor: pointer; }
    .meta-info { margin-bottom: 12px; font-size: 14px; }
    .meta-item { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
    .meta-label { font-size: 10px; color: #666; text-transform: uppercase; }
    .status-squares { display: flex; gap: 6px; margin-top: 8px; }
    .status-square { width: 24px; height: 24px; border-radius: 2px; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; color: #fff; border: 1px solid #000; }
    .action-row { display: flex; flex-direction: row; justify-content: space-between; align-items: flex-start; gap: 32px; width: 100%; margin: 16px 0 20px 0; padding: 0; }
    .action-wrapper { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; width: auto; }
    .action-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; width: auto; margin-left: auto; }
    .action-btn { background: #fff; color: #000; border: 1px solid #000; padding: 8px 12px; font-family: var(--font-mono); font-size: 10px; font-weight: bold; text-transform: uppercase; cursor: pointer; min-height: 36px; display: flex; align-items: center; justify-content: center; text-align: center; }
    .action-btn:hover { background: #f0f0f0; }
    .action-btn:active { transform: scale(0.95); }
    .status-led { display: block; width: 20px; height: 10px; background: #d1d5db; border-radius: 1px; transition: background 0.3s, box-shadow 0.3s; flex-shrink: 0; margin-left: 3px; }
    .status-led.green { background: #0d8659; box-shadow: 0 0 8px rgba(13,134,89,0.6); animation: pulse 0.5s ease-out; }
    .status-led.red { background: #FF3131; box-shadow: 0 0 8px rgba(255,49,49,0.6); animation: pulse 0.5s ease-out; }
    @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
    .action-btn-square { width: 72px; height: 72px; aspect-ratio: 1 / 1; display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; background: #0d7377; color: #fff; border: none; cursor: pointer; font-family: var(--font-mono); font-weight: bold; padding: 6px; margin: 0; box-sizing: border-box; }
    .btn-label { font-size: 9px; font-weight: normal; line-height: 1; margin-bottom: auto; }
    .btn-icon { font-size: 40px; font-weight: bold; margin-left: auto; margin-right: auto; margin-top: auto; width: 100%; text-align: center; }
    .action-btn-square:hover { opacity: 0.9; }
    .action-btn-square:active { transform: scale(0.95); }
    .action-btn-square.lh-square { background: #0d8659; color: #fff; }
    .action-btn-square.lh-square.yellow { background: #FF9529; color: #fff; }
    .action-btn-square.lh-square.orange { background: #FF9529; color: #fff; }
    .action-btn-square.lh-square.red { background: #FF3131; color: #fff; }
    #sendTokenBtn { background: #000; color: #fff; font-size: 32px; }
    .led { width: 32px; height: 16px; border-radius: 2px; display: block; position: absolute; top: -24px; right: 0; background: #bbb; border: 1px solid #999; box-shadow: inset 0 1px 2px rgba(255,255,255,0.5), 0 2px 4px rgba(0,0,0,0.2); transition: background 0.3s, box-shadow 0.3s; }
    .led.green { background: #10b981; box-shadow: inset 0 1px 2px rgba(255,255,255,0.3), 0 0 8px rgba(16,185,129,0.6), 0 2px 4px rgba(0,0,0,0.3); animation: pulse-green 0.5s ease-out; }
    .led.red { background: #ef4444; box-shadow: inset 0 1px 2px rgba(255,255,255,0.3), 0 0 8px rgba(239,68,68,0.6), 0 2px 4px rgba(0,0,0,0.3); animation: pulse-red 0.5s ease-out; }
    .action-button-wrapper { position: relative; display: inline-block; margin-bottom: 12px; }
    .action-button-wrapper.success .led { animation: pulse-green 0.5s ease-out, fadeout 3s ease-out 0.5s forwards; }
    .action-button-wrapper.error .led { animation: pulse-red 0.5s ease-out, fadeout-red 3s ease-out 0.5s forwards; }
    @keyframes pulse-green { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    @keyframes pulse-red { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    @keyframes fadeout { 0% { opacity: 1; background: #10b981; } 100% { opacity: 0.2; background: #bbb; } }
    @keyframes fadeout-red { 0% { opacity: 1; background: #ef4444; } 100% { opacity: 0.2; background: #bbb; } }
    .btn-square { width: 48px; height: 48px; padding: 0; display: flex; align-items: center; justify-content: center; }
    .btn { min-height: 40px; }
    @keyframes fadeout { 0% { opacity: 1; } 100% { opacity: 0; } }
    /* Sub-Navigation Layout-Sicherung */
    .sub-nav {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      padding: 16px 32px;
      border-bottom: 1px solid #000;
      background: #fff;
      box-sizing: border-box;
    }
    .sub-nav-item {
      display: inline-block;
      padding: 6px 12px;
      border: 1px solid #000;
      font-family: monospace;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      text-decoration: none;
      color: #000;
      background: #fff;
      white-space: nowrap;
    }
    .sub-nav-item.active {
      color: #fff;
      background: #000;
    }
    @media (max-width: 768px) {
      header { padding: 25px 16px 19px 16px !important; margin-bottom: 22px !important; }
      .brand-name { font-size: 24px !important; }
      .header-claim { font-size: 10px !important; }
      .logo-link svg { width: 180px; height: 54px; }
      .meta-info { font-size: 12px; margin-bottom: 6px; }
      .status-squares { gap: 4px; margin-top: 6px; }
      .status-square { width: 22px; height: 22px; font-size: 8px; }
      .content { padding: 12px; }
      .action-row { gap: 20px; margin: 14px 0 16px 0; }
      .action-wrapper { gap: 7px; }
      .action-btn-square { width: 70px; height: 70px; padding: 5px; }
      .btn-icon { font-size: 36px; }
      .status-led { width: 18px; height: 9px; }
      textarea { font-size: 14px; min-height: 100px; }
      .btn { min-height: 36px; font-size: 12px; }
      .interaction { padding: 10px 0; font-size: 12px; }
      .interaction-meta { font-size: 10px; }
    }
    @media (max-width: 480px) {
      .header { padding: 8px; }
      .header-top { display: flex; gap: 8px; align-items: center; margin-bottom: 2px; }
      .header-content { gap: 12px; margin-bottom: 6px; }
      .header-left > div, .header-right > div { font-size: 12px; }
      .logo-link svg { width: 160px; height: 48px; }
      .meta-info { font-size: 11px; margin-bottom: 4px; }
      .status-squares { gap: 3px; margin-top: 4px; }
      .status-square { width: 20px; height: 20px; font-size: 7px; }
      .content { padding: 8px; }
      .action-row { gap: 16px; margin: 12px 0 14px 0; }
      .action-wrapper { gap: 6px; }
      .action-btn-square { width: 64px; height: 64px; padding: 4px; }
      .btn-label { font-size: 8px; }
      .btn-icon { font-size: 32px; }
      .status-led { width: 16px; height: 8px; }
      textarea { font-size: 12px; min-height: 80px; }
      .btn { min-height: 32px; font-size: 11px; }
      .interaction { padding: 8px 0; font-size: 11px; }
      .interaction-meta { font-size: 9px; }
    }
  </style>
</head>
<body>

<header>
  <div class="brand"><span class="brand-name">Revision100™</span><span id="statusLed" class="status-led"></span></div>
  <div class="header-claim">Interne Werkbank // System-Zentrale</div>
  <div id="statusSquares" style="display: flex; gap: 4px; margin-top: 12px; height: 12px;"></div>
</header>

<?php
$current_page = basename($_SERVER['PHP_SELF']);
$project_id_param = isset($id) ? '?id=' . $id : '';
?>
<div class="sub-nav">
  <a href="crm.php" class="sub-nav-item <?= ($current_page === 'crm.php') ? 'active' : '' ?>">
    Projekt
  </a>
  <a href="project.php<?= $project_id_param ?>" class="sub-nav-item <?= ($current_page === 'project.php') ? 'active' : '' ?>">
    History
  </a>
  <a href="edit_data.php<?= $project_id_param ?>" class="sub-nav-item <?= ($current_page === 'edit_data.php') ? 'active' : '' ?>">
    Data
  </a>
  <a href="mail.php<?= $project_id_param ?>" class="sub-nav-item <?= ($current_page === 'mail.php') ? 'active' : '' ?>">
    Mail
  </a>
  <a href="pdf.php<?= $project_id_param ?>" class="sub-nav-item <?= ($current_page === 'pdf.php') ? 'active' : '' ?>">
    PDF
  </a>
</div>

<div class="container">
  <div class="content">
    <div class="action-row">
      <div style="display: flex; gap: 24px;">
        <div class="action-wrapper">
          <span class="status-led" id="lhLed"></span>
          <button class="action-btn-square lh-square" id="lhSquare" title="Klick für PSI-Messung">
            <span class="btn-label">Score/PSI</span>
            <span class="btn-icon">-</span>
          </button>
        </div>
        <div class="action-wrapper">
          <span class="status-led" id="tokenLed"></span>
          <button class="action-btn-square" id="sendTokenBtn" style="background: #000;">
            <span class="btn-label">Token</span>
            <span class="btn-icon">↻</span>
          </button>
        </div>
      </div>
    </div>

    <?php if (!empty($project['secret_token'])):
      // Dynamische Ermittlung des Protokolls und Hosts für den absoluten Link
      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
      $client_link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/update.php?token=' . $project['secret_token'];
    ?>
      <div style="margin-top: 16px; padding: 12px; border: 1px dashed #000; background: #fafafa;">
        <label style="display:block; font-family:monospace; font-size:11px; text-transform:uppercase; color:#666; margin-bottom:4px;">
          Externer Kunden-Zugangslink (Token)
        </label>
        <div style="display: flex; gap: 8px; align-items: center;">
          <input type="text" value="<?= htmlspecialchars($client_link) ?>" readonly
                 onclick="this.select(); document.execCommand('copy'); alert('Link kopiert!');"
                 style="flex: 1; font-family: monospace; font-size: 12px; padding: 6px; border: 1px solid #000; background: #fff; cursor: pointer;"
                 title="Klicken zum Kopieren">
          <a href="<?= htmlspecialchars($client_link) ?>" target="_blank"
             style="display: inline-block; padding: 6px 12px; border: 1px solid #000; background: #000; color: #fff; font-family: monospace; font-size: 12px; text-decoration: none; font-weight: bold;">
            ÖFFNEN ↗
          </a>
        </div>
      </div>
    <?php endif; ?>

    <div style="border-top: 1px solid #000; padding-top: 12px;">
      <form id="noteForm" style="margin-bottom: 16px;">
        <textarea id="noteContent" rows="2" style="width: 100%; box-sizing: border-box; border: 1px solid #000; padding: 8px; font-family: var(--font-mono); font-size: 12px;" placeholder="Neue Notiz..."></textarea>
        <button type="submit" class="btn" style="margin-top: 8px; width: 100%;">Notiz speichern</button>
      </form>
      <div id="interactionsList">
        <?php foreach ($interactions as $i): ?>
          <div class="interaction">
            <div class="interaction-meta"><?= htmlspecialchars($i['created_at']) ?> — [<?= htmlspecialchars($i['type']) ?>]</div>
            <div><?= nl2br(htmlspecialchars($i['content'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('noteForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const content = document.getElementById('noteContent').value.trim();
  if (!content) return;
  
  const res = await fetch('api_interactions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project_id: <?= $id ?>, type: 'Notiz', content: content })
  });
  if (res.ok) location.reload();
});

function getLHSquareColor(score) {
  if (score >= 90) return 'green';
  if (score >= 75) return 'yellow';
  if (score >= 50) return 'orange';
  return 'red';
}

function showLED(ledElementId, success = true) {
  const led = document.getElementById(ledElementId);
  if (!led) return;

  led.classList.remove('green', 'red');
  led.classList.add(success ? 'green' : 'red');
  setTimeout(() => {
    led.classList.remove('green', 'red');
  }, 3000);
}

function addInteractionNote(projectId, type, content) {
  fetch('api_interactions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project_id: projectId, type: type, content: content })
  }).catch(e => console.error('Note error:', e));
}

function getCurrentTime() {
  const now = new Date();
  const day = now.getDate();
  const month = now.getMonth() + 1;
  const hours = String(now.getHours()).padStart(2, '0');
  const mins = String(now.getMinutes()).padStart(2, '0');
  return `${day}.${month}. ${hours}:${mins}`;
}


// Token Handler — nur Token generieren, KEINE Email
document.getElementById('sendTokenBtn').addEventListener('click', async (e) => {
  e.preventDefault();
  const btn = e.target;
  btn.disabled = true;

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'generate_token',
        project_id: <?= $id ?>
      })
    });

    const json = await response.json();

    if (json.success && json.token) {
      const tokenLink = `${window.location.origin}/update.php?token=${json.token}`;
      navigator.clipboard.writeText(tokenLink).catch(e => console.error('Clipboard error:', e));
      showLED('tokenLed', true);
      addInteractionNote(<?= $id ?>, 'Aktion', getCurrentTime() + ' — Token generiert');
    } else {
      showLED('tokenLed', false);
      addInteractionNote(<?= $id ?>, 'Fehler', getCurrentTime() + ' — Token-Generierung fehlgeschlagen');
    }
  } catch (e) {
    console.error('Token error:', e);
    showLED('tokenLed', false);
    addInteractionNote(<?= $id ?>, 'Fehler', getCurrentTime() + ' — Token Error: ' + e.message);
  } finally {
    btn.disabled = false;
  }
});

// PSI Handler — Klick auf Score-Button triggert PSI-Abruf
document.getElementById('lhSquare').addEventListener('click', async (e) => {
  e.preventDefault();
  const btn = e.target.closest('.action-btn-square');
  const icon = btn.querySelector('.btn-icon');
  const originalIcon = icon.innerText;
  icon.innerText = '⟳';
  btn.disabled = true;

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'run_psi_now',
        project_id: <?= $id ?>
      })
    });

    const json = await response.json();

    if (json.success) {
      const mobileScore = json.results.mobile?.score;
      const desktopScore = json.results.desktop?.score;

      showLED('lhLed', true);
      const msg = `Messung abgeschlossen — Mobile: ${mobileScore || '–'}, Desktop: ${desktopScore || '–'}`;
      addInteractionNote(<?= $id ?>, 'PSI-Messung', getCurrentTime() + ' — ' + msg);

      // Reload page to show new scores
      setTimeout(() => location.reload(), 1500);
    } else {
      showLED('lhLed', false);
      addInteractionNote(<?= $id ?>, 'Fehler', getCurrentTime() + ' — PSI-Messung fehlgeschlagen: ' + json.error);
    }
  } catch (e) {
    console.error('PSI error:', e);
    showLED('lhLed', false);
    addInteractionNote(<?= $id ?>, 'Fehler', getCurrentTime() + ' — PSI Error: ' + e.message);
  } finally {
    icon.innerText = originalIcon;
    btn.disabled = false;
  }
});

// R100VisionControl™ — Color Palettes
const colorPalettes = {
  green: ['#a3e4d7', '#7ed4c1', '#5cc4ab', '#3bb495', '#1fa47f', '#0d8659'],
  orange: ['#FFE4B5', '#FFD699', '#FFC87D', '#FFBA61', '#FFAB45', '#FF9529'],
  red: ['#FFB3B3', '#FF9999', '#FF7F7F', '#FF6565', '#FF4B4B', '#FF3131'],
  gray: ['#D3D3D3', '#BEBEBE', '#A9A9A9', '#949494', '#7F7F7F', '#696969']
};

const phaseIndex = { anfrage: 0, analyse: 1, kontakt: 2, beauftragung: 3, umsetzung: 4, abgeschlossen: 5 };

// Calculate activity age status
function getAgeStatus(lastInteractionDate) {
  if (!lastInteractionDate) return 'green';
  const last = new Date(lastInteractionDate);
  const now = new Date();
  const days = Math.floor((now - last) / (1000 * 60 * 60 * 24));
  if (days >= 13) return 'gray';
  if (days >= 12) return 'red';
  if (days >= 7) return 'orange';
  return 'green';
}

// Render 6 Phase Squares with R100VisionControl™
function renderPhaseSquares() {
  const currentPhase = '<?= htmlspecialchars($project['tunnel']) ?>';
  const lastInteractionDate = '<?= !empty($project['last_interaction_date']) ? htmlspecialchars($project['last_interaction_date']) : '' ?>';

  const phaseIdx = phaseIndex[currentPhase] || 0;
  const status = getAgeStatus(lastInteractionDate);
  const colors = colorPalettes[status];

  const container = document.getElementById('statusSquares');
  container.innerHTML = '';

  for (let i = 0; i < 6; i++) {
    const square = document.createElement('div');
    square.className = 'status-square';
    square.style.background = i <= phaseIdx ? colors[i] : '#eee';
    square.style.color = i <= phaseIdx ? '#fff' : '#ccc';
    square.textContent = String(i + 1);

    const phaseName = Object.keys(phaseIndex).find(key => phaseIndex[key] === i);
    square.title = phaseName ? phaseName.charAt(0).toUpperCase() + phaseName.slice(1) : '';

    container.appendChild(square);
  }
}

// Lighthouse Button Handler
document.getElementById('lhSquare').addEventListener('click', async () => {
  const url = "<?= htmlspecialchars($project['target_url']) ?>";
  const key = "<?= $lighthouseKey ?>";
  const square = document.getElementById('lhSquare');

  square.querySelector('.btn-icon').innerText = "...";
  square.className = 'action-btn-square lh-square';

  try {
    const apiCall = `https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=${encodeURIComponent(url)}&key=${key}&strategy=mobile&category=performance`;
    const res = await fetch(apiCall);
    const data = await res.json();

    if (data.lighthouseResult) {
      const score = Math.round(data.lighthouseResult.categories.performance.score * 100);
      square.querySelector('.btn-icon').innerText = score;
      square.className = 'action-btn-square lh-square ' + getLHSquareColor(score);
      showLED('lhLed', true);
      addInteractionNote(<?= $id ?>, 'Aktion', 'Lighthouse Messung: ' + score + ' durchgeführt');

      await fetch('api_interactions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_id: <?= $id ?>, action: 'save_score', score: score })
      });

      if (score >= 90) {
        await fetch('api_interactions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ project_id: <?= $id ?>, type: 'Meilenstein', content: '🏆 Elite 90+ Score: ' + score })
        });
      }
    } else {
      square.querySelector('.btn-icon').innerText = "-";
      square.className = 'action-btn-square lh-square';
      showLED('lhLed', false);
      addInteractionNote(<?= $id ?>, 'Fehler', 'Lighthouse Messung fehlgeschlagen');
    }
  } catch (err) {
    square.querySelector('.btn-icon').innerText = "-";
    square.className = 'action-btn-square lh-square';
    showLED('lhLed', false);
    addInteractionNote(<?= $id ?>, 'Fehler', 'Lighthouse API Error: ' + err.message);
  }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  renderPhaseSquares();

  const lastScore = <?= $project['last_score'] !== null ? $project['last_score'] : 'null' ?>;
  if (lastScore !== null) {
    const square = document.getElementById('lhSquare');
    square.querySelector('.btn-icon').innerText = lastScore;
    square.className = 'action-btn-square lh-square ' + getLHSquareColor(lastScore);
  }
});
</script>
</body>
</html>