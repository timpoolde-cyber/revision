<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rechnungs-Zentrale (INV) — <?= htmlspecialchars($project['customer_name']) ?></title>
  <link rel="stylesheet" href="style-crm.css">
  <style>
    :root {
      --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      --font-sans: -apple-system, BlinkMacSystemFont, Arial, sans-serif;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      background: #f0f0f0;
      color: #000;
      font-family: var(--font-sans);
      line-height: 1.5;
    }

    /* Starre Desktop-Zentrierung bei 600px für alle Hauptblöcke */
    header, .crm-layout, .container {
      max-width: 600px;
      width: 100%;
      margin-left: auto;
      margin-right: auto;
      box-sizing: border-box;
    }

    header {
      background: #fff;
      padding: 45px 16px 35px 16px;
      border-bottom: 1px solid #000;
      margin-bottom: 40px;
      display: block;
    }

    .brand-name {
      font-family: var(--font-mono);
      font-size: 32px;
      font-weight: 700;
      letter-spacing: -1px;
      line-height: 1.0;
      color: #000;
      margin: 0;
      padding: 0;
      display: inline-block;
    }

    .container {
      background: #fff;
      padding: 0;
    }

    .section {
      padding: 16px 32px;
      border-bottom: 1px solid #000;
    }

    .section:last-child {
      border-bottom: none;
    }

    .section-title {
      font-family: var(--font-mono);
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      color: #000;
      margin-bottom: 16px;
      display: block;
    }

    .form-group {
      margin-bottom: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .form-label {
      font-family: var(--font-sans);
      font-size: 12px;
      font-weight: 500;
      color: #333;
    }

    .form-input {
      padding: 10px 12px;
      border: 1px solid #000;
      font-family: var(--font-sans);
      font-size: 13px;
      background: #fff;
      color: #000;
      width: 100%;
    }

    .form-input:focus {
      outline: none;
      background: #f9f9f9;
    }

    select.form-input {
      cursor: pointer;
    }

    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
    }

    .checkbox-group input[type="checkbox"] {
      width: 16px;
      height: 16px;
      cursor: pointer;
      border: 1px solid #000;
      accent-color: #000;
    }

    .checkbox-group label {
      font-family: var(--font-sans);
      font-size: 13px;
      color: #333;
      cursor: pointer;
    }

    .button-group {
      display: flex;
      gap: 8px;
      margin-top: 20px;
    }

    .btn {
      padding: 10px 16px;
      border: 1px solid #000;
      background: #000;
      color: #fff;
      font-family: var(--font-mono);
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      cursor: pointer;
      flex: 1;
      text-align: center;
    }

    .btn:hover {
      background: #333;
    }

    .btn-secondary {
      background: #fff;
      color: #000;
    }

    .btn-secondary:hover {
      background: #f0f0f0;
    }

    .success-message {
      background: #e8f5e9;
      border: 1px solid #4caf50;
      color: #2e7d32;
      padding: 12px 16px;
      margin-bottom: 16px;
      font-family: var(--font-mono);
      font-size: 11px;
      text-transform: uppercase;
    }

    .project-info {
      background: #f9f9f9;
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

    .status-square {
      width: 22px;
      height: 22px;
      border: 1px solid #000;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 9px;
      font-weight: bold;
    }

    @media (max-width: 768px) {
      header { padding: 25px 16px 19px 16px; margin-bottom: 22px; }
      .section { padding: 12px 16px; }
      .button-group { flex-direction: column; }
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

  <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
  <div class="section">
    <div class="success-message">✓ Projektwert gespeichert</div>
  </div>
  <?php endif; ?>

  <!-- PROJEKT-INFORMATIONEN -->
  <div class="section">
    <span class="section-title">Projekt-Informationen</span>
    <div class="project-info">
      <div class="project-info-row">
        <span class="project-info-label">Kunde:</span>
        <span><?= htmlspecialchars($project['customer_name']) ?></span>
      </div>
      <div class="project-info-row">
        <span class="project-info-label">URL:</span>
        <span style="word-break: break-all;"><?= htmlspecialchars($project['target_url']) ?></span>
      </div>
      <div class="project-info-row">
        <span class="project-info-label">Phase:</span>
        <span><?= htmlspecialchars($project['tunnel']) ?></span>
      </div>
      <?php if (isset($project['budget']) && $project['budget']): ?>
      <div class="project-info-row">
        <span class="project-info-label">Aktueller Wert:</span>
        <span><?= number_format((float)$project['budget'], 2, ',', '.') ?> €</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- PROJEKTWERT-ZENTRALE -->
  <div class="section">
    <span class="section-title">Projektwert (Budget)</span>
    <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>">
      <input type="hidden" name="action" value="save_project_value">
      <div class="form-group">
        <label class="form-label" for="project_value">Projektwert in Euro</label>
        <input
          type="text"
          id="project_value"
          name="project_value"
          class="form-input"
          placeholder="z.B. 4.800,00"
          value="<?= (!empty($project['budget'])) ? number_format((float)$project['budget'], 2, ',', '.') : '' ?>"
          pattern="[0-9.,]+"
        >
      </div>
      <div class="button-group">
        <button type="submit" class="btn">Speichern</button>
      </div>
    </form>
  </div>

  <!-- ANSPRECHPARTNER -->
  <div class="section">
    <span class="section-title">Ansprechpartner</span>
    <?php if (!empty($contacts)): ?>
    <div class="form-group">
      <label class="form-label" for="contact_select">Standard-Kontakt</label>
      <select id="contact_select" class="form-input">
        <option value="">— Wählen —</option>
        <?php foreach ($contacts as $contact): ?>
        <option value="<?= htmlspecialchars($contact['id']) ?>" <?= $contact['is_default'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($contact['name']) ?>
          <?php if ($contact['email']): ?>
          (<?= htmlspecialchars($contact['email']) ?>)
          <?php endif; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php else: ?>
    <div style="color: #999; font-family: var(--font-mono); font-size: 11px; text-transform: uppercase;">
      Keine Ansprechpartner definiert
    </div>
    <?php endif; ?>
  </div>

  <!-- ABRECHNUNG & STEUERN -->
  <div class="section">
    <span class="section-title">Abrechnung & Steuern</span>

    <div class="checkbox-group">
      <input
        type="checkbox"
        id="ustg_19_checkbox"
        name="ustg_19"
        value="1"
      >
      <label for="ustg_19_checkbox">
        § 19 UStG (Kleinunternehmerregelung) — Keine Umsatzsteuer
      </label>
    </div>

    <div class="checkbox-group">
      <input
        type="checkbox"
        id="prepayment_checkbox"
        name="prepayment"
        value="1"
      >
      <label for="prepayment_checkbox">
        Vorkasse — 50% Anzahlung erforderlich
      </label>
    </div>
  </div>

  <!-- RECHNUNGS-NOTIZEN -->
  <div class="section">
    <span class="section-title">Interne Notizen</span>
    <div class="form-group">
      <textarea
        id="invoice_notes"
        class="form-input"
        rows="4"
        placeholder="Rechnungsspezifische Notizen…"
        style="font-family: var(--font-sans); resize: vertical;"
      ></textarea>
    </div>
    <div class="button-group">
      <button type="button" class="btn btn-secondary" onclick="clearInvoiceNotes()">Löschen</button>
      <button type="button" class="btn" onclick="saveInvoiceNotes()">Speichern</button>
    </div>
  </div>

  <!-- RECHNUNGS-VORSCHAU -->
  <div class="section">
    <span class="section-title">Dokumentation</span>
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

<script>
  // VisionControl Paletten & Mathematische Alterung (Identisch mit project.tpl.php)
  const colorPalettes = {
    green: ['#a3e4d7', '#7ed4c1', '#5cc4ab', '#3bb495', '#1fa47f', '#0d8659'],
    orange: ['#FFE4B5', '#FFD699', '#FFC87D', '#FFBA61', '#FFAB45', '#FF9529'],
    red: ['#FFB3B3', '#FF9999', '#FF7F7F', '#FF6565', '#FF4B4B', '#FF3131'],
    gray: ['#D3D3D3', '#BEBEBE', '#A9A9A9', '#949494', '#7F7F7F', '#696969']
  };
  const phaseIndex = { anfrage: 0, analyse: 1, kontakt: 2, beauftragung: 3, umsetzung: 4, abgeschlossen: 5 };

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
    const currentPhase = '<?= htmlspecialchars($project['tunnel'] ?? 'anfrage') ?>';
    const lastInteractionDate = '<?= !empty($project['last_interaction_date']) ? htmlspecialchars($project['last_interaction_date']) : '' ?>';
    const phaseIdx = phaseIndex[currentPhase] || 0;
    const status = getAgeStatus(lastInteractionDate);
    const colors = colorPalettes[status];
    const container = document.getElementById('statusSquares');
    if (!container) return;
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

  function clearInvoiceNotes() {
    const notes = document.getElementById('invoice_notes');
    if (confirm('Notizen wirklich löschen?')) {
      notes.value = '';
      notes.focus();
    }
  }

  function saveInvoiceNotes() {
    const notes = document.getElementById('invoice_notes');
    const value = notes.value.trim();

    if (!value) {
      alert('Bitte Notiz eingeben');
      return;
    }

    const data = new FormData();
    data.append('action', 'save_invoice_notes');
    data.append('project_id', <?= $currentProjectId ?>);
    data.append('notes', value);

    fetch('api.php', { method: 'POST', body: data })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          alert('✓ Notizen gespeichert');
        } else {
          alert('Fehler: ' + (json.error || 'Unbekannt'));
        }
      })
      .catch(e => alert('Fehler: ' + e.message));
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Rendert die Quadrate unbarmherzig beim Laden der Seite
    renderPhaseSquares();

    const contactSelect = document.getElementById('contact_select');
    const ustgCheckbox = document.getElementById('ustg_19_checkbox');
    const prepaymentCheckbox = document.getElementById('prepayment_checkbox');
    const notesArea = document.getElementById('invoice_notes');

    // Laden der gespeicherten Werte aus localStorage
    if (localStorage.getItem('invoice_ustg_' + <?= $currentProjectId ?>)) {
      ustgCheckbox.checked = true;
    }
    if (localStorage.getItem('invoice_prepayment_' + <?= $currentProjectId ?>)) {
      prepaymentCheckbox.checked = true;
    }
    if (localStorage.getItem('invoice_notes_' + <?= $currentProjectId ?>)) {
      notesArea.value = localStorage.getItem('invoice_notes_' + <?= $currentProjectId ?>);
    }

    // Event-Listener für Speicherung
    ustgCheckbox.addEventListener('change', () => {
      if (ustgCheckbox.checked) {
        localStorage.setItem('invoice_ustg_' + <?= $currentProjectId ?>, '1');
      } else {
        localStorage.removeItem('invoice_ustg_' + <?= $currentProjectId ?>);
      }
    });

    prepaymentCheckbox.addEventListener('change', () => {
      if (prepaymentCheckbox.checked) {
        localStorage.setItem('invoice_prepayment_' + <?= $currentProjectId ?>, '1');
      } else {
        localStorage.removeItem('invoice_prepayment_' + <?= $currentProjectId ?>);
      }
    });

    notesArea.addEventListener('blur', () => {
      const value = notesArea.value.trim();
      if (value) {
        localStorage.setItem('invoice_notes_' + <?= $currentProjectId ?>, value);
      }
    });
  });
</script>

</body>
</html>