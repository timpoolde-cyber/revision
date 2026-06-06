<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Projektdaten: <?= htmlspecialchars($project['customer_name']) ?></title>
  <link rel="stylesheet" href="style-crm.css">
  <script src="../crm-functions.js"></script>
  <style>
    .btn { background: #000; color: #fff; border: 1px solid #000; padding: 10px 16px; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; cursor: pointer; }
    .btn:hover { background: #333; }
    .btn-secondary { background: #fff; color: #000; border: 1px solid #000; }
    .btn-secondary:hover { background: #f0f0f0; }
    .btn-danger { background: #ff3131; color: #fff; border: 1px solid #ff3131; }
    .btn-danger:hover { background: #cc2424; }

    .contact-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 16px;
      box-sizing: border-box;
      width: 100%;
      padding: 12px;
      border: 1px solid #000;
      background: #fafafa;
    }
    .contact-row > div { display: flex; flex-direction: column; min-width: 0; }
    .contact-row input[type="text"],
    .contact-row input[type="email"],
    .contact-row input[type="tel"] {
      width: 100%;
      box-sizing: border-box;
      padding: 8px;
      font-size: 12px;
      border: 1px solid #000;
      font-family: var(--font-mono);
    }
    .contact-row input[type="radio"] { margin: 0; cursor: pointer; width: 16px; height: 16px; }
    .contact-row button { padding: 6px 10px; height: 34px; font-size: 11px; }

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

  </style>
</head>
<body>

<header>
  <div class="brand-container">
    <div class="brand"><span class="brand-name">Revision100™</span></div>
    <div id="statusSquares" class="status-squares"></div>
  </div>
</header>

<div class="crm-layout">
  <?php include __DIR__ . '/nav.tpl.php'; ?>
</div>

<div class="container">
  <div class="content">

    <div class="section-title">Projektdaten</div>
      
      <div class="form-group" style="background: #fafafa; padding: 14px; border: 1px dashed #000; margin-bottom: 24px;">
        <label class="form-label" style="color: #000; display: flex; align-items: center; gap: 6px;">
          ⚡ Google Firmensuche (Ausfüllhilfe)
        </label>
        <input type="text" id="googleSearchField" class="form-input" placeholder="Unternehmen tippen für Auto-Fill..." autocomplete="off" style="background: #fff; font-size: 14px; padding: 10px;">
      </div>

      <div class="form-group">
        <label class="form-label">Firma / Kundenname</label>
        <input type="text" id="customerName" class="form-input" value="<?= htmlspecialchars($project['customer_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">URL</label>
        <input type="text" id="targetUrl" class="form-input" value="<?= htmlspecialchars($project['target_url'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Straße & Hausnummer</label>
        <input type="text" id="address" class="form-input" placeholder="Straße" value="<?= htmlspecialchars($project['address'] ?? '') ?>">
      </div>
      <input type="hidden" id="lat" value="">
      <input type="hidden" id="lng" value="">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%;">
        <div class="form-group">
          <label class="form-label">Stadt</label>
          <input type="text" id="city" class="form-input" value="<?= htmlspecialchars($project['city'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">PLZ</label>
          <input type="text" id="postalCode" class="form-input" value="<?= htmlspecialchars($project['postal_code'] ?? '') ?>">
        </div>
      </div>
      <button class="btn" style="width: 100%; margin-top: 8px;" onclick="saveProjectData()">Speichern</button>

      <?php if (!empty($project['secret_token'])):
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
      <button type="button" id="addContactBtn" class="btn btn-secondary" style="width: 100%; border: 1px dashed #000; background: #fff; color: #000; margin-top: 8px;">+ Person hinzufügen</button>
    </div>
  </div>
</div>

<script>
const projectId = <?= $currentProjectId ?>;

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

  document.querySelectorAll('input[name="deleted_contacts[]"]').forEach(input => {
    deletedIds.push(input.value);
  });

  try {
    await fetch('api.php', {
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
    showNotification('✓ Kontakte gespeichert', true);
    setTimeout(() => location.reload(), 800);
  } catch (error) {
    console.error(error);
  }
}

function showNotification(message, success = true) {
  const notif = document.createElement('div');
  notif.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:' + (success ? '#0d8659' : '#ff3131') + ';color:#fff;padding:20px 40px;border-radius:4px;font-weight:bold;z-index:9999;font-family:var(--font-mono);';
  notif.textContent = message;
  document.body.appendChild(notif);
  setTimeout(() => notif.remove(), 2000);
}

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

    setTimeout(() => { input.value = ''; }, 50);
  });
}

function deleteContactRow(button) {
  const row = button.closest('.contact-row');
  const idInput = row.querySelector('input[name*="[id]"]');
  if (idInput && idInput.value) {
    const deleteMarkerInput = document.createElement('input');
    deleteMarkerInput.type = 'hidden';
    deleteMarkerInput.name = 'deleted_contacts[]';
    deleteMarkerInput.value = idInput.value;
    row.parentElement.appendChild(deleteMarkerInput);
  }
  row.remove();
}

function formatPhoneNumberInputField(input) {
  let value = input.value.trim();
  if (!value) return;
  let cleaned = value.replace(/[^0-9]/g, '');
  if (cleaned.startsWith('0049')) cleaned = cleaned.substring(4);
  else if (cleaned.startsWith('49')) cleaned = cleaned.substring(2);
  else if (cleaned.startsWith('0')) cleaned = cleaned.substring(1);
  if (cleaned.length < 3) return;
  input.value = '+49 ' + cleaned.substring(0, 4) + ' ' + cleaned.substring(4);
}

document.getElementById('addContactBtn').addEventListener('click', () => {
  const tempId = 'new_' + Math.random().toString(36).substr(2, 9);
  const row = document.createElement('div');
  row.className = 'contact-row';
  row.innerHTML = `
    <div><label class="form-label">Name</label><input type="text" name="contacts[\${tempId}][name]" placeholder="Name"></div>
    <div><label class="form-label">Rolle</label><input type="text" name="contacts[\${tempId}][role]" placeholder="Technik"></div>
    <div><label class="form-label">E-Mail</label><input type="email" name="contacts[\${tempId}][email]" placeholder="mail@example.com"></div>
    <div><label class="form-label">Telefon</label><input type="tel" name="contacts[\${tempId}][phone]" class="phone-input" placeholder="+49..."></div>
    <div style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-height: 50px;">
      <label class="form-label" style="font-size: 10px;">DEFAULT</label>
      <input type="radio" name="default_contact" value="\${tempId}" style="margin-bottom: 12px;">
    </div>
    <div style="display: flex; align-items: flex-end; justify-content: flex-end; height: 100%;"><button type="button" class="btn btn-danger" onclick="deleteContactRow(this)">X</button></div>
  `;
  row.querySelector('.phone-input').addEventListener('blur', function() { formatPhoneNumberInputField(this); });
  document.getElementById('contactsGrid').appendChild(row);
});

document.querySelectorAll('.phone-input').forEach(input => {
  input.addEventListener('blur', function() { formatPhoneNumberInputField(this); });
});

window.addEventListener('DOMContentLoaded', () => {
  const currentPhase = '<?= htmlspecialchars($project['tunnel']) ?>';
  const lastInteractionDate = '<?= !empty($project['last_interaction_date']) ? htmlspecialchars($project['last_interaction_date']) : '' ?>';
  const container = document.getElementById('statusSquares');
  if (container) {
    container.innerHTML = window.renderPhaseSquares(currentPhase, lastInteractionDate).html;
  }
});
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= $googleMapsKey ?>&libraries=places&callback=initAutocomplete" async defer></script>
</body>
</html>