<?php
require_once __DIR__ . '/session_handler.php';
check_auth();

$project_id = intval($_GET['id'] ?? 0);
if (!$project_id) {
    header('Location: crm.php');
    exit;
}

$dbPath = __DIR__ . '/data/rockets.db';

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: crm.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM interactions WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$project_id]);
$interactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projekt <?= htmlspecialchars($project['pnr'] ?? $project['id']) ?> — Revision100™</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { background: #f5f5f5; font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 16px; }
    .proj-back { display: inline-block; margin-bottom: 24px; font-size: 15px; text-decoration: none; color: #000; }
    .proj-header { font-family: monospace; font-size: 10px; letter-spacing: .2em; text-transform: uppercase; color: #888; margin-bottom: 4px; }
    .proj-title { font-size: 28px; font-weight: bold; margin-bottom: 24px; }
    .proj-section { border: 1px solid #000; margin-bottom: 24px; background: white; }
    .proj-section-head { padding: 12px 16px; border-bottom: 1px solid #000; background: #f5f5f5; font-family: monospace; font-size: 13px; letter-spacing: .2em; text-transform: uppercase; font-weight: 700; }
    .proj-section-body { padding: 16px; }
    .proj-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 16px; }
    .proj-row-label { color: #888; font-family: monospace; font-size: 13px; }
    .proj-interaction { margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #f0f0f0; }
    .proj-interaction-time { font-family: monospace; font-size: 12px; color: #c00; font-weight: 500; }
    .proj-interaction-note { margin-top: 6px; font-size: 15px; line-height: 1.5; }
    .btn-proj { padding: 12px 16px; text-align: center; font-size: 15px; border: 1px solid #000; background: white; cursor: pointer; font-weight: 500; width: 100%; box-sizing: border-box; }
    .btn-proj:hover { background: #f5f5f5; }
    textarea { width: 100%; padding: 10px; border: 1px solid #000; font-family: system-ui, sans-serif; font-size: 15px; resize: vertical; min-height: 60px; box-sizing: border-box; }
  </style>
</head>
<body style="background: #f5f5f5;">

<div style="max-width: 600px; margin: 0 auto;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;">
    <div>
      <div class="proj-header"><?= htmlspecialchars($project['pnr'] ?? 'KEIN PNR') ?> · <?= htmlspecialchars($project['id']) ?></div>
      <h1 class="proj-title"><?= htmlspecialchars($project['customer_name']) ?></h1>
    </div>
    <a href="crm.php" style="font-size:24px;color:#000;text-decoration:none;cursor:pointer;padding:8px;line-height:1;" title="Zurück zum CRM">✕</a>
  </div>

  <!-- KUNDENDATEN -->
  <div class="proj-section">
    <div class="proj-section-head">Kundendaten</div>
    <div class="proj-section-body">
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">Name / Firma</label>
        <input type="text" id="cName" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;box-sizing:border-box;" value="<?= htmlspecialchars($project['customer_name'] ?? '') ?>" placeholder="Firma oder Name">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">E-Mail</label>
          <input type="email" id="cEmail" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;box-sizing:border-box;" value="<?= htmlspecialchars($project['email'] ?? '') ?>" placeholder="kontakt@firma.de">
        </div>
        <div>
          <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">Telefon</label>
          <input type="tel" id="cPhone" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;box-sizing:border-box;" value="<?php echo htmlspecialchars($project['phone'] ?? '') ?>" placeholder="+49 30 ...">
        </div>
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">Website</label>
        <input type="url" id="cUrl" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;box-sizing:border-box;" value="<?= htmlspecialchars($project['target_url'] ?? '') ?>" placeholder="https://...">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">Stadt</label>
          <input type="text" id="cCity" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;box-sizing:border-box;" placeholder="Berlin">
        </div>
        <div>
          <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">PLZ</label>
          <input type="text" id="cPostal" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;box-sizing:border-box;" placeholder="10115">
        </div>
      </div>
      <button class="btn-proj" onclick="saveCustomerData()" style="background:#000;color:#fff;font-weight:700;padding:8px 12px;font-size:13px;">Save</button>
    </div>
  </div>

  <!-- PROJEKT -->
  <div class="proj-section">
    <div class="proj-section-head">Projekt</div>
    <div class="proj-section-body">
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">Fortschritt</label>
        <select id="projProgress" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;">
          <option value="anfrage|offen">01 · Anfrage</option>
          <option value="analyse|offen">02 · Analyse</option>
          <option value="kontakt|offen">03 · Kontakt</option>
          <option value="beauftragung|offen">04 · Beauftragung</option>
          <option value="umsetzung|in_arbeit">05 · Umsetzung</option>
          <option value="umsetzung|abgeschlossen">06 · Abgeschlossen</option>
        </select>
      </div>
      <div class="proj-row">
        <span class="proj-row-label">Betrag</span>
        <span><?= $project['betrag'] ? '€ ' . number_format($project['betrag'], 2, ',', '.') : '—' ?></span>
      </div>
      <button class="btn-proj" onclick="saveProject()" style="margin-top:12px;background:#000;color:#fff;font-weight:700;">💾 Speichern</button>
    </div>
  </div>

  <!-- LIGHTHOUSE ANALYSE -->
  <div class="proj-section">
    <div class="proj-section-head">⚡ Lighthouse Analyse</div>
    <div class="proj-section-body">
      <button class="btn-proj" id="lighthouseBtn" onclick="analyzeProject(<?= $project_id ?>)" style="margin-bottom:16px;background:#000;color:#fff;font-weight:700;">ANALYSE STARTEN</button>
      <div id="lighthouseResults" style="display:none;">
        <div class="proj-row">
          <span class="proj-row-label">Performance</span>
          <span id="lhPerf">—</span>
        </div>
        <div class="proj-row">
          <span class="proj-row-label">Accessibility</span>
          <span id="lhA11y">—</span>
        </div>
        <div class="proj-row">
          <span class="proj-row-label">Best Practices</span>
          <span id="lhBP">—</span>
        </div>
        <div class="proj-row">
          <span class="proj-row-label">SEO</span>
          <span id="lhSEO">—</span>
        </div>
      </div>
    </div>
  </div>

  <!-- DETAILS / METRIKEN -->
  <div class="proj-section">
    <div class="proj-section-head">Details (Metriken, Änderungen)</div>
    <div class="proj-section-body">
      <textarea id="projDetails" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;min-height:100px;box-sizing:border-box;"><?= htmlspecialchars($project['schutt_protokoll'] ?? '') ?></textarea>
      <button class="btn-proj" onclick="saveDetails()" style="margin-top:12px;">💾 Speichern</button>
    </div>
  </div>

  <!-- PERSONEN / KONTAKTE -->
  <div class="proj-section">
    <div class="proj-section-head">Personen</div>
    <div class="proj-section-body">
      <div id="contactsList">
        <?php if (empty($contacts)): ?>
          <p style="color: #888; font-size: 15px; margin: 0;">Keine Personen erfasst.</p>
        <?php else: ?>
          <?php foreach ($contacts as $contact): ?>
            <div style="padding:12px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:start;">
              <div>
                <div style="font-weight:bold;font-size:15px;"><?= htmlspecialchars($contact['name']) ?></div>
                <?php if ($contact['function']): ?>
                  <div style="color:#888;font-size:13px;"><?= htmlspecialchars($contact['function']) ?></div>
                <?php endif; ?>
                <?php if ($contact['email']): ?>
                  <div style="color:#0066cc;font-size:13px;"><?= htmlspecialchars($contact['email']) ?></div>
                <?php endif; ?>
                <?php if ($contact['phone']): ?>
                  <div style="color:#888;font-size:13px;"><?= htmlspecialchars($contact['phone']) ?></div>
                <?php endif; ?>
              </div>
              <div style="display:flex;gap:8px;">
                <button onclick="editContact(<?= $contact['id'] ?>, '<?= htmlspecialchars($contact['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($contact['function'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($contact['email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($contact['phone'] ?? '', ENT_QUOTES) ?>')" style="background:none;border:none;color:#666;cursor:pointer;font-size:16px;padding:0;">✎</button>
                <button onclick="deleteContact(<?= $contact['id'] ?>)" style="background:none;border:none;color:#c00;cursor:pointer;font-size:18px;padding:0;">✕</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0;">
        <input type="text" id="newContactName" placeholder="Name *" style="width:100%;padding:8px;border:1px solid #000;font-family:system-ui;font-size:13px;margin-bottom:8px;box-sizing:border-box;">
        <input type="text" id="newContactFunction" placeholder="Funktion" style="width:100%;padding:8px;border:1px solid #000;font-family:system-ui;font-size:13px;margin-bottom:8px;box-sizing:border-box;">
        <input type="email" id="newContactEmail" placeholder="E-Mail" style="width:100%;padding:8px;border:1px solid #000;font-family:system-ui;font-size:13px;margin-bottom:8px;box-sizing:border-box;">
        <input type="tel" id="newContactPhone" placeholder="Telefon" style="width:100%;padding:8px;border:1px solid #000;font-family:system-ui;font-size:13px;margin-bottom:8px;box-sizing:border-box;">
        <button onclick="addContact()" style="width:100%;padding:10px;border:1px solid #000;background:#fff;cursor:pointer;font-size:13px;font-weight:500;">+ Person hinzufügen</button>
      </div>
    </div>
  </div>

  <!-- KONTAKTHISTORIE -->
  <div class="proj-section">
    <div class="proj-section-head">Kontakthistorie</div>
    <div class="proj-section-body">
      <?php if (empty($interactions)): ?>
        <p style="color: #888; font-size: 15px; margin: 0;">Noch keine Einträge.</p>
      <?php else: ?>
        <?php foreach ($interactions as $inter): ?>
          <div class="proj-interaction">
            <div class="proj-interaction-time"><?= date('d.m.Y H:i', strtotime($inter['created_at'])) ?> (<?= htmlspecialchars($inter['type']) ?>)</div>
            <div class="proj-interaction-note"><?= nl2br(htmlspecialchars($inter['notes'])) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- NEUE NOTIZ -->
  <div class="proj-section">
    <div class="proj-section-head">+ Notiz hinzufügen</div>
    <div class="proj-section-body">
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">Art der Interaktion</label>
        <select id="interactionType" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;margin-bottom:12px;">
          <option value="notiz">Notiz</option>
          <option value="telefon">Telefonat</option>
          <option value="email">E-Mail (manuell)</option>
          <option value="meeting">Meeting</option>
          <option value="sms">SMS</option>
        </select>
      </div>
      <textarea id="newNote" placeholder="Kurze Notiz…"></textarea>
      <button class="btn-proj" onclick="addNote()" style="margin-top:12px;">Speichern</button>
    </div>
  </div>

  <!-- COM / EMAIL VERSENDEN -->
  <div class="proj-section">
    <div class="proj-section-head">📧 Mail versenden</div>
    <div class="proj-section-body">
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;">Template wählen:</label>
        <select id="emailTemplate" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;">
          <option value="">— Template auswählen —</option>
          <option value="onboarding_phase1">Onboarding Phase 1</option>
          <option value="eingang">Anfrage Eingang</option>
          <option value="diagnose_sanierbar">Diagnose: Sanierbar</option>
          <option value="diagnose_nicht_sanierbar">Diagnose: Nicht sanierbar</option>
          <option value="zahlung_bestaetigt">Zahlung bestätigt</option>
          <option value="projekt_gestartet">Projekt gestartet</option>
          <option value="abnahme">Abnahme / Fertig</option>
          <option value="rechnung">Rechnung</option>
        </select>
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:12px;color:#888;margin-bottom:6px;">Zusätzlicher Text (optional):</label>
        <textarea id="emailCustomText" style="width:100%;padding:10px;border:1px solid #000;font-family:system-ui;font-size:15px;min-height:60px;" placeholder="Zusätzlicher Text, der vor dem Template eingefügt wird…"></textarea>
      </div>
      <button class="btn-proj" onclick="sendEmail()">Versenden</button>
    </div>
  </div>

  <!-- AKTIONEN -->
  <div class="proj-section">
    <div class="proj-section-head">Aktionen</div>
    <div class="proj-section-body" style="display:flex;flex-direction:column;gap:8px;">
      <button class="btn-proj" onclick="deleteProject()">✕ Löschen</button>
    </div>
  </div>
</div>

<script>
const projectId = <?= (int)$project_id ?>;
const currentTunnel = '<?= htmlspecialchars($project['tunnel'] ?? 'sondierung') ?>';
const currentStatus = '<?= htmlspecialchars($project['project_status'] ?? 'offen') ?>';

// Initialize progress selector with current values
document.addEventListener('DOMContentLoaded', function() {
  const progressDropdown = document.getElementById('projProgress');
  if (progressDropdown) {
    progressDropdown.value = currentTunnel + '|' + currentStatus;
  }
});

async function saveProject() {
  const progressValue = document.getElementById('projProgress').value;
  const [tunnel, status] = progressValue.split('|');

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save',
        id: projectId,
        tunnel: tunnel,
        project_status: status
      })
    });
    const json = await res.json();
    if (json.success) {
      alert('Fortschritt aktualisiert.');
      location.reload();
    } else {
      alert('Fehler: ' + (json.error || 'Unbekannt'));
    }
  } catch (e) {
    alert('Fehler beim Speichern: ' + e.message);
  }
}

async function saveDetails() {
  const details = document.getElementById('projDetails').value.trim();

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save',
        id: projectId,
        schutt_protokoll: details
      })
    });
    const json = await res.json();
    if (json.success) {
      alert('Details aktualisiert.');
      location.reload();
    } else {
      alert('Fehler: ' + (json.error || 'Unbekannt'));
    }
  } catch (e) {
    alert('Fehler beim Speichern: ' + e.message);
  }
}

async function addNote() {
  const note = document.getElementById('newNote').value.trim();
  const type = document.getElementById('interactionType').value;
  if (!note) {
    alert('Bitte eine Notiz eingeben.');
    return;
  }

  try {
    const res = await fetch('api_interactions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ project_id: projectId, notes: note, type: type })
    });
    const json = await res.json();
    if (json.success) {
      document.getElementById('newNote').value = '';
      document.getElementById('interactionType').value = 'notiz';
      location.reload();
    } else {
      alert('Fehler: ' + (json.error || 'Unbekannt'));
    }
  } catch (e) {
    alert('Fehler beim Speichern.');
  }
}

function deleteProject() {
  if (!confirm('Wirklich löschen?')) return;
  fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'delete', id: projectId })
  }).then(() => window.location.href = 'crm.php');
}

async function sendEmail() {
  const template = document.getElementById('emailTemplate').value;
  const customText = document.getElementById('emailCustomText').value.trim();

  if (!template) {
    alert('Bitte ein Template auswählen.');
    return;
  }

  const templateLabels = {
    'onboarding_phase1': 'Onboarding Phase 1',
    'eingang': 'Anfrage Eingang',
    'diagnose_sanierbar': 'Diagnose: Sanierbar',
    'diagnose_nicht_sanierbar': 'Diagnose: Nicht sanierbar',
    'zahlung_bestaetigt': 'Zahlung bestätigt',
    'projekt_gestartet': 'Projekt gestartet',
    'abnahme': 'Abnahme / Fertig',
    'rechnung': 'Rechnung'
  };

  try {
    const res = await fetch('mailer.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: projectId, template: template, custom_text: customText })
    });
    const json = await res.json();
    if (json.success) {
      const now = new Date();
      const timestamp = now.toLocaleDateString('de-DE', {day:'2-digit',month:'2-digit',year:'2-digit'}) + ' ' +
                       now.toLocaleTimeString('de-DE', {hour:'2-digit',minute:'2-digit'});
      const protocolEntry = `${timestamp} (mail) ${templateLabels[template]}`;

      await fetch('api_interactions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_id: projectId, notes: protocolEntry, type: 'email' })
      });

      alert('Mail versendet und Protokoll aktualisiert.');
      document.getElementById('emailTemplate').value = '';
      document.getElementById('emailCustomText').value = '';
      location.reload();
    } else {
      alert('Fehler: ' + (json.error || 'Unbekannt'));
    }
  } catch (e) {
    alert('Fehler beim Versenden.');
  }
}

function formatPhoneNumber(phone) {
  if (!phone || typeof phone !== 'string') return '';

  let cleaned = phone.trim();
  if (!cleaned) return '';

  // Remove all non-digits except leading +
  cleaned = cleaned.replace(/[^\d+]/g, '');
  if (!cleaned) return '';

  // If it starts with 0, replace with +49
  if (cleaned.startsWith('0')) {
    cleaned = '+49' + cleaned.substring(1);
  }
  // If it's just digits without +, add +49
  else if (!cleaned.startsWith('+')) {
    cleaned = '+49' + cleaned;
  }

  return cleaned;
}

async function addContact() {
  const name = document.getElementById('newContactName').value.trim();
  const email = document.getElementById('newContactEmail').value.trim();
  let phone = document.getElementById('newContactPhone').value.trim();
  const func = document.getElementById('newContactFunction').value.trim();

  if (!name) {
    alert('Name erforderlich.');
    return;
  }

  // Format phone number
  phone = formatPhoneNumber(phone);

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'contact_add',
        customer_id: <?= (int)$project['customer_id'] ?>,
        name: name,
        email: email,
        phone: phone,
        function: func
      })
    });
    const json = await res.json();
    if (json.success) {
      alert('Person hinzugefügt.');
      document.getElementById('newContactName').value = '';
      document.getElementById('newContactEmail').value = '';
      document.getElementById('newContactPhone').value = '';
      document.getElementById('newContactFunction').value = '';
      location.reload();
    } else {
      alert('Fehler: ' + (json.error || 'Unbekannt'));
    }
  } catch (e) {
    alert('Fehler beim Hinzufügen.');
  }
}

async function deleteContact(contactId) {
  if (!confirm('Person wirklich löschen?')) return;

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'contact_delete', id: contactId })
    });
    const json = await res.json();
    if (json.success) {
      alert('Person gelöscht.');
      location.reload();
    } else {
      alert('Fehler: ' + (json.error || 'Unbekannt'));
    }
  } catch (e) {
    alert('Fehler beim Löschen.');
  }
}
</script>

<div id="editContactModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:white;border:1px solid #000;padding:24px;max-width:400px;width:90%;">
    <div style="font-size:16px;font-weight:bold;margin-bottom:20px;">Person bearbeiten</div>
    <input type="hidden" id="editContactId">
    <input type="text" id="editContactName" placeholder="Name *" style="width:100%;padding:8px;border:1px solid #000;margin-bottom:12px;box-sizing:border-box;">
    <input type="text" id="editContactFunction" placeholder="Funktion" style="width:100%;padding:8px;border:1px solid #000;margin-bottom:12px;box-sizing:border-box;">
    <input type="email" id="editContactEmail" placeholder="E-Mail" style="width:100%;padding:8px;border:1px solid #000;margin-bottom:12px;box-sizing:border-box;">
    <input type="tel" id="editContactPhone" placeholder="Telefon" style="width:100%;padding:8px;border:1px solid #000;margin-bottom:12px;box-sizing:border-box;">
    <div style="display:flex;gap:8px;">
      <button onclick="saveContact()" style="flex:1;padding:10px;border:1px solid #000;background:#000;color:white;cursor:pointer;font-weight:500;">Speichern</button>
      <button onclick="closeEditContact()" style="flex:1;padding:10px;border:1px solid #000;background:#fff;cursor:pointer;font-weight:500;">Abbrechen</button>
    </div>
  </div>
</div>

<script>
function editContact(id, name, func, email, phone) {
  document.getElementById('editContactId').value = id;
  document.getElementById('editContactName').value = name;
  document.getElementById('editContactFunction').value = func;
  document.getElementById('editContactEmail').value = email;
  document.getElementById('editContactPhone').value = phone;
  document.getElementById('editContactModal').style.display = 'flex';
}

function closeEditContact() {
  document.getElementById('editContactModal').style.display = 'none';
}

async function saveContact() {
  const id = document.getElementById('editContactId').value;
  const name = document.getElementById('editContactName').value.trim();
  if (!name) { alert('Name erforderlich'); return; }

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'contact_update',
        id,
        name,
        function: document.getElementById('editContactFunction').value.trim(),
        email: document.getElementById('editContactEmail').value.trim(),
        phone: document.getElementById('editContactPhone').value.trim()
      })
    });
    const json = await res.json();
    if (json.success) {
      location.reload();
    } else {
      alert('Fehler: ' + (json.error || 'Speichern fehlgeschlagen'));
    }
  } catch (e) {
    alert('Fehler: ' + e.message);
  }
}

async function saveCustomerData() {
  const name = document.getElementById('cName').value.trim();
  const email = document.getElementById('cEmail').value.trim();
  const phone = document.getElementById('cPhone').value.trim();
  const url = document.getElementById('cUrl').value.trim();

  if (!name || !url) {
    alert('Name und Website erforderlich');
    return;
  }

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save',
        id: <?= $project_id ?>,
        customer_name: name,
        email: email,
        phone: phone,
        target_url: url
      })
    });
    const json = await res.json();
    if (json.success) {
      alert('Kundendaten gespeichert');
    } else {
      alert('Fehler: ' + (json.error || 'Speichern fehlgeschlagen'));
    }
  } catch (e) {
    alert('Fehler: ' + e.message);
  }
}

async function analyzeProject(id) {
  const btn = document.getElementById('lighthouseBtn');
  btn.textContent = 'Analysiere…';
  btn.disabled = true;

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'analyze_url', id: id })
    });
    const json = await res.json();

    if (json.success && json.scores) {
      document.getElementById('lhPerf').textContent = json.scores.score_performance + '/100';
      document.getElementById('lhA11y').textContent = json.scores.score_accessibility + '/100';
      document.getElementById('lhBP').textContent = json.scores.score_best_practices + '/100';
      document.getElementById('lhSEO').textContent = json.scores.score_seo + '/100';
      document.getElementById('lighthouseResults').style.display = '';
      btn.textContent = '✓ FERTIG';
    } else {
      alert('Fehler: ' + (json.error || 'Analyse fehlgeschlagen'));
      btn.textContent = 'ANALYSE STARTEN';
      btn.disabled = false;
    }
  } catch (err) {
    alert('Verbindungsfehler: ' + err.message);
    btn.textContent = 'ANALYSE STARTEN';
    btn.disabled = false;
  }
}
</script>

</body>
</html>
