<?php
// VIP-Kanal-Erkennung für Infobox
$current_tunnel = $project['tunnel'] ?? 'standard';
$is_vip_channel = in_array($current_tunnel, ['vip', 'anfrage', 'abgeschaltet']);
$is_terminated = $current_tunnel === 'abgeschaltet';
$tunnel_labels = [
    'vip' => 'VIP-Kanal',
    'anfrage' => 'Anfrage',
    'bewertet' => 'Bewertet',
    'bereit' => 'Bereit',
    'kontakt' => 'Kontakt',
    'abgeschlossen' => 'Abgeschlossen',
    'abgeschaltet' => 'Terminated',
];
$tunnel_display = $tunnel_labels[$current_tunnel] ?? ucfirst($current_tunnel);
?><!DOCTYPE html>
<html lang="de">
<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
  <title>Projekt: <?= htmlspecialchars($project['target_url']) ?></title>
  <link rel="stylesheet" href="style-crm.css">
  <link rel="stylesheet" href="r400-status.css">
  <style>

    .btn-square { width: 48px; min-width: 48px; height: 48px; padding: 0; display: flex; align-items: center; justify-content: center; background: #000; color: #fff; border: 1px solid #000; cursor: pointer; font-family: var(--font-mono); flex-shrink: 0; }
    .btn-square:hover { background: #333; }

    .btn { background: #000; color: #fff; padding: 10px 16px; border: none; cursor: pointer; font-family: var(--font-mono); font-weight: bold; text-transform: uppercase; min-height: 40px; }
    .btn:hover { background: #333; }
    
    .interaction { border-bottom: 1px solid #ccc; padding: 12px 0; font-size: 13px; }
    .interaction:last-child { border-bottom: none; }
    .interaction-meta { color: #666; font-size: 11px; margin-bottom: 4px; }


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

  </style>
</head>
<body>

<?php include __DIR__ . '/header.tpl.php'; ?>

<div class="crm-layout">
  <?php include __DIR__ . '/nav.tpl.php'; ?>
</div>

<div class="container">
  <div class="content"><div class="section-title">PROJEKT: <?= htmlspecialchars($project['target_url']) ?></div>

<div class="action-row" style="display: flex; gap: 24px; margin: 0 0 20px 0; padding: 0; align-items: flex-start; flex-wrap: wrap;">
      <div class="action-wrapper" style="position: relative; width: 72px; height: 72px;">
        <span class="led" id="lhLed" style="width: 12px; height: 6px; border-radius: 0px !important; display: block; position: absolute; top: -12px; left: 0; z-index: 10; background-color: #bbb !important; box-shadow: inset 0 1px 1px rgba(0,0,0,0.3); transition: all 0.2s; pointer-events: none;"></span>
        <button class="action-btn-square lh-square" id="lhSquare" title="Klick für PSI-Messung">
          <span class="btn-label">Score/PSI</span>
          <span class="btn-icon" style="font-size: 32px; line-height: 1.2; margin-top: 4px;">-</span>
        </button>
      </div>
      <div class="action-wrapper" style="position: relative; width: 72px; height: 72px;">
        <span class="led" id="tokenLed" style="width: 12px; height: 6px; border-radius: 0px !important; display: block; position: absolute; top: -12px; left: 0; z-index: 10; background-color: #bbb !important; box-shadow: inset 0 1px 1px rgba(0,0,0,0.3); transition: all 0.2s; pointer-events: none;"></span>
        <button class="action-btn-square" id="sendTokenBtn" style="background: #000; padding: 6px;">
          <span class="btn-label">Token</span>
          <span class="btn-icon" style="font-size: 11px; line-height: 1.2; margin-top: 16px; font-weight: normal; font-family: var(--font-mono);">Core</span>
        </button>
      </div>
      <?php if (!empty($project['secret_token'])):
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $client_link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/update.php?token=' . $project['secret_token'];
      ?>
      <div style="flex: 1; min-width: 200px; padding: 12px; border: 1px dashed #000; background: #fafafa; box-sizing: border-box;">
        <label style="display:block; font-family: var(--font-mono); font-size:11px; text-transform:uppercase; color:#666; margin-bottom:4px;">
          Externer Zugangslink
        </label>
        <div style="display: flex; gap: 8px; align-items: center;">
          <input type="text" value="<?= htmlspecialchars($client_link) ?>" readonly
                 onclick="this.select(); document.execCommand('copy'); alert('Link kopiert!');"
                 style="flex: 1; font-family: var(--font-mono); font-size: 12px; padding: 6px; border: 1px solid #000; background: #fff; cursor: pointer;"
                 title="Klicken zum Kopieren">
          <a href="<?= htmlspecialchars($client_link) ?>" target="_blank"
             style="display: inline-block; padding: 6px 12px; border: 1px solid #000; background: #000; color: #fff; font-family: var(--font-mono); font-size: 12px; text-decoration: none; font-weight: bold;">
            ÖFFNEN ↗
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>

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
  console.log("LED getriggert", ledElementId, success);
  const led = document.getElementById(ledElementId);
  if (!led) return;
  led.className = 'led ' + (success ? 'green' : 'red');
  setTimeout(() => { led.className = 'led'; }, 3000);
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

      // Robuster Clipboard-Fallback für unverschlüsselte IP-Aufrufe
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(tokenLink).catch(e => console.error('Clipboard error:', e));
      } else {
        // Fallback für Nicht-HTTPS / IP-Umgebungen
        const textarea = document.createElement('textarea');
        textarea.value = tokenLink;
        textarea.style.position = 'fixed'; // Verhindert Scroll-Sprüche
        document.body.appendChild(textarea);
        textarea.select();
        try {
          document.execCommand('copy');
        } catch (err) {
          console.error('Fallback Clipboard error:', err);
        }
        document.body.removeChild(textarea);
      }

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

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('lhLed').classList.remove('green', 'red');
  document.getElementById('tokenLed').classList.remove('green', 'red');

  const currentPhase = '<?= htmlspecialchars($project['tunnel']) ?>';
  const lastInteractionDate = '<?= !empty($project['last_interaction_date']) ? htmlspecialchars($project['last_interaction_date']) : '' ?>';

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