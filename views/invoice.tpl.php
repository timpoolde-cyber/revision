<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Rechnungs-Zentrale (INV) — <?= htmlspecialchars($project['customer_name']) ?></title>
  <link rel="stylesheet" href="style-crm.css">
  <link rel="stylesheet" href="r400-status.css">
  <style>

    .container { background: #fff; padding: 0; }
    .content { padding: 16px 20px; margin: 0; display: flex; flex-direction: column; gap: 24px; }
    .section-title { font-weight: bold; font-size: 13px; margin-bottom: 12px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 8px; }

    .form-group {
      margin-bottom: 14px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .form-label {
      font-size: 11px;
      color: #666;
      text-transform: uppercase;
      font-weight: bold;
    }

    .form-input {
      padding: 10px;
      border: 1px solid #000;
      font-family: var(--font-mono);
      font-size: 13px;
      box-sizing: border-box;
      width: 100%;
    }

    .toggle-row {
      display: flex;
      gap: 16px;
      margin-bottom: 12px;
    }

    .toggle-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
    }

    .toggle-item input {
      cursor: pointer;
    }

    .invoice-preview-panel {
      border: 1px solid #000;
      padding: 20px;
      background: #fafafa;
      font-family: var(--font-mono);
      font-size: 12px;
      line-height: 1.5;
    }

    .btn {
      background: #000;
      color: #fff;
      border: 1px solid #000;
      padding: 10px 16px;
      font-family: var(--font-mono);
      font-weight: bold;
      text-transform: uppercase;
      cursor: pointer;
      min-height: 40px;
    }

    .btn:hover {
      background: #333;
    }

    .project-info {
      background: #fafafa;
      border: 1px solid #000;
      padding: 12px;
      margin-bottom: 16px;
      font-family: var(--font-mono);
      font-size: 11px;
    }

    .project-info-row {
      display: flex;
      justify-content: space-between;
      padding: 4px 0;
    }

    .project-info-label {
      font-weight: 700;
      text-transform: uppercase;
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
      <div class="section-title">Projektwert & Stammdaten</div>
    <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
      <input type="hidden" name="action" value="save_project_value">
      <div class="form-group">
        <label class="form-label">Projektwert (Brutto/Netto je nach Regelung)</label>
        <div style="display: flex; gap: 8px;">
          <input 
            type="text" 
            name="project_value" 
            id="projectValueInput" 
            class="form-input" 
            style="text-align: right;" 
            value="<?= (!empty($project['budget'])) ? number_format((float)$project['budget'], 2, ',', '') : '' ?>" 
            placeholder="0,00"
          >
          <button type="submit" class="btn">Sichern</button>
        </div>
      </div>
    </form>

    <div class="form-group" style="margin-top: 16px;">
      <label class="form-label">Rechnungsempfänger / Ansprechpartner</label>
      <select id="contactSelect" class="form-input" onchange="updateInvoicePreview()">
        <option value="default"><?= htmlspecialchars($project['customer_name']) ?> (Hauptadresse)</option>
        <?php if (!empty($contacts)): ?>
          <?php foreach ($contacts as $c): ?>
            <option value="<?= htmlspecialchars($c['id']) ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-email="<?= htmlspecialchars($c['email']) ?>" <?= $c['is_default'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?> <?= $c['role'] ? '('.htmlspecialchars($c['role']).')' : '' ?>
            </option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
    </div>
    </div>

    <div>
      <div class="section-title">Rechnungs-Konfiguration</div>
    <div class="toggle-row">
      <div class="toggle-item">
        <input type="checkbox" id="chkUstg" onchange="updateInvoicePreview()">
        <label for="chkUstg">§ 19 UStG (Kleinunternehmer)</label>
      </div>
      <div class="toggle-item">
        <input type="checkbox" id="chkVorkasse" checked onchange="updateInvoicePreview()">
        <label for="chkVorkasse">Zahlungsart: Vorkasse</label>
      </div>
    </div>
    </div>

    <div>
      <div class="section-title">Rechnungs-Vorschau</div>
    <div class="invoice-preview-panel" id="invoicePreview"></div>
    <button type="button" class="btn" style="width: 100%; margin-top: 16px;" onclick="generateAndSendInvoice()">PDF Generieren & Rechnungsversand starten</button>
    </div>

    <div>
      <div class="section-title">Dokumentation</div>
    <div class="form-group" style="margin: 0;">
      <a
        href="pdf.php?id=<?= $currentProjectId ?>"
        target="_blank"
        style="display: block; padding: 10px 16px; border: 1px solid #000; background: #f0f0f0; text-decoration: none; color: #000; text-align: center; font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;"
      >
        → PDF-Export öffnen
      </a>
    </div>
    </div>

  </div>
</div>

<script>
const projectData = {
  id: <?= $currentProjectId ?>,
  firma: '<?= htmlspecialchars(addslashes($project['customer_name'])) ?>',
  adresse: '<?= htmlspecialchars(addslashes($project['address'] ?? '')) ?>',
  plzOrt: '<?= htmlspecialchars(addslashes(($project['postal_code'] ?? '') . ' ' . ($project['city'] ?? ''))) ?>',
  baseValue: <?= (float)($project['budget'] ?? 0) ?>,
  rechnungsNr: 'INV-' + new Date().getFullYear() + '-' + String(<?= $currentProjectId ?>).padStart(4, '0')
};

function updateInvoicePreview() {
  const isKleinunternehmer = document.getElementById('chkUstg').checked;
  const isVorkasse = document.getElementById('chkVorkasse').checked;
  const contactSelect = document.getElementById('contactSelect');
  
  let valueStr = document.getElementById('projectValueInput').value.replace(/\./g, '').replace(',', '.');
  let currentVal = parseFloat(valueStr) || 0;
  
  let recipientName = projectData.firma;
  if (contactSelect.value !== 'default') {
    const selectedOption = contactSelect.options[contactSelect.selectedIndex];
    recipientName = projectData.firma + '\nz.Hd. ' + selectedOption.getAttribute('data-name');
  }

  let calculationsHtml = '';
  if (isKleinunternehmer) {
    calculationsHtml = `
Zwischensumme:          ${currentVal.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €
Umsatzsteuer (0%):       0,00 €
---------------------------------
Gesamtbetrag:           ${currentVal.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €

Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.`;
  } else {
    let net = currentVal / 1.19;
    let mwst = currentVal - net;
    calculationsHtml = `
Zwischensumme (Netto):  ${net.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €
Umsatzsteuer (19%):     ${mwst.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €
---------------------------------
Gesamtbetrag (Brutto):  ${currentVal.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €`;
  }

  let paymentText = isVorkasse 
    ? 'Zahlungsart: Vorkasse\nBitte überweisen Sie den Betrag vor Beginn der Umsetzung auf das angegebene Geschäftskonto.' 
    : 'Zahlungsziel: 14 Tage netto nach Erhalt der Rechnung ohne Abzug.';

  document.getElementById('invoicePreview').innerHTML = `
<strong>RECHNUNG</strong>
Nr: ${projectData.rechnungsNr}
Datum: ${new Date().toLocaleDateString('de-DE')}

<strong>Empfänger:</strong>
${recipientName}
${projectData.adresse}
${projectData.plzOrt}

-------------------------------------------------
Position: Revision100™ Code-Leistungsrevision 1,00x
-------------------------------------------------
${calculationsHtml}

${paymentText}
  `.trim().replace(/\n/g, '<br>');
}

async function generateAndSendInvoice() {
  const isKleinunternehmer = document.getElementById('chkUstg').checked;
  const isVorkasse = document.getElementById('chkVorkasse').checked;
  const contactSelect = document.getElementById('contactSelect');
  
  const btn = event.target;
  btn.disabled = true;
  btn.textContent = 'Verarbeite...';

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'generate_invoice_email',
        project_id: projectData.id,
        is_ustg: isKleinunternehmer,
        is_vorkasse: isVorkasse,
        contact_id: contactSelect.value,
        rechnungs_nr: projectData.rechnungsNr
      })
    });

    const result = await response.json();
    if (result.success) {
      alert('✓ PDF generiert und Rechnung per E-Mail versendet.');
    } else {
      alert('Fehler: ' + result.error);
    }
  } catch (e) {
    alert('Fehler bei der Übertragung: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'PDF Generieren & Rechnungsversand starten';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const currentPhase = '<?= htmlspecialchars($project['tunnel'] ?? 'anfrage') ?>';
  const lastInteractionDate = '<?= !empty($project['last_interaction_date']) ? htmlspecialchars($project['last_interaction_date']) : '' ?>';
  }

  updateInvoicePreview();
  
  document.getElementById('projectValueInput').addEventListener('input', updateInvoicePreview);
  
  const ustgCheckbox = document.getElementById('chkUstg');
  const prepaymentCheckbox = document.getElementById('chkVorkasse');

  if (localStorage.getItem('invoice_ustg_' + projectData.id)) {
    ustgCheckbox.checked = true;
    updateInvoicePreview();
  }
  if (localStorage.getItem('invoice_prepayment_' + projectData.id) === '0') {
    prepaymentCheckbox.checked = false;
    updateInvoicePreview();
  }

  ustgCheckbox.addEventListener('change', () => {
    if (ustgCheckbox.checked) {
      localStorage.setItem('invoice_ustg_' + projectData.id, '1');
    } else {
      localStorage.removeItem('invoice_ustg_' + projectData.id);
    }
  });

  prepaymentCheckbox.addEventListener('change', () => {
    localStorage.setItem('invoice_prepayment_' + projectData.id, prepaymentCheckbox.checked ? '1' : '0');
  });
});
</script>
</body>
</html>