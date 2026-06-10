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

require_once __DIR__ . '/Logger.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Ungültiger Zugangslink.");
}

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Logger::init($db);

$stmt = $db->prepare("SELECT c.*, p.id as project_id, p.last_score, p.target_url, p.tunnel FROM customers c JOIN projects p ON c.id = p.customer_id WHERE p.secret_token = ?");
$stmt->execute([$token]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Link abgelaufen oder ungültig.");
}

$now = new DateTime();
$tokenExpired = false;

if (isset($data['token_expires']) && !empty($data['token_expires'])) {
    $expiresAt = new DateTime($data['token_expires']);
    if ($now > $expiresAt) {
        $tokenExpired = true;
    }
}

if ($tokenExpired) {
    Logger::logTokenValidation($data['project_id'] ?? 'unknown', false);
    die("Zugriff abgelaufen.");
}

Logger::logTokenValidation($data['project_id'] ?? 'unknown', true);

// Register token usage
$stmt = $db->prepare("UPDATE customers SET token_used_at = CURRENT_TIMESTAMP WHERE id = ?");
$executeResult = $stmt->execute([$data['id']]);

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
  <link rel="stylesheet" href="style-crm.css">
  <style>
    h1, h2 {
      font-family: var(--font-mono);
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
      font-family: var(--font-mono);
      font-size: 48px;
      line-height: 1;
      margin: 12px 0;
      font-weight: bold;
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

    /* CSS Styling für das klassische Google Autocomplete Dropdown */
    .pac-container {
      border: 1px solid #000 !important;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
      font-family: var(--font-sans) !important;
      z-index: 99999 !important;
    }
    .pac-item {
      padding: 10px !important;
      font-size: 13px !important;
      cursor: pointer;
    }
    .pac-item:hover {
      background-color: #f5f5f5 !important;
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
    .status-dot.saved { background: #10b981; }
    .status-dot.unsaved { background: #ef4444; }
    
    .section-title {
      font-size: 13px;
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
      border: 1px solid #000;
      padding: 14px;
      background: #fafafa;
    }
    .contact-row > div {
      display: flex;
      flex-direction: column;
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
      height: 38px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    #addContactBtn {
      background: transparent;
      color: var(--color-dark);
      border: 1px dashed #000;
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
    @media (max-width: 640px) {
      .contact-row { grid-template-columns: 1fr; gap: 8px; }
    }
    @media (min-width: 640px) {
      body { padding: 40px 20px; }
      .container { padding: 40px; }
      h1 { font-size: 24px; }
      .score { font-size: 64px; }
    }
    @media (max-width: 768px) {
      header { padding: 25px 16px 19px 16px !important; margin-bottom: 22px !important; }
      .brand-name { font-size: 24px !important; }
    }
  </style>
</head>
<body>

<header>
  <div class="brand"><span class="brand-name">Revision100™</span></div>
  <div class="header-claim">Kundenportal // Stammdaten-Aktualisierung</div>
</header>

<div class="container">
  <div class="content">
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

    <div class="form-group" style="background: #fafafa; padding: 14px; border: 1px dashed #000; margin-bottom: 24px;">
      <label class="form-label" style="color: #000;">⚡ Google Blitz-Suche (Findet Ihre Firma automatisch)</label>
      <input type="text" id="googleSearchField" class="form-input" placeholder="Unternehmensnamen tippen..." autocomplete="off">
    </div>

    <div class="form-group">
      <label class="form-label">Firma / Kundenname</label>
      <input type="text" id="customerName" class="form-input" name="customer_name" value="<?= htmlspecialchars($data['customer_name'] ?? '') ?>" placeholder="Firmenname">
    </div>

    <div class="form-group">
      <label class="form-label">Straße & Hausnummer</label>
      <input type="text" id="address" class="form-input" name="address" value="<?= htmlspecialchars($data['address'] ?? '') ?>" placeholder="Straße und Hausnummer">
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%;">
      <div class="form-group">
        <label class="form-label">Stadt</label>
        <input type="text" id="city" class="form-input" name="city" value="<?= htmlspecialchars($data['city'] ?? '') ?>" placeholder="Stadt">
      </div>
      <div class="form-group">
        <label class="form-label">PLZ</label>
        <input type="text" id="postalCode" class="form-input" name="postal_code" value="<?= htmlspecialchars($data['postal_code'] ?? '') ?>" placeholder="Postleitzahl">
      </div>
    </div>

    <input type="hidden" id="lat" name="latitude" value="<?= htmlspecialchars($data['latitude'] ?? '') ?>">
    <input type="hidden" id="lng" name="longitude" value="<?= htmlspecialchars($data['longitude'] ?? '') ?>">

    <div class="section-title" style="margin-top: 32px;">Personen / Ansprechpartner im Projekt</div>

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

    <div class="section-title" style="margin-top: 32px;">Weitere Person hinzufügen</div>

    <div id="contactsContainer"></div>

    <button type="button" id="addContactBtn">+ Weitere Person hinzufügen</button>

    <button type="submit" class="btn" style="margin-top: 20px;">Daten bestätigen</button>
  </form>
  </div>
</div>

<script>
  // Klassischer Google Autocomplete Initialisierer
  function initAutocomplete() {
    const input = document.getElementById('googleSearchField');
    if (!input) return;

    const autocomplete = new google.maps.places.Autocomplete(input, {
      types: ['establishment'],
      componentRestrictions: { country: 'de' },
      fields: ['address_components', 'name', 'geometry']
    });

    autocomplete.addListener('place_changed', () => {
      const place = autocomplete.getPlace();
      if (!place || !place.address_components) return;

      let street = '', itemNumber = '', city = '', postalCode = '';

      place.address_components.forEach(comp => {
        const types = comp.types;
        if (types.includes('route')) street = comp.long_name;
        if (types.includes('street_number')) itemNumber = comp.long_name;
        if (types.includes('locality')) city = comp.long_name;
        if (types.includes('postal_code')) postalCode = comp.long_name;
      });

      // Zuweisung in die strukturierten Formularfelder
      if (place.name) {
        document.getElementById('customerName').value = place.name;
      }
      document.getElementById('address').value = street + (itemNumber ? ' ' + itemNumber : '');
      document.getElementById('city').value = city;
      document.getElementById('postalCode').value = postalCode;
      
      if (place.geometry && place.geometry.location) {
        document.getElementById('lat').value = place.geometry.location.lat();
        document.getElementById('lng').value = place.geometry.location.lng();
      }

      setSaveStatus(false);
      // Suchfeld leeren
      setTimeout(() => { input.value = ''; }, 50);
    });
  }

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

  const container = document.getElementById('contactsContainer');
  const addBtn = document.getElementById('addContactBtn');
  let contactIndex = 0;

  function formatPhoneNumberInput(input) {
    let phone = input.value.trim();
    if (!phone) return;
    if (phone.startsWith('+49')) return;

    let cleaned = phone.replace(/\D/g, '');
    if (cleaned.startsWith('0049')) { cleaned = cleaned.substring(4); } 
    else if (cleaned.startsWith('49')) { cleaned = cleaned.substring(2); } 
    else if (cleaned.startsWith('0')) { cleaned = cleaned.substring(1); }

    if (cleaned.length < 3) return;
    input.value = '+49 ' + cleaned.substring(0, 3) + ' ' + cleaned.substring(3);
  }

  addBtn.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'contact-row';

    row.innerHTML = `
      <div><label>Name</label><input type="text" name="new_contacts[${contactIndex}][name]" placeholder="Vorname Nachname"></div>
      <div><label>Funktion / Rolle</label><input type="text" name="new_contacts[${contactIndex}][role]" placeholder="z.B. Geschäftsführer, Technik"></div>
      <div><label>E-Mail</label><input type="email" name="new_contacts[${contactIndex}][email]" placeholder="name@example.com"></div>
      <div><label>Telefon</label><input type="tel" name="new_contacts[${contactIndex}][phone]" placeholder="+49 123 456789"></div>
      <div><button type="button" class="btn-delete">X</button></div>
    `;

    row.querySelector('.btn-delete').addEventListener('click', () => {
      row.remove();
      setSaveStatus(false);
    });

    row.querySelector('input[type="tel"]').addEventListener('blur', function() {
      formatPhoneNumberInput(this);
    });

    container.appendChild(row);
    contactIndex++;
    setSaveStatus(false);
  });
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= $mapsKey ?>&libraries=places&callback=initAutocomplete" async defer></script>
</body>
</html>