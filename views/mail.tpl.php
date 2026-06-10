<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Email: <?= htmlspecialchars($project['customer_name']) ?></title>
  <link rel="stylesheet" href="style-crm.css">
  <script src="../crm-functions.js"></script>
  <style>
    .container { background: #fff; padding: 0; }
    .content { padding: 16px 20px; margin: 0; display: flex; flex-direction: column; gap: 24px; }

    .section-title { font-weight: bold; font-size: 13px; margin-bottom: 12px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 8px; }
    .checkbox-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
    .checkbox-item { display: flex; align-items: flex-start; gap: 8px; }
    .checkbox-item input { margin-top: 4px; cursor: pointer; }
    .checkbox-item label { flex: 1; font-size: 12px; cursor: pointer; word-wrap: break-word; overflow-wrap: break-word; }

    .preview-box { border: 1px solid #000; padding: 16px; background: #fafafa; font-size: 12px; line-height: 1.6; max-height: 500px; overflow-y: auto; overflow-x: hidden; word-wrap: break-word; width: 100%; box-sizing: border-box; }
    .btn { background: #000; color: #fff; border: 1px solid #000; padding: 10px 16px; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; cursor: pointer; min-height: 40px; }
    .btn:hover { background: #333; }
    .btn-send { width: 100%; margin-top: 16px; }

    .pdf-actions-row { display: flex; gap: 8px; margin-top: 12px; }
    .btn-pdf { flex: 1; background: #0066cc; border: 1px solid #0052a3; color: #fff; font-family: var(--font-mono); font-size: 11px; font-weight: bold; text-transform: uppercase; padding: 10px 4px; cursor: pointer; text-align: center; text-decoration: none; display: inline-block; }
    .btn-pdf:hover { background: #0052a3; }

    .form-input-text { width: 100%; padding: 8px; border: 1px solid #000; font-family: var(--font-mono); font-size: 12px; box-sizing: border-box; margin-bottom: 8px; }
    .editor-toolbar { display: flex; gap: 4px; margin-bottom: 8px; align-items: center; width: 100%; }
    .btn-tool { width: 36px; height: 36px; background: #000; color: #fff; border: 1px solid #000; font-weight: bold; cursor: pointer; font-family: var(--font-mono); }
    .btn-tool:hover { background: #333; }

    @media (max-width: 768px) {
      .content { padding: 12px; gap: 20px; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/header.tpl.php'; ?>

<div class="crm-layout">
  <?php include __DIR__ . '/nav.tpl.php'; ?>
</div>

<div class="container">
  <div class="content">
    
    <div>
      <div class="section-title">E-Mail-Bausteine & Vorlagen</div>
      <div style="margin-bottom: 16px;">
        <div style="display: flex; gap: 8px;">
          <select id="templateSelect" style="flex: 1; padding: 8px; border: 1px solid #000; font-family: var(--font-mono); font-size: 12px; box-sizing: border-box; min-width: 0;">
            <option value="">-- Vorlage auswählen --</option>
          </select>
          <button type="button" class="btn" onclick="saveTemplate()">Speichern</button>
          <button type="button" style="background: #ff3131; color: #fff; border: 1px solid #ff3131;" class="btn" onclick="deleteSelectedTemplate()" id="deleteBtn" disabled>Löschen</button>
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
          <input type="checkbox" id="cbSignatureLong" checked>
          <label for="cbSignatureLong">Gruß lang</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="cbSignatureShort">
          <label for="cbSignatureShort">Gruß kurz</label>
        </div>
      </div>
      
      <div class="section-title">PSI-Report Dokumente</div>
      <div class="pdf-actions-row">
        <a href="psi_pdf_generator.php?id=<?= $currentProjectId ?>" target="_blank" class="btn-pdf">📊 Report</a>
        <a href="psi_history_pdf_generator.php?id=<?= $currentProjectId ?>" target="_blank" class="btn-pdf">📈 Historie</a>
        <a href="psi_audit_pdf_generator.php?id=<?= $currentProjectId ?>" target="_blank" class="btn-pdf">🔍 Audit</a>
      </div>
    </div>

    <div>
      <div class="section-title">Nachrichten-Zentrale</div>
      <input type="text" id="emailSubject" placeholder="Betreff" class="form-input-text">
      
      <div class="editor-toolbar">
        <button type="button" class="btn-tool" onclick="document.execCommand('bold', false, null);">B</button>
        <button type="button" class="btn-tool" onclick="document.execCommand('italic', false, null);">I</button>
        <button type="button" class="btn-tool" onclick="document.execCommand('underline', false, null);">U</button>
        <button type="button" class="btn" style="margin-left: auto; font-size: 12px;" onclick="sendEmail()">Senden</button>
      </div>
      
      <div class="preview-box" id="preview" contenteditable="true" style="cursor: text; white-space: pre-line;">Vorschau wird geladen...</div>
    </div>
    
  </div>
</div>

<script>
const projectData = {
  id: <?= $currentProjectId ?>,
  customer_name: '<?= htmlspecialchars(addslashes($project['customer_name'])) ?>',
  email: '<?= htmlspecialchars(addslashes($project['email'])) ?>',
  last_score: <?= $project['last_score'] !== null ? $project['last_score'] : 'null' ?>,
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
  signatureLong: `Mit freundlichen Grüßen\n\nTimo E. Pohlhaus`,
  signatureShort: `Grüße\n\nTimo E. Pohlhaus`
};

const checkboxes = ['cbSalutation', 'cbScore', 'cbAction', 'cbPhone', 'cbToken', 'cbSignatureLong', 'cbSignatureShort'];
let rawEmailContent = '';

// Default-Betreff im Ulm-Stil vordefinieren
document.getElementById('emailSubject').value = `REVISION100 ${projectData.customer_name} website analyse`;

checkboxes.forEach(id => {
  document.getElementById(id).addEventListener('change', updatePreview);
});

// Steuert das exklusive Umschalten der beiden Grußformeln
document.getElementById('cbSignatureLong').addEventListener('change', function() {
  if (this.checked) document.getElementById('cbSignatureShort').checked = false;
});
document.getElementById('cbSignatureShort').addEventListener('change', function() {
  if (this.checked) document.getElementById('cbSignatureLong').checked = false;
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
    content += `🔗 Aktualisierungslink:\n${tokenLink}\n\n`;
  }
  if (document.getElementById('cbSignatureLong').checked) {
    content += templates.signatureLong;
  }
  if (document.getElementById('cbSignatureShort').checked) {
    content += templates.signatureShort;
  }

  rawEmailContent = content.trim();
  document.getElementById('preview').innerHTML = rawEmailContent.replace(/\n/g, '<br>');
}

async function sendEmail() {
  const emailBody = document.getElementById('preview').innerText.trim();
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
      setTimeout(() => led.remove(), 2500);
    } else {
      alert('Fehler: ' + (result.error || 'Email konnte nicht versendet werden'));
    }
  } catch (error) {
    alert('Fehler beim Versenden: ' + error.message);
  } finally {
    btn.disabled = false;
  }
}

// Template Management via api.php
async function loadTemplates() {
  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'list_email_templates', project_id: projectData.id })
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
      body: JSON.stringify({ action: 'load_email_template', template_id: templateId })
    });
    const result = await response.json();
    if (result.success) {
      document.getElementById('preview').innerHTML = result.content;
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
      body: JSON.stringify({ action: 'save_email_template', project_id: projectData.id, name: name, content: content })
    });
    const result = await response.json();
    if (result.success) {
      select.innerHTML = '<option value="">-- Vorlage auswählen --</option>';
      loadTemplates();
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
      body: JSON.stringify({ action: 'delete_email_template', template_id: templateId })
    });
    const result = await response.json();
    if (result.success) {
      select.innerHTML = '<option value="">-- Vorlage auswählen --</option>';
      loadTemplates();
    }
  } catch (error) {
    alert('Fehler beim Löschen: ' + error.message);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const tunnel = '<?= htmlspecialchars($project['tunnel']) ?>';
  const lastInteractionDate = '<?= !empty($project['last_interaction_date']) ? htmlspecialchars($project['last_interaction_date']) : '' ?>';
  const container = document.getElementById('statusSquares');
  container.innerHTML = window.renderPhaseSquares(tunnel, lastInteractionDate).html;

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