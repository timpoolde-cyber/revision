<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Revision100™ — System-Zentrale</title>
  <link rel="stylesheet" href="style-crm.css">
  <link rel="stylesheet" href="r400-status.css">
  <link rel="stylesheet" href="print.css">
  <script src="../r400-status.js"></script>
</head>
<body class="crm-body">

<header>
  <div class="brand-container">
    <div class="brand"><span class="brand-name">R400™</span></div>
    <?php r400_status_sprite(); ?>
  </div>
</header>

<div class="crm-layout">
  <?php include __DIR__ . '/nav.tpl.php'; ?>
</div>

<div class="crm-layout">
  <div class="crm-main">
    <div class="crm-toolbar">
      <span id="loadingIndicator" style="font-family:var(--font-mono);font-size:10px;letter-spacing:.15em;color:#aaa;display:none;">Laden...</span>
      <span id="errorIndicator" style="font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;color:#c00;display:none;"></span>
      <select id="phaseFilter" onchange="setFilter(this.value)">
        <option value="alle">Alle</option>
        <option value="anfrage">01 Anfrage</option>
        <option value="analyse">02 Analyse</option>
        <option value="kontakt">03 Kontakt</option>
        <option value="beauftragung">04 Beauftragung</option>
        <option value="umsetzung">05 Umsetzung</option>
        <option value="abgeschlossen">06 Abgeschlossen</option>
      </select>
      <button class="btn-sm-ghost" onclick="exportCSV()" style="white-space:nowrap;">CSV</button>
      <button style="padding:8px 12px;background:#000;color:#fff;border:1px solid #000;cursor:pointer;font-family:var(--font-mono);font-size:11px;font-weight:700;" onclick="openModal()">+P</button>
      <button style="padding:8px 12px;background:#000;color:#fff;border:1px solid #000;cursor:pointer;font-family:var(--font-mono);font-size:11px;font-weight:700;" onclick="openCustomerModal()">+K</button>
    </div>

    <div id="leadsList"></div>

    <div class="empty-state" id="emptyState" style="display:none;padding:32px 16px;text-align:center;">
      <p>Keine Projekte vorhanden.</p>
      <button class="btn-sm" onclick="openModal()">Erstes Projekt anlegen</button>
    </div>
  </div>
</div>

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
          <input class="modal-input" type="text" id="mUrl" placeholder="domain.de" required>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;border-top:var(--line);padding-top:16px;margin-top:16px;">
        <div class="modal-form-row">
          <label class="modal-label" for="mMobile">Mobil</label>
          <input class="modal-input" type="tel" id="mMobile" placeholder="+49 171 ...">
        </div>
        <div class="modal-form-row">
          <label class="modal-label" for="mPhone">Landline</label>
          <input class="modal-input" type="tel" id="mPhone" placeholder="+49 30 ...">
        </div>
      </div>

      <div class="modal-form-row">
        <label class="modal-label" for="mAddress">Adresse</label>
        <gmpx-place-autocomplete for="mAddress"></gmpx-place-autocomplete>
        <input class="modal-input" type="text" id="mAddress" placeholder="Straße und Nummer" autocomplete="off">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
        <div class="modal-form-row">
          <label class="modal-label" for="mCity">Ort</label>
          <input class="modal-input" type="text" id="mCity" placeholder="Stadt" autocomplete="off">
        </div>
        <div class="modal-form-row">
          <label class="modal-label" for="mPostal">PLZ</label>
          <input class="modal-input" type="text" id="mPostal" placeholder="Postleitzahl" autocomplete="off">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;">
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
          <label class="modal-label" for="mChannel">Kanal</label>
          <select class="modal-input" id="mChannel">
            <option value="lead">Lead</option>
            <option value="maps" selected>Maps</option>
            <option value="vip">VIP</option>
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
        <div class="modal-form-row">
          <label class="modal-label" for="mAlertLevel">Alert-Level</label>
          <select class="modal-input" id="mAlertLevel">
            <option value="eskalation">Eskalation</option>
            <option value="kritisch">Kritisch</option>
            <option value="normal" selected>Normal</option>
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
          <label class="modal-label" for="cPhone">Mobil / Landline</label>
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

async function apiFetch(options = {}, action = 'get_leads') {
  const url = `${API}?action=${action}`;
  const res = await fetch(url, options);
  if (res.status === 403) { window.location.href = 'login.php'; return Promise.reject(new Error('Unauthorized')); }
  const json = await res.json();
  if (!res.ok) return Promise.reject(new Error(json.error || 'API Error'));
  return json;
}

async function postAction(body) {
  return fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(r => r.json());
}

async function loadLeads() {
  showLoading(true);
  try {
    const json = await apiFetch({}, 'get_leads');
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
  const list = document.getElementById('leadsList');
  const empty = document.getElementById('emptyState');

  if (filtered.length === 0) {
    list.innerHTML = '';
    empty.style.display = 'block';
  } else {
    empty.style.display = 'none';
    list.innerHTML = filtered.map(renderCard).join('');
  }
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

function formatTokenDate(dateStr) {
  const d = new Date(dateStr);
  const day = d.getDate();
  const month = d.getMonth() + 1;
  const hours = String(d.getHours()).padStart(2, '0');
  const mins = String(d.getMinutes()).padStart(2, '0');
  return `${day}.${month}. ${hours}:${mins}`;
}

function getTokenStatus(token_created_at, token_used_at, tunnel) {
  if (!token_created_at) return { status: 'none', date: '', color: '' };
  if (token_used_at) {
    return { status: 'used', date: formatTokenDate(token_used_at), color: '#16c784' };
  }
  const created = new Date(token_created_at);
  const now = new Date();
  const days = Math.floor((now - created) / (1000 * 60 * 60 * 24));
  if (days > 5) {
    return { status: 'invalid', date: formatTokenDate(token_created_at), color: '#d3d3d3' };
  }
  return { status: 'active', date: formatTokenDate(token_created_at), color: '#e8e8e8' };
}

function getLHColor(score) {
  if (!score) return '#eee';
  if (score >= 90) return '#0d8659';
  if (score >= 75) return '#FF9529';
  if (score >= 50) return '#FF9529';
  return '#FF3131';
}

function renderCard(l) {
  const historyText = l.last_interaction_notes ? l.last_interaction_notes.substring(0, 60) + (l.last_interaction_notes.length > 60 ? '...' : '') : '-';
  const lastDate = l.last_interaction_date ? new Date(l.last_interaction_date).toLocaleString('de-DE').substring(0, 16) : '';
  const tokenStatus = getTokenStatus(l.token_created_at, l.token_used_at, l.tunnel);
  const tokenStatusLabel = { active: 'Aktiv', used: 'Verwendet', invalid: 'Ungültig', none: '' }[tokenStatus.status] || '';
  const displayScore = l.psi_mobile_score || l.last_score;

  return `<a href="project.php?id=${l.id}" style="text-decoration:none;color:inherit;display:block;border:1px solid #000;padding:16px;margin-bottom:12px;background:#fff;transition:background 0.2s;cursor:pointer;">
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
      <div style="flex:1;">
        <div style="font-family:var(--font-mono);font-size:11px;color:#666;text-transform:uppercase;margin-bottom:4px;">Firma</div>
        <div style="font-weight:700;font-size:16px;margin-bottom:8px;">${esc(l.customer_name)}</div>
        <div style="font-family:var(--font-mono);font-size:11px;color:#666;text-transform:uppercase;margin-bottom:4px;">Website</div>
        <div style="font-weight:600;font-size:14px;margin-bottom:8px;word-break:break-all;">${esc(l.target_url)}</div>
        <div style="font-family:var(--font-mono);font-size:11px;color:#666;text-transform:uppercase;margin-bottom:4px;">Kontakt</div>
        <div style="font-size:12px;color:#333;margin-bottom:4px;">${esc(l.email || '–')}</div>
      </div>
      ${tokenStatus.status !== 'none' ? `<div style="background:${tokenStatus.color};padding:6px 10px;border-radius:2px;font-size:11px;font-weight:600;color:#000;white-space:nowrap;margin-left:12px;"><div style="font-size:9px;color:#333;">${tokenStatusLabel}</div><div>${tokenStatus.date}</div></div>` : ''}
    </div>

    ${displayScore ? `<div style="width:32px;height:32px;background:${getLHColor(displayScore)};border:1px solid #000;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:bold;color:#fff;margin-bottom:8px;">${displayScore}</div>` : ''}

    ${l.budget ? `<div style="font-family:var(--font-mono);font-size:11px;font-weight:bold;margin-bottom:8px;text-transform:uppercase;">Wert: ${parseFloat(l.budget).toLocaleString('de-DE', {style: 'currency', currency: 'EUR'})}</div>` : ''}

    <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
      ${r4StatusCockpit(r4StageStates(l), 'card')}
      ${r4KanalBadge(l.channel || 'lead')}
    </div>

    <div style="display:flex;gap:12px;align-items:start;padding-top:12px;border-top:1px solid #f0f0f0;font-size:12px;">
      <div style="flex:1;">
        <div style="color:#c00;font-weight:600;font-family:var(--font-mono);margin-bottom:4px;">${lastDate || '-'}</div>
        <div style="color:#666;line-height:1.4;">${esc(historyText)}</div>
      </div>
    </div>
  </a>`;
}

function setFilter(filter) {
  currentFilter = filter;
  render();
}

function openModal() {
  document.getElementById('mFirma').value = '';
  document.getElementById('mEmail').value = '';
  document.getElementById('mUrl').value = '';
  document.getElementById('mNotiz').value = '';
  document.getElementById('mSchutt').value = '';
  document.getElementById('mTunnel').value = 'anfrage';
  document.getElementById('mChannel').value = 'lead';
  document.getElementById('mAlertLevel').value = 'normal';
  document.getElementById('mCustomerSelect').value = '';
  document.getElementById('modalOverlay').classList.add('open');
  loadCustomersInModal();
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}

function loadCustomersInModal() {
  const select = document.getElementById('mCustomerSelect');
  apiFetch({}, 'get_customers')
    .then(json => {
      if (json.success && json.data) {
        customersData = json.data;
        const html = json.data.map(c => `<option value="${c.id}">${c.customer_name}</option>`).join('');
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
      document.getElementById('mMobile').value = customer.phone_mobile || '';
      document.getElementById('mAddress').value = customer.address || '';
      document.getElementById('mCity').value = customer.city || '';
      document.getElementById('mPostal').value = customer.postal_code || '';
      window.mAddressLat = customer.latitude || null;
      window.mAddressLon = customer.longitude || null;
    }
  } else {
    document.getElementById('mMobile').value = '';
    document.getElementById('mPhone').value = '';
    document.getElementById('mAddress').value = '';
    document.getElementById('mCity').value = '';
    document.getElementById('mPostal').value = '';
    window.mAddressLat = null;
    window.mAddressLon = null;
  }
}

function formatURL(url) {
  url = url.trim();
  if (!url) return '';
  if (!url.includes('://')) {
    url = 'https://' + url;
  }
  return url;
}

async function saveLead() {
  const name = document.getElementById('mFirma').value.trim();
  let url = document.getElementById('mUrl').value.trim();
  if (!name || !url) { showError('Firma und URL erforderlich'); return; }
  url = formatURL(url);

  const btn = event.target;
  btn.textContent = '…';
  btn.disabled = true;

  try {
    let customerId = document.getElementById('mCustomerSelect').value || null;

    const customerData = {
      action: 'save_customer',
      id: customerId,
      customer_name: name,
      email: document.getElementById('mEmail').value.trim(),
      phone: document.getElementById('mMobile').value.trim(),
      address: document.getElementById('mAddress').value.trim(),
      city: document.getElementById('mCity').value.trim(),
      postal_code: document.getElementById('mPostal').value.trim(),
      latitude: window.mAddressLat || null,
      longitude: window.mAddressLon || null
    };

    const custJson = await postAction(customerData);
    if (custJson.success) {
      customerId = custJson.data.id;
    } else {
      showError('Kunde konnte nicht erstellt werden');
      return;
    }

    const json = await postAction({
      action: 'save',
      id: null,
      customer_id: customerId,
      customer_name: name,
      target_url: url,
      tunnel: document.getElementById('mTunnel').value,
      channel: document.getElementById('mChannel').value,
      alert_level: document.getElementById('mAlertLevel').value,
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
    const json = await postAction({
      action: 'save_customer',
      customer_name: name,
      email: document.getElementById('cEmail').value.trim(),
      phone: document.getElementById('cPhone').value.trim(),
      address: document.getElementById('cAddress').value.trim(),
      city: document.getElementById('cCity').value.trim(),
      postal_code: document.getElementById('cPostal').value.trim()
    });

    if (json.success) {
      closeCustomerModal();
      loadCustomersInModal();
      showError('✓ Kunde erstellt');
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
  const headers = ['ID','Firma','URL','Phase','Alert Level'];
  const rows = leads.map(l => [l.id, l.customer_name, l.target_url, PHASES[l.tunnel] || l.tunnel, l.alert_level].map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(';'));
  const csv = '﻿' + [headers.join(';'), ...rows].join('\n');
  const a = Object.assign(document.createElement('a'), { href: 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv), download: 'crm-' + new Date().toISOString().split('T')[0] + '.csv' });
  a.click();
}

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function showLoading(v) { document.getElementById('loadingIndicator').style.display = v ? '' : 'none'; }
function showError(msg) { const el = document.getElementById('errorIndicator'); el.textContent = msg; el.style.display = ''; }
function clearError() { document.getElementById('errorIndicator').style.display = 'none'; }

function initAddressAutocomplete() {
  const wrapper = document.querySelector('gmpx-place-autocomplete[for="mAddress"]');
  if (!wrapper) return;

  initPlacePicker(wrapper, {
    company: 'mFirma',
    street: 'mAddress',
    city: 'mCity',
    postal: 'mPostal',
    website: 'mUrl',
    lat: (val) => { window.mAddressLat = val; },
    lon: (val) => { window.mAddressLon = val; }
  });
}

const originalOpenModal = window.openModal;
window.openModal = function() {
  originalOpenModal();
  setTimeout(() => initAddressAutocomplete(), 50);
};

window.mAddressLat = null;
window.mAddressLon = null;

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeCustomerModal(); } });

(async () => { await loadLeads(); })();
</script>

<script src="assets/place-picker.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= getenv('GOOGLE_MAPS_KEY') ?>&libraries=places&loading=async" defer></script>
<script type="module" src="https://unpkg.com/@googlemaps/extended-component-library"></script>s</body>
</html>