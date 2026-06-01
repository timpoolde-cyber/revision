<?php
require_once __DIR__ . '/session_handler.php';
check_auth();

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
    @media (max-width: 768px) {
      .header { padding: 8px 12px; }
      .status-squares { gap: 4px; margin-top: 6px; }
      .status-square { width: 22px; height: 22px; font-size: 8px; }
    }
    @media (max-width: 480px) {
      .header { padding: 8px; }
      .status-squares { gap: 3px; margin-top: 4px; }
      .status-square { width: 20px; height: 20px; font-size: 7px; }
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
        <label class="form-label">Rechnungsbetrag (netto)</label>
        <input type="number" id="netValue" class="form-input" placeholder="0,00" value="<?= htmlspecialchars($project['net_value'] ?? '') ?>" step="0.01" min="0">
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
    </div>

    <!-- Kontaktpersonen -->
    <div>
      <div class="section-title">Kontaktpersonen</div>
      <div class="contact-list" id="contactsList">
        <?php if (empty($contacts)): ?>
          <p style="color: #999; font-size: 12px;">Keine Kontakte definiert</p>
        <?php else: ?>
          <?php foreach ($contacts as $contact): ?>
            <div class="contact-item" id="contact-<?= $contact['id'] ?>">
              <div class="contact-info">
                <div class="contact-name"><?= htmlspecialchars($contact['name']) ?></div>
                <?php if ($contact['email']): ?>
                  <div class="contact-email">✉ <?= htmlspecialchars($contact['email']) ?></div>
                <?php endif; ?>
                <?php if ($contact['phone_mobile']): ?>
                  <div class="contact-phone">📱 <?= htmlspecialchars($contact['phone_mobile']) ?></div>
                <?php endif; ?>
                <?php if ($contact['is_default']): ?>
                  <div class="contact-default">★ Default</div>
                <?php endif; ?>
              </div>
              <div class="contact-actions">
                <input type="radio" name="defaultContact" value="<?= $contact['id'] ?>" <?= $contact['is_default'] ? 'checked' : '' ?> onchange="setDefaultContact(<?= $contact['id'] ?>)">
                <button class="btn btn-secondary" onclick="editContact(<?= $contact['id'] ?>)">Edit</button>
                <button class="btn btn-danger" onclick="deleteContact(<?= $contact['id'] ?>)">Delete</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Neue Kontaktperson -->
    <div>
      <div class="section-title">Neue Kontaktperson</div>
      <div class="add-contact-form">
        <div class="contact-form-fields">
          <div class="form-group">
            <label class="form-label">Name</label>
            <input type="text" id="newContactName" class="form-input" placeholder="Max Mustermann">
          </div>
          <div class="contact-form-row">
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" id="newContactEmail" class="form-input" placeholder="max@example.com">
            </div>
            <div class="form-group">
              <label class="form-label">Telefon</label>
              <input type="text" id="newContactPhone" class="form-input" placeholder="+49...">
            </div>
          </div>
        </div>
        <div class="add-contact-actions">
          <input type="radio" name="newContactDefault" id="newContactDefault">
          <button class="btn" onclick="addContact()">+ Hinzufügen</button>
        </div>
      </div>
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
    net_value: document.getElementById('netValue').value,
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
      showNotification('✓ Daten gespeichert', true);
    } else {
      alert('Fehler: ' + result.error);
    }
  } catch (error) {
    alert('Fehler beim Speichern: ' + error.message);
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

window.addEventListener('load', () => {
  initAutocomplete();
  renderPhaseSquares();
});
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= getenv('GOOGLE_MAPS_KEY') ?>&libraries=places" async defer></script>
</body>
</html>
