<?php
require_once __DIR__ . '/session_handler.php';
check_auth();

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

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure project_contacts table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS project_contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        email TEXT,
        phone_mobile TEXT,
        is_default INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id)
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_contacts_project ON project_contacts(project_id, is_default)");
} catch (Exception $e) {
    error_log("Database table creation error: " . $e->getMessage());
}

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

$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? ORDER BY is_default DESC, name ASC");
$stmt->execute([$id]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Auto-create initial contact from customers if none exist
if (empty($contacts) && $project['customer_id']) {
    $stmt = $db->prepare("SELECT customer_name, email, phone_mobile FROM customers WHERE id = ?");
    $stmt->execute([$project['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        $stmt = $db->prepare("INSERT INTO project_contacts (project_id, name, email, phone_mobile, is_default) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$id, $customer['customer_name'], $customer['email'], $customer['phone_mobile']]);

        // Reload contacts
        $stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? ORDER BY is_default DESC, name ASC");
        $stmt->execute([$id]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Projektdaten: <?= htmlspecialchars($project['customer_name']) ?></title>
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
    .header-left-title { font-weight: bold; }
    .section-title { font-weight: bold; font-size: 13px; margin: 20px 0 12px 0; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 8px; }
    .form-group { margin-bottom: 12px; display: flex; flex-direction: column; gap: 4px; }
    .form-label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: bold; }
    .form-input { padding: 8px; border: 1px solid #000; font-family: var(--font-mono); font-size: 12px; box-sizing: border-box; }
    .btn { background: #000; color: #fff; border: 1px solid #000; padding: 10px 16px; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; cursor: pointer; }
    .btn:hover { background: #333; }
    .btn-secondary { background: #fff; color: #000; border: 1px solid #000; }
    .btn-secondary:hover { background: #f0f0f0; }
    .btn-danger { background: #ff3131; color: #fff; border: 1px solid #ff3131; }
    .btn-danger:hover { background: #cc2424; }
    .contact-list { margin-top: 12px; }
    .contact-item { border: 1px solid #000; padding: 12px; margin-bottom: 8px; display: flex; gap: 12px; align-items: flex-start; }
    .contact-info { flex: 1; }
    .contact-name { font-weight: bold; margin-bottom: 4px; }
    .contact-email { font-size: 12px; color: #666; }
    .contact-phone { font-size: 12px; color: #666; }
    .contact-default { font-size: 10px; color: #0d8659; font-weight: bold; margin-top: 4px; }
    .contact-actions { display: flex; gap: 8px; }
    .contact-actions button { padding: 6px 10px; font-size: 11px; min-width: auto; }
    .add-contact-form { display: flex; flex-direction: column; gap: 12px; border: 1px solid #000; padding: 12px; margin-top: 12px; background: #fafafa; }
    .add-contact-actions { display: flex; gap: 8px; align-items: center; justify-content: flex-end; }
    .add-contact-actions input[type="radio"] { margin: 0; cursor: pointer; }
    .add-contact-actions button { white-space: nowrap; flex-shrink: 0; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; }
    .form-row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .contact-form-fields { display: grid; grid-template-columns: 1fr; gap: 8px; }
    .contact-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .status-squares { display: flex; gap: 6px; margin-top: 8px; }
    .status-square { width: 24px; height: 24px; border-radius: 2px; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; color: #fff; border: 1px solid #000; }
    .contact-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 16px;
      box-sizing: border-box;
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      background: #fafafa;
    }
    .contact-row > div:nth-child(5),
    .contact-row > div:nth-child(6) {
      grid-column: auto;
    }
    .contact-row > div:nth-child(5) {
      justify-self: center;
    }
    .contact-row > div:nth-child(6) {
      justify-self: end;
    }
    .contact-row > div {
      display: flex;
      flex-direction: column;
      min-width: 0;
    }
    .contact-row input[type="text"],
    .contact-row input[type="email"],
    .contact-row input[type="tel"] {
      width: 100%;
      box-sizing: border-box;
      padding: 8px;
      font-size: 12px;
      border: 1px solid #ccc;
    }
    .contact-row input[type="radio"] {
      margin: 0;
      cursor: pointer;
      width: 16px;
      height: 16px;
    }
    .contact-row button {
      padding: 6px 10px;
      height: 34px;
      font-size: 11px;
    }
    @media (max-width: 768px) {
      header { padding: 25px 16px 19px 16px !important; margin-bottom: 22px !important; }
      .brand-name { font-size: 24px !important; }
      .header-claim { font-size: 10px !important; }
      .status-squares { gap: 4px; margin-top: 6px; }
      .status-square { width: 22px; height: 22px; font-size: 8px; }
    }
    @media (max-width: 480px) {
      .header { padding: 20px 16px 12px 16px !important; margin-bottom: 24px !important; }
      .brand-name { font-size: 24px !important; }
      .header-claim { font-size: 10px !important; }
      .status-squares { gap: 3px; margin-top: 4px; }
      .status-square { width: 20px; height: 20px; font-size: 7px; }
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
    <!-- Projektdaten -->
    <div>
      <div class="section-title">Projektdaten</div>
      <div class="form-group">
        <label class="form-label">Firma</label>
        <input type="text" id="customerName" class="form-input" value="<?= htmlspecialchars($project['customer_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">URL</label>
        <input type="text" id="targetUrl" class="form-input" value="<?= htmlspecialchars($project['target_url'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Unternehmensstandort (Auto-Complete)</label>
        <input type="text" id="address" class="form-input" placeholder="Straße eingeben..." value="<?= htmlspecialchars($project['address'] ?? '') ?>">
      </div>
      <input type="hidden" id="lat" value="">
      <input type="hidden" id="lng" value="">
      <div class="form-row-2col">
        <div class="form-group">
          <label class="form-label">Stadt</label>
          <input type="text" id="city" class="form-input" value="<?= htmlspecialchars($project['city'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">PLZ</label>
          <input type="text" id="postalCode" class="form-input" value="<?= htmlspecialchars($project['postal_code'] ?? '') ?>">
        </div>
      </div>
      <button class="btn" onclick="saveProjectData()">Speichern</button>

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
    </div>

    <!-- Projektkontakte / Ansprechpartner -->
    <div>
      <div class="section-title">Projektkontakte / Ansprechpartner</div>

      <div id="contactsGrid">
        <?php if (!empty($contacts)): ?>
          <?php foreach ($contacts as $contact): ?>
            <div class="contact-row">
              <div>
                <label class="form-label" style="margin-bottom: 4px;">Name</label>
                <input type="text" name="contacts[<?= $contact['id'] ?>][name]" value="<?= htmlspecialchars($contact['name'] ?? '') ?>" placeholder="Name">
              </div>
              <div>
                <label class="form-label" style="margin-bottom: 4px;">Rolle</label>
                <input type="text" name="contacts[<?= $contact['id'] ?>][role]" value="<?= htmlspecialchars($contact['role'] ?? '') ?>" placeholder="z.B. Technik">
              </div>
              <div>
                <label class="form-label" style="margin-bottom: 4px;">E-Mail</label>
                <input type="email" name="contacts[<?= $contact['id'] ?>][email]" value="<?= htmlspecialchars($contact['email'] ?? '') ?>" placeholder="mail@example.com">
              </div>
              <div>
                <label class="form-label" style="margin-bottom: 4px;">Telefon</label>
                <input type="tel" name="contacts[<?= $contact['id'] ?>][phone]" class="phone-input" value="<?= htmlspecialchars($contact['phone_mobile'] ?? '') ?>" placeholder="+49...">
              </div>
              <div style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-height: 50px;">
                <label class="form-label" style="margin-bottom: 8px; font-size: 10px;">DEFAULT</label>
                <input type="radio" name="default_contact" value="<?= $contact['id'] ?>" <?= $contact['is_default'] ? 'checked' : '' ?> style="margin-bottom: 12px;">
              </div>
              <div style="display: flex; align-items: flex-end; justify-content: flex-end; height: 100%;">
                <button type="button" class="btn btn-danger" onclick="deleteContactRow(this)">X</button>
              </div>
              <input type="hidden" name="contacts[<?= $contact['id'] ?>][id]" value="<?= $contact['id'] ?>">
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <button type="button" id="addContactBtn" class="btn btn-secondary" style="width: 100%; border: 1px dashed #000; background: #fff; color: #000;">+ Person hinzufügen</button>
    </div>
  </div>
</div>

<script>
const projectId = <?= $id ?>;

function formatPhoneNumberJS(phone) {
  if (!phone) return '';
  phone = phone.replace(/[^0-9+]/g, '');

  if (phone.startsWith('+')) {
    return phone;
  }

  if (phone.startsWith('0')) {
    phone = '+49' + phone.substring(1);
  } else if (phone.length === 11 && phone[0] === '1') {
    phone = '+49' + phone;
  } else if (phone.length === 10 && /^\d+$/.test(phone)) {
    phone = '+49' + phone;
  }

  return phone;
}

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

async function saveProjectData() {
  const data = {
    action: 'save_project_data',
    project_id: projectId,
    customer_name: document.getElementById('customerName').value,
    target_url: document.getElementById('targetUrl').value,
    address: document.getElementById('address').value,
    city: document.getElementById('city').value,
    postal_code: document.getElementById('postalCode').value
  };

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });

    const result = await response.json();
    if (result.success) {
      showNotification('✓ Projektdaten gespeichert', true);
      // Speichere auch die Kontakte
      await saveAllContacts();
    } else {
      alert('Fehler: ' + result.error);
    }
  } catch (error) {
    alert('Fehler beim Speichern: ' + error.message);
  }
}

async function saveAllContacts() {
  const contactRows = document.querySelectorAll('.contact-row');
  const contacts = {};
  const deletedIds = [];
  const defaultContactRadio = document.querySelector('input[name="default_contact"]:checked');
  const defaultContactId = defaultContactRadio ? defaultContactRadio.value : null;

  // Sammle alle Kontaktdaten aus den Zeilen
  contactRows.forEach((row, index) => {
    const nameInput = row.querySelector('input[name*="[name]"]');
    const roleInput = row.querySelector('input[name*="[role]"]');
    const emailInput = row.querySelector('input[name*="[email]"]');
    const phoneInput = row.querySelector('input[name*="[phone]"]');
    const idInput = row.querySelector('input[name*="[id]"]');

    if (nameInput) {
      const key = idInput ? idInput.value : 'new_' + index;
      contacts[key] = {
        name: nameInput.value.trim(),
        role: roleInput ? roleInput.value.trim() : '',
        email: emailInput ? emailInput.value.trim() : '',
        phone: phoneInput ? phoneInput.value.trim() : ''
      };
    }
  });

  // Sammle gelöschte Kontakte
  document.querySelectorAll('input[name="deleted_contacts[]"]').forEach(input => {
    deletedIds.push(input.value);
  });

  if (Object.keys(contacts).length === 0 && deletedIds.length === 0) {
    return; // Keine Änderungen
  }

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_project_contacts',
        project_id: projectId,
        contacts: contacts,
        deleted_ids: deletedIds,
        default_contact_id: defaultContactId
      })
    });

    const result = await response.json();
    if (result.success) {
      showNotification('✓ Kontakte gespeichert', true);
      // Entferne die Delete-Marker
      document.querySelectorAll('input[name="deleted_contacts[]"]').forEach(input => input.remove());
    } else {
      alert('Fehler beim Speichern der Kontakte: ' + result.error);
    }
  } catch (error) {
    console.error('Fehler beim Speichern der Kontakte:', error);
    // Nicht als kritischer Fehler behandeln - Projektdaten wurden bereits gespeichert
  }
}

async function addContact() {
  const name = document.getElementById('newContactName').value.trim();
  const email = document.getElementById('newContactEmail').value.trim();
  let phone = document.getElementById('newContactPhone').value.trim();
  const isDefault = document.getElementById('newContactDefault').checked;

  if (!name) {
    alert('Bitte geben Sie einen Namen ein.');
    return;
  }

  // Format phone number before sending
  phone = formatPhoneNumberJS(phone);

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'add_project_contact',
        project_id: projectId,
        name: name,
        email: email,
        phone_mobile: phone
      })
    });

    const result = await response.json();
    if (result.success) {
      // Wenn als Default markiert, setze ihn direkt als Default
      if (isDefault) {
        const setDefaultResponse = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'set_default_contact',
            project_id: projectId,
            contact_id: result.contact_id
          })
        });
      }

      showNotification('✓ Kontakt hinzugefügt', true);
      document.getElementById('newContactName').value = '';
      document.getElementById('newContactEmail').value = '';
      document.getElementById('newContactPhone').value = '';
      document.getElementById('newContactDefault').checked = false;
      setTimeout(() => location.reload(), 1000);
    } else {
      alert('Fehler: ' + result.error);
    }
  } catch (error) {
    alert('Fehler beim Hinzufügen: ' + error.message);
  }
}

async function deleteContact(contactId) {
  if (!confirm('Kontakt wirklich löschen?')) return;

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'delete_project_contact',
        contact_id: contactId
      })
    });

    const result = await response.json();
    if (result.success) {
      showNotification('✓ Kontakt gelöscht', true);
      setTimeout(() => location.reload(), 1000);
    } else {
      alert('Fehler: ' + result.error);
    }
  } catch (error) {
    alert('Fehler beim Löschen: ' + error.message);
  }
}

async function setDefaultContact(contactId) {
  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'set_default_contact',
        project_id: projectId,
        contact_id: contactId
      })
    });

    const result = await response.json();
    if (result.success) {
      showNotification('✓ Default-Kontakt gesetzt', true);
      setTimeout(() => location.reload(), 1000);
    } else {
      alert('Fehler: ' + result.error);
    }
  } catch (error) {
    alert('Fehler: ' + error.message);
  }
}

async function editContact(contactId) {
  const contactItem = document.getElementById('contact-' + contactId);
  if (!contactItem) return;

  // Get current data from the DOM
  const nameElement = contactItem.querySelector('.contact-name');
  const emailElement = contactItem.querySelector('.contact-email');
  const phoneElement = contactItem.querySelector('.contact-phone');

  const currentName = nameElement.textContent.trim();
  const currentEmail = emailElement ? emailElement.textContent.replace('✉ ', '').trim() : '';
  const currentPhone = phoneElement ? phoneElement.textContent.replace('📱 ', '').trim() : '';

  // Create edit form
  const editForm = document.createElement('div');
  editForm.className = 'contact-item';
  editForm.innerHTML = `
    <div class="contact-info">
      <div style="margin-bottom: 8px;">
        <label class="form-label">Name</label>
        <input type="text" id="editName-${contactId}" class="form-input" value="${htmlEscape(currentName)}" style="width: 100%;">
      </div>
      <div style="margin-bottom: 8px;">
        <label class="form-label">Email</label>
        <input type="email" id="editEmail-${contactId}" class="form-input" value="${htmlEscape(currentEmail)}" style="width: 100%;">
      </div>
      <div style="margin-bottom: 8px;">
        <label class="form-label">Telefon</label>
        <input type="text" id="editPhone-${contactId}" class="form-input" value="${htmlEscape(currentPhone)}" style="width: 100%;">
      </div>
    </div>
    <div class="contact-actions">
      <button class="btn" onclick="saveEditContact(${contactId})">Speichern</button>
      <button class="btn btn-secondary" onclick="cancelEditContact(${contactId})">Abbrechen</button>
    </div>
  `;

  // Replace the current item with the form
  contactItem.replaceWith(editForm);
  document.getElementById('editName-' + contactId).focus();
}

function htmlEscape(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

async function saveEditContact(contactId) {
  const name = document.getElementById('editName-' + contactId).value.trim();
  const email = document.getElementById('editEmail-' + contactId).value.trim();
  let phone = document.getElementById('editPhone-' + contactId).value.trim();

  if (!name) {
    alert('Bitte geben Sie einen Namen ein.');
    return;
  }

  // Format phone number before sending
  phone = formatPhoneNumberJS(phone);

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_project_contact',
        contact_id: contactId,
        name: name,
        email: email,
        phone_mobile: phone
      })
    });

    const result = await response.json();
    if (result.success) {
      showNotification('✓ Kontakt aktualisiert', true);
      setTimeout(() => location.reload(), 1000);
    } else {
      alert('Fehler: ' + result.error);
    }
  } catch (error) {
    alert('Fehler beim Speichern: ' + error.message);
  }
}

function cancelEditContact(contactId) {
  location.reload();
}

function showNotification(message, success = true) {
  const notif = document.createElement('div');
  notif.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:' + (success ? '#0d8659' : '#ff3131') + ';color:#fff;padding:20px 40px;border-radius:4px;font-weight:bold;z-index:9999;font-family:var(--font-mono);';
  notif.textContent = message;
  document.body.appendChild(notif);
  setTimeout(() => notif.remove(), 3000);
}

function initAutocomplete() {
  const input = document.getElementById('address');
  if (!input || typeof google === 'undefined' || !google.maps) return;

  try {
    const autocomplete = new google.maps.places.Autocomplete(input, {
      componentRestrictions: { country: 'de' }
    });

    autocomplete.addListener('place_changed', function() {
      const place = autocomplete.getPlace();
      if (!place.geometry) return;

      document.getElementById('lat').value = place.geometry.location.lat();
      document.getElementById('lng').value = place.geometry.location.lng();

      // Extract city and postal code from address components
      if (place.address_components) {
        let city = '', postalCode = '';
        place.address_components.forEach(comp => {
          if (comp.types.includes('postal_code')) {
            postalCode = comp.long_name;
          }
          if (comp.types.includes('locality') || comp.types.includes('postal_town')) {
            city = comp.long_name;
          }
        });
        if (city) document.getElementById('city').value = city;
        if (postalCode) document.getElementById('postalCode').value = postalCode;
      }

      // Use formatted_address but remove country if it's Germany
      if (place.formatted_address) {
        let addr = place.formatted_address;
        addr = addr.replace(/, Deutschland$/, '').replace(/Germany$/, '');
        document.getElementById('address').value = addr;
      }
    });
  } catch (e) {
    console.error('Autocomplete initialization failed:', e);
  }
}

// Contact Management
const contactsGrid = document.getElementById('contactsGrid');
const addContactBtn = document.getElementById('addContactBtn');
let newContactIndex = 0;

function formatPhoneNumberInputField(input) {
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

function deleteContactRow(button) {
  const row = button.closest('.contact-row');
  const idInput = row.querySelector('input[name*="[id]"]');

  if (idInput && idInput.value) {
    // Bestehender Kontakt - mit verstecktem Input markieren
    const deleteMarkerInput = document.createElement('input');
    deleteMarkerInput.type = 'hidden';
    deleteMarkerInput.name = 'deleted_contacts[]';
    deleteMarkerInput.value = idInput.value;
    row.parentElement.appendChild(deleteMarkerInput);
  }

  row.remove();
}

addContactBtn.addEventListener('click', () => {
  const tempId = 'new_' + newContactIndex;

  const row = document.createElement('div');
  row.className = 'contact-row';

  row.innerHTML = `
    <div>
      <label class="form-label" style="margin-bottom: 4px;">Name</label>
      <input type="text" name="contacts[${tempId}][name]" placeholder="Name">
    </div>
    <div>
      <label class="form-label" style="margin-bottom: 4px;">Rolle</label>
      <input type="text" name="contacts[${tempId}][role]" placeholder="z.B. Technik">
    </div>
    <div>
      <label class="form-label" style="margin-bottom: 4px;">E-Mail</label>
      <input type="email" name="contacts[${tempId}][email]" placeholder="mail@example.com">
    </div>
    <div>
      <label class="form-label" style="margin-bottom: 4px;">Telefon</label>
      <input type="tel" name="contacts[${tempId}][phone]" class="phone-input" placeholder="+49...">
    </div>
    <div style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-height: 50px;">
      <label class="form-label" style="margin-bottom: 8px; font-size: 10px;">DEFAULT</label>
      <input type="radio" name="default_contact" value="${tempId}" style="margin-bottom: 12px;">
    </div>
    <div style="display: flex; align-items: flex-end; justify-content: flex-end; height: 100%;">
      <button type="button" class="btn btn-danger">X</button>
    </div>
  `;

  const deleteBtn = row.querySelector('.btn-danger');
  deleteBtn.addEventListener('click', () => deleteContactRow(deleteBtn));

  const phoneInput = row.querySelector('.phone-input');
  phoneInput.addEventListener('blur', function() {
    formatPhoneNumberInputField(this);
  });

  contactsGrid.appendChild(row);
  newContactIndex++;
});

// Format phone on blur for existing contacts
document.querySelectorAll('.phone-input').forEach(input => {
  input.addEventListener('blur', function() {
    formatPhoneNumberInputField(this);
  });
});

// Event-Listener für Default-Kontakt-Auswahl
document.querySelectorAll('input[name="default_contact"]').forEach(radio => {
  radio.addEventListener('change', async () => {
    // Speichere Kontakte und lade Seite neu
    await saveAllContacts();
    setTimeout(() => location.reload(), 500);
  });
});

window.addEventListener('load', () => {
  initAutocomplete();
  renderPhaseSquares();
});
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= getenv('GOOGLE_MAPS_KEY') ?>&libraries=places" async defer></script>
</body>
</html>
