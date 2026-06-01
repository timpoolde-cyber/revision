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

$stmt = $db->prepare("SELECT c.*, p.id as project_id, p.last_score, p.target_url, p.tunnel FROM customers c JOIN projects p ON c.id = p.customer_id WHERE p.secret_token = ?");
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

// Load project contacts
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? ORDER BY is_default DESC, name ASC");
$stmt->execute([$data['project_id']]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
  <title>Revision100™</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root { --font-mono: 'JetBrains Mono', monospace; --font-sans: 'Impact', sans-serif; --color-primary: #1FA47F; --color-dark: #000; --color-light: #fff; }
    * { box-sizing: border-box; }

    /* Mobile First */
    body {
      background: #fff !important;
      background-image: none !important;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
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
        border: 1px solid #000 !important;
        background-color: #2ecc71 !important; /* Standard Grün */
    }
    .status-led.unsaved {
        background-color: #e74c3c !important; /* Rot bei Änderungen */
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
    .section-title {
      font-size: 14px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid var(--color-dark);
      padding-bottom: 12px;
      margin: 32px 0 20px 0;
    }
    #contactsContainer {
      margin-bottom: 16px;
    }
    .contact-row {
      display: grid;
      grid-template-columns: 2fr 2fr 2fr 2fr auto;
      gap: 12px;
      margin-bottom: 16px;
      align-items: end;
    }
    .contact-row > div {
      display: flex;
      flex-direction: column;
    }
    .contact-row label {
      margin-bottom: 6px;
    }
    .contact-row input {
      padding: 10px;
      border: 1px solid #ddd;
      font-family: var(--font-mono);
      font-size: 14px;
    }
    .contact-row input:focus {
      outline: none;
      border-color: var(--color-primary);
      background: #fafafa;
    }
    .btn-delete {
      background: #ef4444;
      color: var(--color-light);
      border: none;
      padding: 10px 12px;
      font-family: var(--font-mono);
      font-size: 12px;
      font-weight: bold;
      cursor: pointer;
      height: 42px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .btn-delete:active {
      background: #dc2626;
    }
    #addContactBtn {
      background: transparent;
      color: var(--color-dark);
      border: 1px dashed #999;
      padding: 12px 16px;
      font-family: var(--font-mono);
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      cursor: pointer;
      width: 100%;
      text-align: center;
      letter-spacing: 0.5px;
    }
    #addContactBtn:hover {
      background: #f5f5f5;
      border-color: var(--color-dark);
    }
    @media (max-width: 640px) {
      .contact-row {
        grid-template-columns: 1fr;
      }
    }

    /* Desktop */
    @media (min-width: 640px) {
      body { padding: 40px 20px; }
      .wrapper { padding: 40px; }
      h1 { font-size: 32px; }
      .score { font-size: 64px; }
      .btn:hover { background: #333; }
    }
    @media (max-width: 768px) {
      header { padding: 25px 16px 19px 16px !important; margin-bottom: 22px !important; }
      .brand-name { font-size: 24px !important; }
    }
  </style>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= $mapsKey ?>&libraries=places" async defer></script>
</head>
<body>

<header>
    <div class="brand">
        <span class="brand-name">Revision100™</span>
        <span id="statusLed" class="status-led saved"></span>
    </div>
    <div class="header-claim">Kundenportal // Stammdaten-Aktualisierung</div>
</header>

<div class="wrapper">
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

    <h3 class="section-title">Personen / Ansprechpartner im Projekt</h3>

    <?php if (!empty($contacts)): ?>
      <div style="margin-bottom: 24px;">
        <?php foreach ($contacts as $contact): ?>
          <div style="padding: 12px; border: 1px solid #000; margin-bottom: 8px; background: #f9f9f9;">
            <div style="font-weight: bold; margin-bottom: 4px;"><?= htmlspecialchars($contact['name'] ?? '') ?></div>
            <?php if ($contact['role']): ?>
              <div style="font-size: 12px; color: #666; margin-bottom: 2px;">📌 <?= htmlspecialchars($contact['role']) ?></div>
            <?php endif; ?>
            <?php if ($contact['email']): ?>
              <div style="font-size: 12px; color: #666; margin-bottom: 2px;">✉ <?= htmlspecialchars($contact['email']) ?></div>
            <?php endif; ?>
            <?php if ($contact['phone_mobile']): ?>
              <div style="font-size: 12px; color: #666;">📱 <?= htmlspecialchars($contact['phone_mobile']) ?></div>
            <?php endif; ?>
            <?php if ($contact['is_default']): ?>
              <div style="font-size: 10px; color: #0d8659; font-weight: bold; margin-top: 4px;">★ Hauptansprechpartner</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color: #999; font-size: 13px; margin-bottom: 24px;">Keine Kontakte definiert</p>
    <?php endif; ?>

    <h3 class="section-title">Weitere Person hinzufügen</h3>

    <div id="contactsContainer"></div>

    <button type="button" id="addContactBtn">+ Weitere Person hinzufügen</button>

    <button type="submit" class="btn" style="margin-top: 20px;">Daten bestätigen</button>
  </form>
</div>

<script>
  function formatPhoneNumber(input) {
    let value = input.value.trim();
    if (!value) return;

    // 1. Wenn bereits perfekt formatiert (+49 Vorwahl Rufnummer), nichts tun
    if (value.startsWith('+49 ') && value.includes(' ', 5)) {
      return;
    }

    // 2. Spezifischer iPhone-Fix: Die eingeklammerte (0) oder (0) mit Leerzeichen eliminieren
    value = value.replace(/\s*\(0\)\s*/g, ' ');

    // 3. Erst jetzt alle Nicht-Ziffern für die Normalisierung entfernen
    let cleaned = value.replace(/[^0-9]/g, '');

    // 4. Ländervorwahl strikt abschneiden
    if (cleaned.startsWith('0049')) {
      cleaned = cleaned.substring(4);
    } else if (cleaned.startsWith('49')) {
      cleaned = cleaned.substring(2);
    } else if (cleaned.startsWith('0')) {
      cleaned = cleaned.substring(1);
    }

    if (cleaned.length < 3) return;

    // 5. Trennung: Festnetz- und Mobilfunkvorwahlen im Kern 4-stellig (inkl. der abgeschnittenen Null, also hier 3 Stellen)
    const vorwahl = cleaned.substring(0, 4);
    const rest = cleaned.substring(4);

    // Fallback, falls die Nummer zu kurz für eine 4-stellige Vorwahl war
    if (!rest) {
      const vorwahlShort = cleaned.substring(0, 3);
      const restShort = cleaned.substring(3);
      input.value = '+49 ' + vorwahlShort + ' ' + restShort;
      return;
    }

    // 6. Normierte Ausgabe im HfG-Ulm-Design
    input.value = '+49 ' + vorwahl + ' ' + rest;
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

  // Dynamisches Hinzufügen von Personen-Eingaben
  const container = document.getElementById('contactsContainer');
  const addBtn = document.getElementById('addContactBtn');
  let contactIndex = 0;

  function formatPhoneNumberInput(input) {
    let phone = input.value.trim();
    if (!phone) return;

    if (phone.startsWith('+49')) {
      return;
    }

    let cleaned = phone.replace(/\D/g, '');

    if (cleaned.startsWith('0049')) {
      cleaned = cleaned.substring(4);
    } else if (cleaned.startsWith('49')) {
      cleaned = cleaned.substring(2);
    } else if (cleaned.startsWith('0')) {
      cleaned = cleaned.substring(1);
    }

    if (cleaned.length < 3) return;

    const vorwahl = cleaned.substring(0, 3);
    const rest = cleaned.substring(3);

    input.value = '+49 ' + vorwahl + ' ' + rest;
  }

  addBtn.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'contact-row';

    row.innerHTML = `
      <div>
        <label>Name</label>
        <input type="text" name="new_contacts[${contactIndex}][name]" placeholder="Vorname Nachname">
      </div>
      <div>
        <label>Funktion / Rolle</label>
        <input type="text" name="new_contacts[${contactIndex}][role]" placeholder="z.B. Technik">
      </div>
      <div>
        <label>E-Mail</label>
        <input type="email" name="new_contacts[${contactIndex}][email]" placeholder="name@example.com">
      </div>
      <div>
        <label>Telefon</label>
        <input type="tel" name="new_contacts[${contactIndex}][phone]" placeholder="+49 123 456789">
      </div>
      <div>
        <button type="button" class="btn-delete">X</button>
      </div>
    `;

    const deleteBtn = row.querySelector('.btn-delete');
    deleteBtn.addEventListener('click', () => {
      row.remove();
      setSaveStatus(false);
    });

    const phoneField = row.querySelector('input[type="tel"]');
    phoneField.addEventListener('blur', function() {
      formatPhoneNumberInput(this);
    });

    container.appendChild(row);
    contactIndex++;
    setSaveStatus(false);
  });
</script>

</body>
</html>