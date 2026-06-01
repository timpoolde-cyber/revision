<?php
require_once __DIR__ . '/session_handler.php';
check_auth();
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Revision100™ — Maschinenraum</title>
  <link rel="stylesheet" href="style-crm.css">
  <link rel="stylesheet" href="print.css">
</head>
<body class="crm-body">

<header style="padding:16px 32px;">
  <div class="header-inner" style="padding:0;">
    <a href="/" class="logo" style="background:transparent;border:none;padding:0;display:block;margin-bottom:12px;">
      <svg width="200" height="60" viewBox="0 0 500 150" xmlns="http://www.w3.org/2000/svg" style="display:block;">
        <text x="10" y="55%" dominant-baseline="middle" text-anchor="start" style="font-family: 'Impact', 'Haettenschweiler', 'Arial Narrow Bold', sans-serif; font-weight: 900; font-size: 54px; letter-spacing: -0.9px; fill: black;">REVISION100<tspan font-size="24px">™</tspan></text>
      </svg>
    </a>
    <nav>
      <a href="crm.php" class="active" title="Dashboard">
        <svg width="24" height="24" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:-2px;">
          <path d="M 10,50 L 50,20 L 90,50 L 80,50 L 80,85 L 20,85 L 20,50 Z" stroke="currentColor" stroke-width="6" stroke-linejoin="round" fill="none" />
          <rect x="35" y="55" width="30" height="30" stroke="currentColor" stroke-width="5" fill="none" />
        </svg>
      </a>
      <a href="settings.php" title="Einstellungen">
        <svg width="28" height="28" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="vertical-align:-2px;">
          <path d="M 50,5 L 58,15 L 72,10 L 73,23 L 86,22 L 82,34 L 94,40 L 86,50 L 94,60 L 82,66 L 86,78 L 73,77 L 72,90 L 58,85 L 50,95 L 42,85 L 28,90 L 27,77 L 14,78 L 18,66 L 6,60 L 14,50 L 6,40 L 18,34 L 14,22 L 27,23 L 28,10 L 42,15 Z" fill="currentColor" />
          <circle cx="50" cy="50" r="28" fill="white" />
          <circle cx="50" cy="50" r="20" fill="currentColor" />
          <circle cx="50" cy="50" r="8" fill="white" />
        </svg>
      </a>
      <a href="logout.php" title="Logout">
        <svg width="28" height="28" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:-2px;">
          <path d="M 20,10 L 10,10 L 10,90 L 20,90 M 10,50 L 85,50 M 85,50 L 65,30 M 85,50 L 65,70" stroke="currentColor" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
    </nav>
  </div>
</header>

<div class="crm-layout">
  <aside class="crm-sidebar">
    <select id="phaseFilter" onchange="setFilter(this.value)" style="width:100%;padding:8px 12px;border:1px solid #000;border-bottom:2px solid #000;font-family:var(--font-mono);font-size:13px;background:#fff;color:#000;margin-bottom:12px;cursor:pointer;">
      <option value="alle">Alle Projekte</option>
      <option value="anfrage">01 · Anfrage</option>
      <option value="analyse">02 · Analyse</option>
      <option value="kontakt">03 · Kontakt</option>
      <option value="beauftragung">04 · Beauftragung</option>
      <option value="umsetzung">05 · Umsetzung</option>
      <option value="abgeschlossen">06 · Abgeschlossen</option>
    </select>
  </aside>

  <div class="crm-main">
    <div class="crm-toolbar">
      <span id="loadingIndicator" style="font-family:var(--font-mono);font-size:10px;letter-spacing:.15em;color:#aaa;display:none;">Laden…</span>
      <span id="errorIndicator" style="font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;color:#c00;display:none;"></span>
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
        <button class="btn-sm-ghost" onclick="exportCSV()">↓ CSV</button>
        <div style="background:#000;border:1px solid #000;display:flex;gap:0;border-radius:0;">
          <button style="padding:8px 12px;background:#000;color:#fff;border:none;cursor:pointer;font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.1em;" onclick="openModal()" title="Neues Projekt">+P</button>
          <div style="width:1px;background:#fff;"></div>
          <button style="padding:8px 12px;background:#000;color:#fff;border:none;cursor:pointer;font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.1em;" onclick="openCustomerModal()" title="Neuer Kunde">+K</button>
        </div>
      </div>
    </div>

    <div style="overflow-x:auto;">
      <table class="lead-table" id="leadTable" style="display:none;">
        <thead>
          <tr>
            <th>Firma / Name</th>
            <th style="width:140px;">Fortschritt</th>
            <th style="width:70px;"></th>
            <th>Verlauf</th>
          </tr>
        </thead>
        <tbody id="leadBody"></tbody>
      </table>
    </div>

    <div class="empty-state" id="emptyState" style="display:none;">
      <p>Keine Projekte vorhanden.</p>
      <button class="btn-sm" onclick="openModal()">+ Erstes Projekt anlegen</button>
    </div>
  </div>
</div>

<!-- PROJECT MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Neues Projekt</span>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <div style="font-family:var(--font-mono);font-size:9px;letter-spacing:.2em;text-transform:uppercase;color:#666;margin-bottom:12px;">Kundenauswahl</div>
      <div class="modal-form-row">
        <label class="modal-label" for="mCustomerSelect">Bestehender Kunde (optional)</label>
        <select class="modal-input" id="mCustomerSelect" onchange="selectCustomer(this.value)">
          <option value="">— Neuer Kunde —</option>
        </select>
      </div>

      <div style="font-family:var(--font-mono);font-size:9px;letter-spacing:.2em;text-transform:uppercase;color:#666;margin-bottom:12px;margin-top:16px;border-top:var(--line);padding-top:12px;">Projekt-Details</div>
      <div class="modal-form-row">
        <label class="modal-label" for="mFirma">Firmennamen *</label>
        <input class="modal-input" type="text" id="mFirma" placeholder="Name oder Firma" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="modal-form-row">
          <label class="modal-label" for="mEmail">E-Mail</label>
          <input class="modal-input" type="email" id="mEmail" placeholder="kontakt@firma.de">
        </div>
        <div class="modal-form-row">
          <label class="modal-label" for="mUrl">Website-URL *</label>
          <input class="modal-input" type="text" id="mUrl" placeholder="https://domain.de" required>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr;gap:12px;border-top:var(--line);padding-top:16px;margin-top:16px;">
        <div class="modal-form-row">
          <label class="modal-label" for="mBetrag">Angebot (€)</label>
          <input class="modal-input" type="number" id="mBetrag" min="0" step="100" placeholder="3500">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="modal-form-row">
          <label class="modal-label" for="mTunnel">Phase</label>
          <select class="modal-input" id="mTunnel">
            <option value="anfrage">01 · Anfrage</option>
            <option value="analyse">02 · Analyse</option>
            <option value="kontakt">03 · Kontakt</option>
            <option value="beauftragung">04 · Beauftragung</option>
            <option value="umsetzung">05 · Umsetzung</option>
            <option value="abgeschlossen">06 · Abgeschlossen</option>
          </select>
        </div>
        <div class="modal-form-row">
          <label class="modal-label" for="mPrio">Priorität</label>
          <select class="modal-input" id="mPrio">
            <option value="hoch">Hoch</option>
            <option value="normal" selected>Normal</option>
            <option value="niedrig">Niedrig</option>
          </select>
        </div>
      </div>

      <div class="modal-form-row" style="border-top:var(--line);padding-top:16px;margin-top:16px;">
        <label class="modal-label" for="mSchutt">Details</label>
        <textarea class="modal-input" id="mSchutt" rows="6" placeholder="Metriken, Änderungen…"></textarea>
      </div>

      <div class="modal-form-row">
        <label class="modal-label" for="mNotiz">Notiz</label>
        <textarea class="modal-input" id="mNotiz" rows="2" placeholder="Interne Notizen…"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-sm-ghost" onclick="closeModal()">Abbrechen</button>
      <button class="btn-sm" onclick="saveLead()">Speichern</button>
    </div>
  </div>
</div>

<!-- CUSTOMER MODAL -->
<div class="modal-overlay" id="customerModalOverlay">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Neuer Kunde</span>
      <button class="modal-close" onclick="closeCustomerModal()">×</button>
    </div>
    <div class="modal-body">
      <div class="modal-form-row">
        <label class="modal-label" for="cName">Name / Firma *</label>
        <input class="modal-input" type="text" id="cName" placeholder="Firma oder Name" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="modal-form-row">
          <label class="modal-label" for="cEmail">E-Mail</label>
          <input class="modal-input" type="email" id="cEmail" placeholder="kontakt@firma.de">
        </div>
        <div class="modal-form-row">
          <label class="modal-label" for="cPhone">Telefon</label>
          <input class="modal-input" type="tel" id="cPhone" placeholder="+49 30 ...">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="modal-form-row">
          <label class="modal-label" for="cCity">Stadt</label>
          <input class="modal-input" type="text" id="cCity" placeholder="Berlin">
        </div>
        <div class="modal-form-row">
          <label class="modal-label" for="cPostal">Postleitzahl</label>
          <input class="modal-input" type="text" id="cPostal" placeholder="10115">
        </div>
      </div>

      <div class="modal-form-row">
        <label class="modal-label" for="cAddress">Adresse</label>
        <input class="modal-input" type="text" id="cAddress" placeholder="Straße und Nummer">
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-sm-ghost" onclick="closeCustomerModal()">Abbrechen</button>
      <button class="btn-sm" onclick="saveCustomer()">Speichern</button>
    </div>
  </div>
</div>

<script>
const API = 'api.php';
let leads = [];
let currentFilter = 'alle';
let customersData = [];

const PHASES = {
  anfrage:       '01 · Anfrage',
  analyse:       '02 · Analyse',
  kontakt:       '03 · Kontakt',
  beauftragung:  '04 · Beauftragung',
  umsetzung:     '05 · Umsetzung',
  abgeschlossen: '06 · Abgeschlossen'
};

async function apiFetch(options = {}, url = API) {
  const res = await fetch(url, options);
  if (res.status === 403) { window.location.href = 'login.php'; return Promise.reject(new Error('Unauthorized')); }
  const json = await res.json();
  if (!res.ok) return json;
  return json;
}

async function postAction(body) {
  return apiFetch({ method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
}

async function loadLeads() {
  showLoading(true);
  try {
    const json = await apiFetch();
    leads = json.data || [];
    render();
    clearError();
  } catch (e) {
    showError('API nicht erreichbar');
  } finally {
    showLoading(false);
  }
}

function render() {
  const filtered = currentFilter === 'alle' ? leads : leads.filter(l => l.tunnel === currentFilter);
  const tbody = document.getElementById('leadBody');
  const empty = document.getElementById('emptyState');
  const table = document.getElementById('leadTable');

  if (filtered.length === 0) {
    tbody.innerHTML = '';
    table.style.display = 'none';
    empty.style.display = 'block';
  } else {
    empty.style.display = 'none';
    table.style.display = '';
    tbody.innerHTML = filtered.map(renderRow).join('');
  }
}

function renderRow(l) {
  const prioColor = {hoch:'#c00', normal:'#666', niedrig:'#aaa'}[l.prioritaet] || '#666';
  const prioSquare = `<span style="display:inline-block;width:10px;height:10px;background:${prioColor};margin-right:6px;vertical-align:middle;"></span>`;

  return `<tr onclick="window.location.href='project.php?id=${l.id}'" style="cursor:pointer;">
    <td>
      ${prioSquare}
      <strong>${esc(l.customer_name)}</strong>
      ${l.email ? `<br><small style="color:#888;font-size:11px;">${esc(l.email)}</small>` : ''}
    </td>
    <td style="padding:12px 8px;"></td>
    <td style="font-family:var(--font-mono);text-align:right;font-size:13px;">${l.betrag ? Number(l.betrag).toLocaleString('de-DE') : '—'}</td>
    <td style="font-size:11px;color:#666;">—</td>
  </tr>`;
}

function setFilter(filter) {
  currentFilter = filter;
  render();
}

function openModal() {
  document.getElementById('mFirma').value = '';
  document.getElementById('mEmail').value = '';
  document.getElementById('mUrl').value = '';
  document.getElementById('mBetrag').value = '';
  document.getElementById('mNotiz').value = '';
  document.getElementById('mSchutt').value = '';
  document.getElementById('mTunnel').value = 'anfrage';
  document.getElementById('mPrio').value = 'normal';
  document.getElementById('mCustomerSelect').value = '';
  document.getElementById('modalOverlay').classList.add('open');
  loadCustomersInModal();
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}

function loadCustomersInModal() {
  const select = document.getElementById('mCustomerSelect');
  fetch('api_customers.php')
    .then(r => r.json())
    .then(json => {
      if (json.success && json.data) {
        customersData = json.data;
        const html = json.data.map(c => `<option value="${c.id}">${c.knr} · ${c.customer_name}</option>`).join('');
        if (html) select.innerHTML = '<option value="">— Neuer Kunde —</option>' + html;
      }
    })
    .catch(() => {});
}

function selectCustomer(customerId) {
  if (customerId) {
    const customer = customersData.find(c => c.id == customerId);
    if (customer) {
      document.getElementById('mEmail').value = customer.email || '';
    }
  }
}

async function saveLead() {
  const name = document.getElementById('mFirma').value.trim();
  const url = document.getElementById('mUrl').value.trim();
  if (!name || !url) { showError('Firma und URL erforderlich'); return; }

  const btn = event.target;
  btn.textContent = '…';
  btn.disabled = true;

  try {
    const json = await postAction({
      action: 'save',
      id: null,
      customer_id: document.getElementById('mCustomerSelect').value || null,
      customer_name: name,
      email: document.getElementById('mEmail').value.trim(),
      target_url: url,
      betrag: document.getElementById('mBetrag').value || null,
      tunnel: document.getElementById('mTunnel').value,
      project_status: 'offen',
      prioritaet: document.getElementById('mPrio').value,
      notiz: document.getElementById('mNotiz').value.trim()
    });

    if (json.success) {
      closeModal();
      await loadLeads();
      showError('✓ Projekt erstellt');
      setTimeout(clearError, 2000);
    } else {
      showError(json.error || 'Fehler');
    }
  } catch (e) {
    showError('Fehler: ' + e.message);
  } finally {
    btn.textContent = 'Speichern';
    btn.disabled = false;
  }
}

function openCustomerModal() {
  document.getElementById('cName').value = '';
  document.getElementById('cEmail').value = '';
  document.getElementById('cPhone').value = '';
  document.getElementById('cCity').value = '';
  document.getElementById('cPostal').value = '';
  document.getElementById('cAddress').value = '';
  document.getElementById('customerModalOverlay').classList.add('open');
}

function closeCustomerModal() {
  document.getElementById('customerModalOverlay').classList.remove('open');
}

async function saveCustomer() {
  const name = document.getElementById('cName').value.trim();
  if (!name) { showError('Name erforderlich'); return; }

  const btn = event.target;
  btn.textContent = '…';
  btn.disabled = true;

  try {
    const res = await fetch('api_customers.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_name: name,
        email: document.getElementById('cEmail').value.trim(),
        phone: document.getElementById('cPhone').value.trim(),
        address: document.getElementById('cAddress').value.trim(),
        city: document.getElementById('cCity').value.trim(),
        postal_code: document.getElementById('cPostal').value.trim()
      })
    });

    const json = await res.json();
    if (json.success) {
      closeCustomerModal();
      loadCustomersInModal();
      showError('✓ Kunde erstellt: ' + json.data.knr);
      setTimeout(clearError, 2000);
    } else {
      showError(json.error || 'Fehler beim Speichern');
    }
  } catch (e) {
    showError('Fehler: ' + e.message);
  } finally {
    btn.textContent = 'Speichern';
    btn.disabled = false;
  }
}

function exportCSV() {
  if (!leads.length) { alert('Keine Daten'); return; }
  const headers = ['ID','Firma','E-Mail','URL','Phase','Betrag','Status'];
  const rows = leads.map(l => [l.id, l.customer_name, l.email, l.target_url, PHASES[l.tunnel] || l.tunnel, l.betrag, l.project_status].map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(';'));
  const csv = '﻿' + [headers.join(';'), ...rows].join('\n');
  const a = Object.assign(document.createElement('a'), { href: 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv), download: 'crm-' + new Date().toISOString().split('T')[0] + '.csv' });
  a.click();
}

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function showLoading(v) { document.getElementById('loadingIndicator').style.display = v ? '' : 'none'; }
function showError(msg) { const el = document.getElementById('errorIndicator'); el.textContent = msg; el.style.display = ''; }
function clearError() { document.getElementById('errorIndicator').style.display = 'none'; }

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeCustomerModal(); } });

(async () => { await loadLeads(); })();
</script>

</body>
</html>
