<?php
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

// Load default contact if available
$defaultContact = null;
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? AND is_default = 1 LIMIT 1");
$stmt->execute([$id]);
$defaultContact = $stmt->fetch(PDO::FETCH_ASSOC);

// Data cascade: Define active variables with fallback logic
$active_name = $defaultContact['name'] ?? $project['customer_name'];
$active_email = $defaultContact['email'] ?? $project['email'];
$active_phone = $defaultContact['phone_mobile'] ?? $project['phone_mobile'];

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
  <title>PDF: <?= htmlspecialchars($project['customer_name']) ?></title>
  <style>
    :root { --font-mono: 'JetBrains Mono', monospace; --font-sans: 'Impact', sans-serif; }
    html { overflow-y: scroll; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important; background: #fff !important; background-image: none !important; margin: 0; padding: 0; color: #000; }
    header {
      background: #fff !important;
      background-image: none !important;
      padding: 45px 32px 35px 32px !important;
      border-bottom: 1px solid #000 !important;
      margin-bottom: 40px !important;
      width: 100% !important;
      box-sizing: border-box !important;
      display: block !important;
    }
    .brand {
      display: flex !important;
      align-items: center !important;
      gap: 16px !important;
      margin: 0 !important;
      padding: 0 !important;
    }
    .brand-name {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
      font-size: 32px !important;
      font-weight: 700 !important;
      letter-spacing: -1px !important;
      line-height: 1.0 !important;
      color: #000 !important;
      margin: 0 !important;
      padding: 0 !important;
      display: inline-block !important;
    }
    .status-led {
      width: 12px !important;
      height: 12px !important;
      display: inline-block !important;
      background-color: #2ecc71 !important;
      border: 1px solid #000 !important;
    }
    .status-led.unsaved {
      background-color: #e74c3c !important;
    }
    .status-led.loading {
      background-color: #f1c40f !important;
    }
    .header-claim {
      font-family: monospace !important;
      font-size: 11px !important;
      color: #666 !important;
      margin-top: 8px !important;
      text-transform: uppercase !important;
      letter-spacing: 0.5px !important;
      display: block !important;
    }
    .header-logo { text-decoration: none; display: block; margin-bottom: 8px; }
    .header-logo svg { width: 220px; height: 66px; display: block; }
    .header-left, .header-right { display: flex; flex-direction: column; gap: 2px; font-size: 12px; }
    .header-left > div:first-child { font-weight: bold; margin-bottom: 4px; }
    .header-left > div, .header-right > div { line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .header-right > div:first-child { white-space: normal; word-break: break-word; }
    .header-left > a { color: #0066cc; text-decoration: underline; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .header-left-col { display: flex; flex-direction: column; }
    .header-right-col { display: flex; flex-direction: column; justify-content: flex-end; align-self: flex-end; }
    .status-squares { display: flex; gap: 6px; margin-top: 8px; }
    .status-square { width: 24px; height: 24px; border-radius: 2px; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; color: #fff; border: 1px solid #000; }
    .container { width: 100%; margin: 0; background: #fff; padding: 0; box-sizing: border-box; overflow-x: hidden; }
    .content { padding: 16px 20px; margin: 0; display: flex; flex-direction: column; gap: 32px; }
    .btn-send { background: #000; color: #fff; border: 1px solid #000; padding: 12px 20px; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; cursor: pointer; margin-top: 16px; width: 100%; }
    .btn-send:hover { background: #333; }
    .pdf-buttons { display: flex; flex-direction: column; gap: 8px; }
    @media (max-width: 768px) {
      header { padding: 25px 16px 19px 16px !important; margin-bottom: 22px !important; }
      .brand-name { font-size: 24px !important; }
      .status-squares { gap: 4px; margin-top: 6px; }
      .status-square { width: 22px; height: 22px; font-size: 8px; }
      .content { padding: 12px 12px; }
    }
    @media (max-width: 480px) {
      .header { padding: 20px 16px 12px 16px !important; margin-bottom: 16px !important; }
      .brand-name { font-size: 24px !important; }
      .status-squares { gap: 3px; margin-top: 4px; }
      .status-square { width: 20px; height: 20px; font-size: 7px; }
      .content { padding: 12px 8px; }
    }
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
    <div class="pdf-buttons">
      <button class="btn-send" style="background: #0066cc;" onclick="window.open('psi_pdf_generator.php?id=<?= $id ?>', '_blank'); return false;">📊 PSI-Report</button>
      <button class="btn-send" style="background: #0066cc;" onclick="window.open('psi_history_pdf_generator.php?id=<?= $id ?>', '_blank'); return false;">📈 PSI-Historie</button>
      <button class="btn-send" style="background: #0066cc;" onclick="window.open('psi_audit_pdf_generator.php?id=<?= $id ?>', '_blank'); return false;">🔍 PSI-Audit</button>
    </div>
  </div>
</div>

<script>
const projectData = {
  id: <?= $id ?>,
  tunnel: '<?= htmlspecialchars($project['tunnel']) ?>',
  last_interaction_date: '<?= !empty($project['last_interaction_date']) ? htmlspecialchars($project['last_interaction_date']) : '' ?>'
};

// Initialize phase squares
const phaseIndex = {anfrage:0, analyse:1, kontakt:2, beauftragung:3, umsetzung:4, abgeschlossen:5};
const colorPalettes = {
  green: ['#a3e4d7', '#7ed4c1', '#5cc4ab', '#3bb495', '#1fa47f', '#0d8659'],
  orange: ['#FFE4B5', '#FFD699', '#FFC87D', '#FFBA61', '#FFAB45', '#FF9529'],
  red: ['#FFB3B3', '#FF9999', '#FF7F7F', '#FF6565', '#FF4B4B', '#FF3131'],
  gray: ['#D3D3D3', '#BEBEBE', '#A9A9A9', '#949494', '#7F7F7F', '#696969']
};

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

function renderPhaseSquares() {
  const phaseIdx = phaseIndex[projectData.tunnel] || 0;
  const lastInteractionDate = projectData.last_interaction_date || '';
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

window.addEventListener('load', renderPhaseSquares);
</script>

</body>
</html>
