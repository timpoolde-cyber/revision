<?php
// update.php

// Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Ungültiger Zugangslink.");
}

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->prepare("SELECT c.*, p.id as project_id, p.last_score, p.target_url, p.tunnel FROM customers c JOIN projects p ON c.id = p.customer_id WHERE c.secret_token = ?");
$stmt->execute([$token]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Link abgelaufen oder ungültig.");
}

// Check token validity
$tokenValid = true;
if ($data['tunnel'] === 'abgeschlossen') {
    $createdAt = new DateTime($data['token_created_at'] ?? date('Y-m-d H:i:s'));
    $now = new DateTime();
    $diff = $now->diff($createdAt);
    if ($diff->days > 5) {
        $tokenValid = false;
    }
}

if (!$tokenValid) {
    die("Dieser Zugangslink ist abgelaufen (Projekt abgeschlossen).");
}

// Register token usage
$stmt = $db->prepare("UPDATE customers SET token_used_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$data['id']]);

// Log token usage in interactions
$stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, 'Token-Verwendung', ?)");
$time = (new DateTime())->format('H:i');
$stmt->execute([$data['project_id'], "$time — Kunde hat Token verwendet (Daten aktualisiert)"]);

$score = $data['last_score'];
$co2_ersparnis = null;
if ($score !== null && $score !== '') {
    $score_num = (float)$score;
    // CO2-Ersparnis basierend auf aktuellem Score (vs. WordPress-Standard 38)
    $standard_score = 38;
    $co2_ersparnis = (($score_num - $standard_score) / 100) * 0.9 * 10000 * 12 / 1000;
} 

// Google Maps API aus Master-Protokoll
$mapsKey = getenv('GOOGLE_MAPS_KEY');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>REVISION100™ — Client Update</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root { --font-mono: 'JetBrains Mono', monospace; --font-sans: 'Impact', sans-serif; --color-primary: #1FA47F; --color-dark: #000; --color-light: #fff; }
    * { box-sizing: border-box; }

    /* Mobile First */
    body {
      font-family: var(--font-mono);
      background: #000;
      color: var(--color-light);
      margin: 0;
      padding: 20px 16px;
      font-size: 16px;
    }
    .wrapper {
      max-width: 600px;
      margin: 0 auto;
      background: var(--color-light);
      color: var(--color-dark);
      padding: 20px 16px;
      position: relative;
    }
    .close-btn {
      position: absolute;
      top: 16px;
      right: 16px;
      background: none;
      border: none;
      font-size: 28px;
      color: #666;
      cursor: pointer;
      padding: 0;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .close-btn:hover {
      color: #000;
    }
    h1 {
      font-family: var(--font-mono);
      font-size: 16px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid var(--color-dark);
      padding-bottom: 12px;
      margin: 28px 0 20px 0;
      font-weight: bold;
    }
    h2 {
      font-size: 16px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid var(--color-dark);
      padding-bottom: 12px;
      margin: 28px 0 20px 0;
      font-weight: bold;
    }
    p {
      font-size: 14px;
      line-height: 1.6;
      margin: 0 0 16px 0;
      color: #333;
    }
    .metric-box {
      border: 2px solid var(--color-dark);
      padding: 16px;
      margin: 20px 0;
      text-align: center;
      background: #f9f9f9;
    }
    .score {
      font-family: var(--font-sans);
      font-size: 48px;
      line-height: 1;
      margin: 12px 0;
      <?php if($score >= 90) echo 'color: var(--color-primary);'; ?>
    }
    .score-label {
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #666;
      margin-bottom: 8px;
    }
    .badge {
      background: var(--color-primary);
      color: var(--color-light);
      padding: 6px 12px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      display: inline-block;
      margin-top: 8px;
      letter-spacing: 0.5px;
    }
    .climate-impact {
      background: #f0fdf8;
      border-left: 4px solid var(--color-primary);
      padding: 16px;
      margin: 20px 0 28px 0;
      font-size: 13px;
      line-height: 1.7;
      color: #1a5f47;
    }
    .climate-impact strong {
      color: var(--color-primary);
      font-weight: 700;
    }
    .empty-state {
      text-align: center;
      padding: 32px 20px;
      background: #f5f5f5;
      border: 2px solid #e0e0e0;
      color: #999;
    }
    .empty-state-label {
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 12px;
      color: #999;
    }
    .form-group {
      margin-bottom: 20px;
    }
    label {
      display: block;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
      color: #333;
    }
    input {
      width: 100%;
      padding: 14px;
      border: 1px solid #ddd;
      font-family: var(--font-mono);
      font-size: 16px;
      background: var(--color-light);
    }
    input:focus {
      outline: none;
      border-color: var(--color-primary);
      background: #fafafa;
    }
    .btn {
      background: var(--color-dark);
      color: var(--color-light);
      border: none;
      padding: 16px 20px;
      width: 100%;
      font-family: var(--font-mono);
      font-weight: bold;
      text-transform: uppercase;
      cursor: pointer;
      font-size: 14px;
      min-height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      letter-spacing: 0.5px;
    }
    .btn:active {
      background: #333;
    }
    .project-url {
      color: var(--color-dark);
      font-weight: 600;
      word-break: break-all;
    }
    .save-status {
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: var(--font-mono);
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 16px;
    }
    .status-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .status-dot.saved {
      background: #10b981;
    }
    .status-dot.unsaved {
      background: #ef4444;
    }

    /* Desktop */
    @media (min-width: 640px) {
      body { padding: 40px 20px; }
      .wrapper { padding: 40px; }
      h1 { font-size: 32px; }
      .score { font-size: 64px; }
      .btn:hover { background: #333; }
    }
  </style>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= $mapsKey ?>&libraries=places" async defer></script>
</head>
<body>

<div class="wrapper">
  <a href="/" style="text-decoration:none;display:block;margin-bottom:24px;">
    <svg width="220" height="66" viewBox="0 0 500 150" xmlns="http://www.w3.org/2000/svg" style="display:block;">
      <rect width="100%" height="100%" fill="white"/>
      <text x="10" y="60%" dominant-baseline="middle" text-anchor="start" style="font-family: 'Impact', 'Haettenschweiler', 'Arial Narrow Bold', sans-serif; font-weight: 900; font-size: 60px; letter-spacing: -2px; fill: black;">REVISION100<tspan dy="-5" font-size="44px">™</tspan></text>
    </svg>
  </a>

  <h1>Status Report</h1>
  <p>Projekt: <span class="project-url"><?= htmlspecialchars($data['target_url']) ?></span></p>

  <?php if ($score !== null): ?>
  <div class="metric-box">
    <div class="score-label">Aktueller Performance-Score</div>
    <div class="score"><?= $score ?></div>
    <?php if ($score >= 90): ?>
      <span class="badge">Elite 90+ Status verifiziert</span>
    <?php else: ?>
      <span style="font-size: 12px; color: #FB8C00; font-weight: bold; text-transform: uppercase;">Sanierung empfohlen</span>
    <?php endif; ?>
    <?php if ($co2_ersparnis !== null): ?>
    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd; font-size: 12px; line-height: 1.6; color: #000;">
      Climate Impact (Basis 10.000 Views/Monat):<br>
      Mit einem Score von <?= (int)$score ?> sparen Sie gegenüber dem Standard (38) jährlich ca. <?= number_format($co2_ersparnis, 2, ',', '.') ?> kg CO₂ ein.
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="metric-box empty-state">
    <div class="empty-state-label">Performance-Score</div>
    <div style="font-size: 14px; padding: 16px 0;">Noch nicht gemessen</div>
  </div>
  <?php endif; ?>

  <h2>Stammdaten verifizieren</h2>

  <div class="save-status">
    <div class="status-dot saved" id="statusDot"></div>
    <span id="statusText">gesichert</span>
  </div>

  <form action="api_client_update.php" method="POST" id="updateForm">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

    <div class="form-group">
      <label>Name</label>
      <input type="text" id="nameInput" name="name" value="<?= htmlspecialchars($data['customer_name'] ?? '') ?>" placeholder="Vorname Nachname" autocomplete="name">
    </div>

    <div class="form-group">
      <label>Mobil</label>
      <input type="tel" id="phoneInput" name="phone_mobile" value="<?= htmlspecialchars($data['phone_mobile'] ?? '') ?>" placeholder="+49 123 456789" autocomplete="tel">
    </div>

    <div class="form-group">
      <label>Mail</label>
      <input type="email" id="emailInput" name="email" value="<?= htmlspecialchars($data['email'] ?? '') ?>" placeholder="name@example.com" autocomplete="email">
    </div>

    <div class="form-group">
      <label>Unternehmensstandort (Auto-Complete)</label>
      <input type="text" id="addressInput" name="address" value="<?= htmlspecialchars($data['address']) ?>" placeholder="Straße eingeben...">
    </div>

    <input type="hidden" id="lat" name="latitude" value="<?= htmlspecialchars($data['latitude']) ?>">
    <input type="hidden" id="lng" name="longitude" value="<?= htmlspecialchars($data['longitude']) ?>">

    <button type="submit" class="btn">Daten bestätigen</button>
  </form>
</div>

<script>
  function formatPhoneNumber(input) {
    let digits = input.value.replace(/\D/g, '');
    if (!digits) return;

    if (digits.startsWith('49')) {
      digits = digits;
    } else if (digits.startsWith('0')) {
      digits = '49' + digits.substring(1);
    } else if (!digits.startsWith('49')) {
      digits = '49' + digits;
    }

    let formatted = '+' + digits;
    if (digits.length >= 11) {
      formatted = formatted.replace(/(\+49)(\d{3})(\d+)/, '$1 $2 $3');
    }

    input.value = formatted;
  }

  const phoneInput = document.getElementById('phoneInput');
  phoneInput.addEventListener('input', function(e) {
    formatPhoneNumber(e.target);
  });
  phoneInput.addEventListener('change', function(e) {
    formatPhoneNumber(e.target);
  });
  phoneInput.addEventListener('blur', function(e) {
    formatPhoneNumber(e.target);
  });

  function initAutocomplete() {
    const input = document.getElementById('addressInput');
    if (!input || typeof google === 'undefined' || !google.maps) return;

    try {
      const autocomplete = new google.maps.places.Autocomplete(input);

      autocomplete.addListener('place_changed', function() {
        const place = autocomplete.getPlace();
        if (!place.geometry) return;
        document.getElementById('lat').value = place.geometry.location.lat();
        document.getElementById('lng').value = place.geometry.location.lng();
      });
    } catch (e) {
      console.error('Autocomplete initialization failed:', e);
    }
  }
  window.addEventListener('load', initAutocomplete);

  function setSaveStatus(saved) {
    const dot = document.getElementById('statusDot');
    const text = document.getElementById('statusText');
    if (saved) {
      dot.className = 'status-dot saved';
      text.textContent = 'gesichert';
    } else {
      dot.className = 'status-dot unsaved';
      text.textContent = 'ungesichert';
    }
  }

  const form = document.getElementById('updateForm');
  const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"]');

  inputs.forEach(input => {
    input.addEventListener('change', () => setSaveStatus(false));
    input.addEventListener('input', () => setSaveStatus(false));
  });

  form.addEventListener('submit', () => {
    setSaveStatus(true);
  });
</script>

</body>
</html>