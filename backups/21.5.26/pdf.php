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

$stmt = $db->prepare("SELECT p.*, c.email, c.phone_mobile, c.address, c.city, c.postal_code, c.secret_token FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
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

function formatPhoneNumber($phone) {
    if (!$phone) return '';
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strpos($phone, '+') === 0) {
        return $phone;
    }
    if (substr($phone, 0, 1) === '0') {
        $phone = '+49' . substr($phone, 1);
    } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '1') {
        $phone = '+49' . $phone;
    } elseif (strlen($phone) === 10 && is_numeric($phone)) {
        $phone = '+49' . $phone;
    }
    return $phone;
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
    body { font-family: var(--font-mono); background: #f0f0f0; margin: 0; padding: 0; color: #000; }
    .header { position: sticky; top: 0; background: #fff; border-bottom: 1px solid #000; padding: 8px 16px; z-index: 1000; width: 100%; box-sizing: border-box; }
    .header { padding: 8px 16px; }
    .header-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: flex-start; }
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
      .header { padding: 8px 12px; }
      .status-squares { gap: 4px; margin-top: 6px; }
      .status-square { width: 22px; height: 22px; font-size: 8px; }
      .content { padding: 12px 12px; }
    }
    @media (max-width: 480px) {
      .header { padding: 8px; }
      .status-squares { gap: 3px; margin-top: 4px; }
      .status-square { width: 20px; height: 20px; font-size: 7px; }
      .content { padding: 12px 8px; }
    }
  </style>
</head>
<body>

<header class="header">
  <div class="header-grid">
    <div class="header-left-col">
      <a href="/" class="header-logo">
        <svg width="220" height="66" viewBox="0 0 500 150" xmlns="http://www.w3.org/2000/svg" style="display:block;">
          <rect width="100%" height="100%" fill="white"/>
          <text x="10" y="60%" dominant-baseline="middle" text-anchor="start" style="font-family: 'Impact', 'Haettenschweiler', 'Arial Narrow Bold', sans-serif; font-weight: 900; font-size: 60px; letter-spacing: -2px; fill: black;">REVISION100<tspan dy="-5" font-size="44px">™</tspan></text>
        </svg>
      </a>
      <div class="header-left">
        <div><?= htmlspecialchars($project['customer_name']) ?></div>
        <?php if ($defaultContact): ?>
          <div><?= htmlspecialchars($defaultContact['name']) ?></div>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($project['target_url']) ?>" target="_blank"><?php echo parse_url($project['target_url'], PHP_URL_HOST) ?: htmlspecialchars($project['target_url']); ?></a>
      </div>
    </div>
    <div class="header-right-col">
      <div class="header-right">
        <div><?= htmlspecialchars($project['address'] ?? '') ?></div>
        <div><?= htmlspecialchars($project['email'] ?? '') ?></div>
        <div><?= htmlspecialchars(formatPhoneNumber($project['phone_mobile'] ?? '')) ?></div>
      </div>
    </div>
  </div>
  <div class="status-squares" id="statusSquares" style="margin-top: 8px;">
    <!-- Wird vom JS gefüllt mit 6 Quadraten -->
  </div>
</header>

<?php include '_nav.php'; ?>

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
  tunnel: '<?= htmlspecialchars($project['tunnel']) ?>'
};

// Initialize phase squares
const phaseIndex = {anfrage:0, analyse:1, kontakt:2, beauftragung:3, umsetzung:4, abgeschlossen:5};
const colorPalettes = {
  green: ['#a3e4d7', '#7ed4c1', '#5cc4ab', '#3bb495', '#1fa47f', '#0d8659'],
  gray: ['#D3D3D3', '#BEBEBE', '#A9A9A9', '#949494', '#7F7F7F', '#696969']
};

function renderPhaseSquares() {
  const phaseIdx = phaseIndex[projectData.tunnel] || 0;
  const colors = colorPalettes.green;
  const container = document.getElementById('statusSquares');
  container.innerHTML = '';

  for (let i = 0; i < 6; i++) {
    const square = document.createElement('div');
    square.className = 'status-square';
    square.style.background = i <= phaseIdx ? colors[i] : '#eee';
    square.style.color = i <= phaseIdx ? '#fff' : '#ccc';
    square.textContent = String(i + 1);
    container.appendChild(square);
  }
}

window.addEventListener('load', renderPhaseSquares);
</script>

</body>
</html>
