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
  <title>Email: <?= htmlspecialchars($project['customer_name']) ?></title>
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
    .header-left-title { font-weight: bold; }
    .header-left-url { color: #0066cc; text-decoration: underline; cursor: pointer; }
    .status-squares { display: flex; gap: 6px; margin-top: 8px; }
    .status-square { width: 24px; height: 24px; border-radius: 2px; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; color: #fff; border: 1px solid #000; }
    .container { width: 100%; margin: 0; background: #fff; padding: 0; box-sizing: border-box; overflow-x: hidden; }
    .content { padding: 16px 20px; margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 32px; min-width: 0; }
    .content > div { min-width: 0; }
    .meta-info { margin-bottom: 12px; font-size: 14px; }
    .score-badge { width: 32px; height: 32px; border: 1px solid #000; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; color: #fff; margin-bottom: 8px; }
    .section-title { font-weight: bold; font-size: 13px; margin-bottom: 12px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 8px; }
    .checkbox-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .checkbox-item { display: flex; align-items: flex-start; gap: 8px; }
    .checkbox-item input { margin-top: 4px; cursor: pointer; }
    .checkbox-item label { flex: 1; font-size: 12px; cursor: pointer; word-wrap: break-word; word-break: break-word; overflow-wrap: break-word; }
    .preview-box { border: 1px solid #000; padding: 16px; background: #fafafa; font-size: 12px; line-height: 1.6; max-height: 600px; overflow-y: auto; overflow-x: hidden; word-wrap: break-word; word-break: break-word; width: 100%; box-sizing: border-box; }
    .btn-send { background: #000; color: #fff; border: 1px solid #000; padding: 12px 20px; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; cursor: pointer; margin-top: 16px; width: 100%; }
    .btn-send:hover { background: #333; }
    @media (max-width: 768px) {
      header { padding: 25px 16px 19px 16px !important; margin-bottom: 22px !important; }
      .brand-name { font-size: 24px !important; }
      .status-squares { gap: 4px; margin-top: 6px; }
      .status-square { width: 22px; height: 22px; font-size: 8px; }
      .content { grid-template-columns: 1fr; padding: 12px 12px; }
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
    <div>
      <div style="margin-bottom: 16px;">
        <div style="display: flex; gap: 8px;">
          <select id="templateSelect" style="flex: 1; padding: 8px; border: 1px solid #000; font-family: 'JetBrains Mono', monospace; font-size: 12px; box-sizing: border-box; min-width: 0;">
            <option value="">-- Vorlage auswählen --</option>
          </select>
          <button type="button" style="background: #000; color: #fff; border: 1px solid #000; padding: 8px 12px; font-family: 'JetBrains Mono', monospace; font-weight: bold; text-transform: uppercase; cursor: pointer;" onclick="saveTemplate()">Speichern</button>
          <button type="button" style="background: #ff3131; color: #fff; border: 1px solid #ff3131; padding: 8px 12px; font-family: 'JetBrains Mono', monospace; font-weight: bold; text-transform: uppercase; cursor: pointer;" onclick="deleteSelectedTemplate()" id="deleteBtn" disabled>Löschen</button>
        </div>
      </div>

      <div class="checkbox-group">
        <div class="checkbox-item">
          <input type="checkbox" id="cbSalutation" checked>
          <label for="cbSalutation">Anrede</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="cbScore" checked>
          <label for="cbScore">Score-Erklärung</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="cbAction" checked>
          <label for="cbAction">CTA 1</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="cbPhone">
          <label for="cbPhone">Telefonangebot</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="cbToken">
          <label for="cbToken">Token einbinden</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="cbSignatureLong">
          <label for="cbSignatureLong">Gruß lang</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="cbSignatureShort">
          <label for="cbSignatureShort">Gruß kurz</label>
        </div>
      </div>
      <div style="display: flex; gap: 8px; margin-top: 12px;">
        <button class="btn-send" style="flex: 1; background: #0066cc;" onclick="window.open('psi_pdf_generator.php?id=<?= $id ?>', '_blank'); return false;">📊 PSI-Report</button>
        <button class="btn-send" style="flex: 1; background: #0066cc;" onclick="window.open('psi_history_pdf_generator.php?id=<?= $id ?>', '_blank'); return false;">📈 PSI-Historie</button>
        <button class="btn-send" style="flex: 1; background: #0066cc;" onclick="window.open('psi_audit_pdf_generator.php?id=<?= $id ?>', '_blank'); return false;">🔍 PSI-Audit</button>
      </div>

    </div>

    <div>
      <input type="text" id="emailSubject" placeholder="Betreff" style="width: 100%; padding: 8px; border: 1px solid #000; font-family: 'JetBrains Mono', monospace; font-size: 12px; box-sizing: border-box; margin-bottom: 8px;">
      <div style="display: flex; gap: 4px; margin-bottom: 8px; align-items: center;">
        <button type="button" style="width: 36px; height: 36px; background: #000; color: #fff; border: 1px solid #000; font-weight: bold; cursor: pointer; font-family: var(--font-mono);" onclick="document.execCommand('bold', false, null);">B</button>
        <button type="button" style="width: 36px; height: 36px; background: #000; color: #fff; border: 1px solid #000; font-style: italic; cursor: pointer; font-family: var(--font-mono);" onclick="document.execCommand('italic', false, null);">I</button>
        <button type="button" style="width: 36px; height: 36px; background: #000; color: #fff; border: 1px solid #000; text-decoration: underline; cursor: pointer; font-family: var(--font-mono);" onclick="document.execCommand('underline', false, null);">U</button>
        <button style="background: #000; color: #fff; border: 1px solid #000; padding: 8px 12px; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; cursor: pointer; margin-left: auto; font-size: 12px;" onclick="sendEmail()">Send</button>
      </div>
      <div class="preview-box" id="preview" contenteditable="true" style="cursor: text; white-space: pre-line; word-wrap: break-word; overflow-wrap: break-word;">Vorschau wird geladen...</div>
    </div>
  </div>
</div>

<script>
const projectData = {
  id: <?= $id ?>,
  customer_name: '<?= htmlspecialchars(addslashes($project['customer_name'])) ?>',
  email: '<?= htmlspecialchars(addslashes($project['email'])) ?>',
  last_score: <?= $project['last_score'] ?? 'null' ?>,
  secret_token: '<?= htmlspecialchars(addslashes($project['secret_token'] ?? '')) ?>'
};

function generateSalutation(input) {
  const cleanInput = input.trim();
  const companyPatterns = [
    /\b(gmbh|ag|ug|gbr|kg|gmbh\s*&\s*co\s*\.?\s*kg|e\.?\s*k\.?)\b/i,
    /\b(studio|werkstatt|agentur|bureau|shop|store|systeme|vertrieb)\b/i,
    /&/
  ];
  const isCompany = companyPatterns.some(pattern => pattern.test(cleanInput));

  if (isCompany) {
    return `Sehr geehrte Damen und Herren,\n\nvielen Dank für das Interesse an der Code-Revision für ${cleanInput}.`;
  } else {
    return `Guten Tag ${cleanInput},\n\nvielen Dank für Ihre Anfrage.`;
  }
}

const templates = {
  score: `Ein Lighthouse-Score zeigt nur das Symptom, nicht die Ursache.

Ein automatisierter Messwert liefert keinen Fahrplan. Um die exakten Bremsklötze in Ihrem Quelltext zu finden und die Performance auf ein Maximum zu bringen, ist eine manuelle Tiefenanalyse notwendig.

Im Rahmen meiner Code- und Score-Revision zerlege ich Ihre Seitenarchitektur. Lassen Sie uns in einem kurzen, 10-minütigen Teleforat abstimmen, ob Ihre Ziele und meine technische Optimierung zusammenpassen. Danach erstelle ich die detaillierte Intensiv-Analyse für Ihr System.

Die Score-Legende: Wo steht Ihre Website?

90–100 Punkte: Scheinbar optimiert
Die Performance ist im grünen Bereich, aber selten fehlerfrei. Selbst bei einem Score von 98 kosten minimale Verzögerungen oder ungenutzter Code in hochkompetitiven Märkten und KI-Suchumgebungen entscheidende Sichtbarkeit.

80–90 Punkte: Verschenktes Potenzial
Die Website läuft solide, verfehlt aber die technische Spitzenklasse. Die Ursachen liegen meist in nicht optimierten Assets, Rendering-Blockaden durch Skripte oder einer unsauberen DOM-Struktur.

70–80 Punkte: Messbare Defizite
Hier liegen deutliche Performance-Bremsen vor, die das Suchmaschinen-Ranking belasten. Strukturelle Probleme wie langsame Server-Antwortzeiten (TTFB) oder Layout-Verschiebungen (CLS) müssen isoliert behoben werden.

Unter 70 Punkte: Akuter Handlungsbedarf
Die Website ist technisch unzureichend und verliert durch extreme Ladezeiten aktiv Kunden. Massive Blockaden im kritischen Rendering-Pfad und veralteter Code erfordern eine grundlegende Sanierung.`,
  action: `Lassen Sie uns in einem kurzen Teleforat abstimmen, ob Ihre Ziele und meine technische Optimierung zusammenpassen.`,
  phone: `Gerne stelle ich meine Erkenntnisse in einem 15-minütigen Videocall vor. Ich zeige Ihnen konkret, wo die Performance-Bremsklötze liegen und wie wir diese beheben.`,
  signatureLong: `Mit freundlichen Grüßen

Timo E. Pohlhaus`,
  signatureShort: `Grüße

Timo E. Pohlhaus`
};

const checkboxes = ['cbSalutation', 'cbScore', 'cbAction', 'cbPhone', 'cbToken', 'cbSignatureLong', 'cbSignatureShort'];
let rawEmailContent = '';

// Set default subject with customer name from database
document.getElementById('emailSubject').value = `REVISION100 ${projectData.customer_name} website analyse`;

checkboxes.forEach(id => {
  document.getElementById(id).addEventListener('change', updatePreview);
});

function updatePreview() {
  let content = '';

  if (document.getElementById('cbSalutation').checked) {
    content += generateSalutation(projectData.customer_name) + '\n\n';
  }
  if (document.getElementById('cbScore').checked) {
    content += templates.score + '\n\n';
  }
  if (document.getElementById('cbAction').checked) {
    content += templates.action + '\n\n';
  }
  if (document.getElementById('cbPhone').checked) {
    content += templates.phone + '\n\n';
  }
  if (document.getElementById('cbToken').checked) {
    const tokenLink = projectData.secret_token
      ? window.location.origin + '/update.php?token=' + projectData.secret_token
      : '[Token nicht verfügbar]';
    content += `\n\n🔗 Aktualisierungslink:\n${tokenLink}`;
  }
  if (document.getElementById('cbSignatureLong').checked) {
    content += '\n\n' + templates.signatureLong;
  }
  if (document.getElementById('cbSignatureShort').checked) {
    content += '\n\n' + templates.signatureShort;
  }

  rawEmailContent = content.trim();
  document.getElementById('preview').innerHTML = rawEmailContent.replace(/\n/g, '<br>');
}

async function sendEmail() {
  const emailBody = rawEmailContent;
  const emailSubject = document.getElementById('emailSubject').value.trim();

  if (!emailBody) {
    alert('Bitte wählen Sie mindestens ein Email-Baustein aus.');
    return;
  }

  if (!emailSubject) {
    alert('Bitte geben Sie einen Betreff ein.');
    return;
  }

  const btn = event.target;
  btn.disabled = true;
  btn.style.opacity = '0.6';

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'send_custom_email',
        project_id: projectData.id,
        subject: emailSubject,
        body: emailBody
      })
    });

    const result = await response.json();

    if (result.success) {
      const led = document.createElement('div');
      led.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#0d8659;color:#fff;padding:20px 40px;border-radius:4px;font-weight:bold;z-index:9999;font-family:var(--font-mono);';
      led.textContent = '✓ Email versendet';
      document.body.appendChild(led);
      setTimeout(() => led.remove(), 3000);
    } else {
      alert('Fehler: ' + (result.error || 'Email konnte nicht versendet werden'));
    }
  } catch (error) {
    alert('Fehler beim Versenden: ' + error.message);
  } finally {
    btn.disabled = false;
    btn.style.opacity = '1';
  }
}

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
  const tunnel = '<?= htmlspecialchars($project['tunnel']) ?>';
  const lastInteractionDate = '<?= !empty($project['last_interaction_date']) ? htmlspecialchars($project['last_interaction_date']) : '' ?>';

  const phaseIdx = phaseIndex[tunnel] || 0;
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

// Template Management
async function loadTemplates() {
  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'list_email_templates',
        project_id: projectData.id
      })
    });

    const result = await response.json();
    const select = document.getElementById('templateSelect');

    if (result.success && result.data) {
      result.data.forEach(t => {
        const option = document.createElement('option');
        option.value = t.id;
        option.textContent = t.name;
        select.appendChild(option);
      });
    }
  } catch (error) {
    console.error('Fehler beim Laden der Vorlagen:', error);
  }
}

async function loadTemplate(templateId) {
  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'load_email_template',
        template_id: templateId
      })
    });

    const result = await response.json();
    if (result.success) {
      document.getElementById('preview').innerHTML = result.content;
    } else {
      alert('Fehler: ' + (result.error || 'Vorlage konnte nicht geladen werden'));
    }
  } catch (error) {
    alert('Fehler beim Laden: ' + error.message);
  }
}

async function saveTemplate() {
  const select = document.getElementById('templateSelect');
  let name = prompt('Template-Name:');

  if (!name) return;

  const content = document.getElementById('preview').innerHTML;

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_email_template',
        project_id: projectData.id,
        name: name,
        content: content
      })
    });

    const result = await response.json();
    if (result.success) {
      alert('✓ Template gespeichert: ' + name);
      // Reload templates
      select.innerHTML = '<option value="">-- Vorlage auswählen --</option>';
      loadTemplates();
    } else {
      alert('Fehler: ' + (result.error || 'Template konnte nicht gespeichert werden'));
    }
  } catch (error) {
    alert('Fehler beim Speichern: ' + error.message);
  }
}

async function deleteSelectedTemplate() {
  const select = document.getElementById('templateSelect');
  const templateId = select.value;

  if (!templateId || !confirm('Template wirklich löschen?')) return;

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'delete_email_template',
        template_id: templateId
      })
    });

    const result = await response.json();
    if (result.success) {
      alert('✓ Template gelöscht');
      select.innerHTML = '<option value="">-- Vorlage auswählen --</option>';
      loadTemplates();
    } else {
      alert('Fehler: ' + (result.error || 'Template konnte nicht gelöscht werden'));
    }
  } catch (error) {
    alert('Fehler beim Löschen: ' + error.message);
  }
}

// Dropdown Change Event
document.addEventListener('DOMContentLoaded', () => {
  renderPhaseSquares();
  updatePreview();
  loadTemplates();

  document.getElementById('templateSelect').addEventListener('change', (e) => {
    if (e.target.value) {
      loadTemplate(e.target.value);
      document.getElementById('deleteBtn').disabled = false;
    } else {
      document.getElementById('deleteBtn').disabled = true;
    }
  });
});
</script>

</body>
</html>
