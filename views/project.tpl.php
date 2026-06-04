<!DOCTYPE html>
<html lang="de">
<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Projekt: <?= htmlspecialchars($project['customer_name']) ?></title>
  <link rel="stylesheet" href="style-crm.css">
  <style>
    
    .btn-square { width: 48px; min-width: 48px; height: 48px; padding: 0; display: flex; align-items: center; justify-content: center; background: #000; color: #fff; border: 1px solid #000; cursor: pointer; font-family: var(--font-mono); flex-shrink: 0; }
    .btn-square:hover { background: #333; }
    
    .box { border: 1px solid #000; padding: 12px; margin-bottom: 16px; }
    .btn { background: #000; color: #fff; padding: 10px 16px; border: none; cursor: pointer; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; min-height: 40px; }
    .btn:hover { background: #333; }
    
    .interaction { border-bottom: 1px solid #ccc; padding: 12px 0; font-size: 13px; }
    .interaction:last-child { border-bottom: none; }
    .interaction-meta { color: #666; font-size: 11px; margin-bottom: 4px; }
    
    
    .action-row { display: flex; flex-direction: row; justify-content: space-between; align-items: flex-start; gap: 32px; width: 100%; margin: 16px 0 20px 0; padding: 0; }
    .action-wrapper { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; width: auto; }
    
    .action-btn-square { width: 72px; height: 72px; aspect-ratio: 1 / 1; display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; background: #0d7377; color: #fff; border: none; cursor: pointer; font-family: var(--font-mono); font-weight: bold; padding: 6px; margin: 0; box-sizing: border-box; }
    .btn-label { font-size: 9px; font-weight: normal; line-height: 1; margin-bottom: auto; }
    .btn-icon { font-size: 40px; font-weight: bold; margin-left: auto; margin-right: auto; margin-top: auto; width: 100%; text-align: center; }
    .action-btn-square:hover { opacity: 0.9; }
    .action-btn-square:active { transform: scale(0.95); }
    
    .action-btn-square.lh-square { background: #0d8659; color: #fff; }
    .action-btn-square.lh-square.yellow { background: #FF9529; color: #fff; }
    .action-btn-square.lh-square.orange { background: #FF9529; color: #fff; }
    .action-btn-square.lh-square.red { background: #FF3131; color: #fff; }
    
    #sendTokenBtn { background: #000; color: #fff; font-size: 32px; }
    
    .led { width: 32px; height: 16px; border-radius: 2px; display: block; position: absolute; top: -24px; right: 0; background: #bbb; border: 1px solid #999; box-shadow: inset 0 1px 2px rgba(255,255,255,0.5), 0 2px 4px rgba(0,0,0,0.2); transition: background 0.3s, box-shadow 0.3s; }
    .led.green { background: #10b981; box-shadow: inset 0 1px 2px rgba(255,255,255,0.3), 0 0 8px rgba(16,185,129,0.6), 0 2px 4px rgba(0,0,0,0.3); }
    .led.red { background: #ef4444; box-shadow: inset 0 1px 2px rgba(255,255,255,0.3), 0 0 8px rgba(239,68,68,0.6), 0 2px 4px rgba(0,0,0,0.3); }
    .action-button-wrapper { position: relative; display: inline-block; margin-bottom: 12px; }
    
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
    
    <div style="font-family: monospace; font-size: 14px; font-weight: bold; margin-bottom: 16px; border-bottom: 1px solid #000; padding-bottom: 8px;">
      PROJEKT: <?= htmlspecialchars($project['customer_name']) ?>
    </div>

    <div class="action-row">
      <div style="display: flex; gap: 24px;">
        <div class="action-wrapper">
          <span class="status-led" id="lhLed"></span>
          <button class="action-btn-square lh-square" id="lhSquare" title="Klick für PSI-Messung">
            <span class="btn-label">Score/PSI</span>
            <span class="btn-icon">-</span>
          </button>
        </div>
        <div class="action-wrapper">
          <span class="status-led" id="tokenLed"></span>
          <button class="action-btn-square" id="sendTokenBtn" style="background: #000;">
            <span class="btn-label">Token</span>
            <span class="btn-icon">Core</span>
          </button>
        </div>
      </div>
    </div>

    <?php if (!empty($project['secret_token'])):
      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
      $client_link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/update.php?token=' . $project['secret_token'];
    ?>
      <div style="margin-top: 16px; padding: 12px; border: 1px dashed #000; background: #fafafa; margin-bottom: 24px;">
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

    <div style="border-top: 1px solid #000; padding-top: 12px;">
      <form id="noteForm" style="margin-bottom: 16px;">
        <textarea id="noteContent" rows="2" style="width: 100%; box-sizing: border-box; border: 1px solid #000; padding: 8px; font-family: var(--font-mono); font-size: 12px;" placeholder="Neue Notiz..."></textarea>
        <button type="submit" class="btn" style="margin-top: 8px; width: 100%;">Notiz speichern</button>
      </form>
      <div id="interactionsList">
        <?php foreach ($interactions as $i): ?>
          <div class="interaction">
            <div class="interaction-meta"><?= htmlspecialchars($i['created_at']) ?> — [<?= htmlspecialchars($i['type']) ?>]</div>
            <div><?= nl2br(htmlspecialchars($i['content'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('noteForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const content = document.getElementById('noteContent').value.trim();
  if (!content) return;
  
  const res = await fetch('api_interactions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project_id: <?= $currentProjectId ?>, type: 'Notiz', content: content })
  });
  if (res.ok) location.reload();
});

function getLHSquareColor(score) {
  if (score >= 90) return 'green';
  if (score >= 75) return 'yellow';
  if (score >= 50) return 'orange';
  return 'red';
}

function showLED(ledElementId, success = true) {
  const led = document.getElementById(ledElementId);
  if (!led) return;
  led.style.backgroundColor = success ? '#0d8659' : '#FF3131';
  setTimeout(() => { led.style.backgroundColor = '#d1d5db'; }, 3000);
}

function addInteractionNote(projectId, type, content) {
  fetch('api_interactions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project_id: projectId, type: type, content: content })
  }).catch(e => console.error('Note error:', e));
}

function getCurrentTime() {
  const now = new Date();
  return `${now.getDate()}.${now.getMonth() + 1}. ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
}

// Token Handler — Erzeugung ohne E-Mail-Versand
document.getElementById('sendTokenBtn').addEventListener('click', async (e) => {
  e.preventDefault();
  const btn = e.target.closest('button');
  btn.disabled = true;

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'generate_token', project_id: <?= $currentProjectId ?> })
    });
    const json = await response.json();

    if (json.success && json.token) {
      const tokenLink = `${window.location.origin}/update.php?token=${json.token}`;
      navigator.clipboard.writeText(tokenLink).catch(e => console.error('Clipboard error:', e));
      showLED('tokenLed', true);
      addInteractionNote(<?= $currentProjectId ?>, 'Aktion', getCurrentTime() + ' — Token generiert');
      setTimeout(() => location.reload(), 1000);
    } else {
      showLED('tokenLed', false);
      addInteractionNote(<?= $currentProjectId ?>, 'Fehler', getCurrentTime() + ' — Token-Generierung fehlgeschlagen');
    }
  } catch (e) {
    showLED('tokenLed', false);
    addInteractionNote(<?= $currentProjectId ?>, 'Fehler', getCurrentTime() + ' — Token Error: ' + e.message);
  } finally {
    btn.disabled = false;
  }
});

// DIREKTE GOOGLE-MESSUNG (KOMPLETT BEREINIGT)
document.getElementById('lhSquare').addEventListener('click', async (e) => {
  e.preventDefault();
  const square = e.target.closest('.action-btn-square');
  square.querySelector('.btn-icon').innerText = "⟳";
  square.className = 'action-btn-square lh-square';
  
  const targetUrl = '<?= !empty($project['target_url']) ? $project['target_url'] : '' ?>';
  const apiKey = '<?= $lighthouseKey ?>'; 

  if (!targetUrl) {
    alert('Keine Ziel-URL für dieses Projekt hinterlegt.');
    square.querySelector('.btn-icon').innerText = "-";
    return;
  }

  const psiUrl = `https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=${encodeURIComponent(targetUrl)}&key=${apiKey}&strategy=mobile`;

  try {
    const res = await fetch(psiUrl);
    const json = await res.json();

    if (json.lighthouseResult) {
      const score = Math.round(json.lighthouseResult.categories.performance.score * 100);
      square.querySelector('.btn-icon').innerText = score;
      square.className = 'action-btn-square lh-square ' + getLHSquareColor(score);
      showLED('lhLed', true);

      const timeStr = getCurrentTime();

      // 1. Interaktion in der Historie speichern
      await fetch('api_interactions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          project_id: <?= $currentProjectId ?>, 
          type: 'Aktion', 
          content: timeStr + ' — PSI-Messung durchgeführt' 
        })
      });

      // 2. Score im Projekt updaten via api_interactions
      await fetch('api_interactions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          project_id: <?= $currentProjectId ?>, 
          action: 'save_score', 
          score: score 
        })
      });

      // 3. Meilenstein-Bonus bei Top-Werten
      if (score >= 90) {
        await fetch('api_interactions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            project_id: <?= $currentProjectId ?>, 
            type: 'Meilenstein', 
            content: '🏆 Elite 90+ Score: ' + score 
          })
        });
      }

      setTimeout(() => location.reload(), 1000);
    } else {
      square.querySelector('.btn-icon').innerText = "-";
      showLED('lhLed', false);
      addInteractionNote(<?= $currentProjectId ?>, 'Fehler', getCurrentTime() + ' — Lighthouse Messung fehlgeschlagen');
    }
  } catch (err) {
    square.querySelector('.btn-icon').innerText = "-";
    showLED('lhLed', false);
    addInteractionNote(<?= $currentProjectId ?>, 'Fehler', getCurrentTime() + ' — Lighthouse API Error: ' + err.message);
  }
});

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
    square.style.width = '22px';
    square.style.height = '22px';
    square.style.border = '1px solid #000';
    square.style.display = 'flex';
    square.style.alignItems = 'center';
    square.style.justifyContent = 'center';
    square.style.fontSize = '9px';
    square.style.fontWeight = 'bold';
    square.style.background = i <= phaseIdx ? colors[i] : '#eee';
    square.style.color = i <= phaseIdx ? '#fff' : '#ccc';
    square.textContent = String(i + 1);
    container.appendChild(square);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  renderPhaseSquares();
  const lastScore = <?= $project['last_score'] !== null ? $project['last_score'] : 'null' ?>;
  if (lastScore !== null) {
    const square = document.getElementById('lhSquare');
    square.querySelector('.btn-icon').innerText = lastScore;
    square.className = 'action-btn-square lh-square ' + getLHSquareColor(lastScore);
  }
});
</script>
</body>
</html>