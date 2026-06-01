<!-- /Users/timpoolair/revision100/views/edit_data.tpl.php -->
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Projektdaten: <?= htmlspecialchars($project['customer_name']) ?></title>
  <style>
    :root { --font-mono: 'JetBrains Mono', monospace; --font-sans: 'Impact', sans-serif; }
    html { overflow-y: scroll; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #fff; margin: 0; padding: 0; color: #000; }
    
    header, .crm-layout, .container {
      max-width: 600px;
      width: 100%;
      margin-left: auto;
      margin-right: auto;
      box-sizing: border-box;
    }

    header { padding: 45px 16px 35px 16px; border-bottom: 1px solid #000; margin-bottom: 40px; display: block; }
    .brand { display: flex; align-items: center; gap: 16px; margin: 0; padding: 0; }
    .brand-name { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 32px; font-weight: 700; letter-spacing: -1px; line-height: 1.0; color: #000; margin: 0; padding: 0; display: inline-block; }
    
    .status-led { width: 12px; height: 12px; display: inline-block; background-color: #2ecc71; border: 1px solid #000; }
    .header-claim { font-family: monospace; font-size: 11px; color: #666; margin-top: 8px; text-transform: uppercase; letter-spacing: 0.5px; display: block; }
    
    /* VisionControl Balken */
    .vision-control-bar { display: flex; gap: 4px; margin-top: 12px; height: 12px; }
    .status-square { width: 22px; height: 22px; border: 1px solid #000; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; color: #fff; }
    
    .container { background: #fff; padding: 0; }
    .content { padding: 16px 20px; margin: 0; }
    
    .section-title { font-weight: bold; font-size: 13px; margin: 20px 0 12px 0; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 8px; }
    .form-group { margin-bottom: 12px; display: flex; flex-direction: column; gap: 4px; }
    .form-label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: bold; }
    .form-input { padding: 8px; border: 1px solid #000; font-family: var(--font-mono); font-size: 12px; box-sizing: border-box; width: 100%; }
    
    .btn { background: #000; color: #fff; border: 1px solid #000; padding: 10px 16px; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; cursor: pointer; }
    .btn:hover { background: #333; }
    .btn-secondary { background: #fff; color: #000; border: 1px solid #000; }
    .btn-secondary:hover { background: #f0f0f0; }
    .btn-danger { background: #ff3131; color: #fff; border: 1px solid #ff3131; }
    .btn-danger:hover { background: #cc2424; }
    
    .form-row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    
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

    @media (max-width: 768px) {
      header { padding: 25px 16px 19px 16px; margin-bottom: 22px; }
      .brand-name { font-size: 24px; }
      .header-claim { font-size: 10px; }
    }
  </style>
</head>
<body>

<header>
  <div class="brand"><span class="brand-name">Revision100™</span></div>
  <div id="statusSquares" style="display: flex; gap: 4px; margin-top: 12px; height: 12px;"></div>
</header>

<div class="crm-layout">
  <?php include __DIR__ . '/nav.tpl.php'; ?>
</div>

<div class="container">
  <div class="content">
    
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
  <!-- Die neue HTML-Komponente umschließt dein Input-Feld -->
  <gmpx-place-autocomplete>
    <input type="text" id="address" class="form-input" placeholder="Straße eingeben..." value="<?= htmlspecialchars($project['address'] ?? '') ?>">
  </gmpx-place-autocomplete>
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
    </div>

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
      <button type="button" id="addContactBtn" class="btn btn-secondary" style="width: 100%; border: 1px dashed #000; background: #fff; color: #000; margin-top: 8px;">+ Person hinzufügen</button>
    </div>
  </div>
</div>

<script>
const projectId = <?= $currentProjectId ?>;

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

// Moderner Handler für das neue HTML-Element ab 2025
function initAutocomplete() {
  const autocompleteEl = document.querySelector('gmpx-place-autocomplete');
  if (!autocompleteEl) return;

  // Beschränkung der Vorschläge auf Deutschland
  autocompleteEl.options = {
    componentRestrictions: { country: 'de' }
  };

  // Horcht auf das neue Google-Event 'gmpx-placechange'
  autocompleteEl.addEventListener('gmpx-placechange', () => {
    const place = autocompleteEl.value;
    if (!place || !place.addressComponents) return;

    let city = '', postalCode = '', route = '', streetNumber = '';

    // Extrahiert die Adressdaten aus der neuen Google-Struktur
    place.addressComponents.forEach(comp => {
      if (comp.types.includes('postal_code')) postalCode = comp.longText;
      if (comp.types.includes('locality')) city = comp.longText;
      if (comp.types.includes('route')) route = comp.longText;
      if (comp.types.includes('street_number')) streetNumber = comp.longText;
    });

    if (route) {
      const street = route + (streetNumber ? ' ' + streetNumber : '');
      document.getElementById('address').value = street;
    }
    if (city) document.getElementById('city').value = city;
    if (postalCode) document.getElementById('postalCode').value = postalCode;
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
  renderPhaseSquares();
});
</script>

<!-- Lädt die erweiterten Web Components mitsamt Places-Bibliothek asynchron -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= getenv('GOOGLE_MAPS_KEY') ?>&libraries=places&loading=async" defer></script>
<script type="module" src="https://ajax.googleapis.com/ajax/libs/javascript-extended-component-library/1.0.1/index.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', initAutocomplete);
  window.addEventListener('load', initAutocomplete);
</script>
</body>
</html>