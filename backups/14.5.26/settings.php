<?php
/**
 * Revision100 — Settings
 * Passwort-Verwaltung, GSC-Verbindung, System-Konfiguration
 */

require_once __DIR__ . '/session_handler.php';
require_once __DIR__ . '/gsc_api.php';

requireAuthPage();

$error      = '';
$gscError   = '';
$gscSuccess = false;

// ── DB ─────────────────────────────────────────────────────
try {
    $db = new PDO('sqlite:' . __DIR__ . '/data/rockets.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    gsc_db_init($db);
} catch (Exception $e) {
    $db = null;
}

// ── POST handler ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    $formAction = $_POST['form_action'] ?? '';

    // ── Passwort ändern ────────────────────────────────────
    if ($formAction === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']     ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if ($currentPw === '' || $newPw === '' || $confirmPw === '') {
            $error = 'Alle Felder sind erforderlich.';
        } elseif ($newPw !== $confirmPw) {
            $error = 'Neues Passwort und Bestätigung stimmen nicht überein.';
        } elseif (strlen($newPw) < 8) {
            $error = 'Neues Passwort muss mindestens 8 Zeichen haben.';
        } else {
            try {
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !password_verify($currentPw, $user['password_hash'])) {
                    $error = 'Aktuelles Passwort ist falsch.';
                } else {
                    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                       ->execute([
                           password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]),
                           $_SESSION['user_id']
                       ]);

                    $_SESSION = [];
                    if (ini_get('session.use_cookies')) {
                        $p = session_get_cookie_params();
                        setcookie(session_name(), '', time() - 3600,
                            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                    }
                    session_destroy();
                    header('Location: login.php?pw_changed=1');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Datenbankfehler — Passwort konnte nicht geändert werden.';
            }
        }
    }

    // ── GSC Zugangsdaten speichern ─────────────────────────
    if ($formAction === 'gsc_save') {
        gsc_set($db, 'gsc_client_id',     trim($_POST['gsc_client_id']     ?? ''));
        gsc_set($db, 'gsc_client_secret', trim($_POST['gsc_client_secret'] ?? ''));
        gsc_set($db, 'gsc_redirect_uri',  trim($_POST['gsc_redirect_uri']  ?? ''));
        $gscSuccess = true;
    }

    // ── GSC Verbindung trennen ─────────────────────────────
    if ($formAction === 'gsc_disconnect') {
        gsc_set($db, 'gsc_access_token',  '');
        gsc_set($db, 'gsc_refresh_token', '');
        gsc_set($db, 'gsc_token_expires', '0');
        gsc_set($db, 'gsc_connected_at',  '');
    }

    // ── SMTP-Konfiguration speichern ─────────────────────────
    if ($formAction === 'smtp_save') {
        gsc_set($db, 'smtp_host',     trim($_POST['smtp_host']     ?? ''));
        gsc_set($db, 'smtp_port',     trim($_POST['smtp_port']     ?? ''));
        gsc_set($db, 'smtp_username', trim($_POST['smtp_username'] ?? ''));
        gsc_set($db, 'smtp_password', trim($_POST['smtp_password'] ?? ''));
    }
}

// ── GSC Status ─────────────────────────────────────────────
$gscConnected   = $db ? gsc_is_connected($db)                      : false;
$gscConnectedAt = $db ? gsc_get($db, 'gsc_connected_at')           : '';
$gscClientId    = $db ? gsc_get($db, 'gsc_client_id')              : '';
$gscSecret      = $db ? gsc_get($db, 'gsc_client_secret')          : '';
$gscRedirectUri = $db ? gsc_get($db, 'gsc_redirect_uri')           : '';

// ── SMTP Status ────────────────────────────────────────────────
$smtpHost     = $db ? gsc_get($db, 'smtp_host')     : '';
$smtpPort     = $db ? gsc_get($db, 'smtp_port')     : '';
$smtpUsername = $db ? gsc_get($db, 'smtp_username') : '';
$smtpPassword = $db ? gsc_get($db, 'smtp_password') : '';
$smtpConfigured = !empty($smtpHost) && !empty($smtpPort) && !empty($smtpUsername);

// ── Rechnungs-Einstellungen ────────────────────────────────
$isSmallBusiness = $db ? (int)gsc_get($db, 'is_small_business') : 0;

// POST handler für Business-Einstellungen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    if (($_POST['form_action'] ?? '') === 'business_settings') {
        $isSmallBusiness = isset($_POST['is_small_business']) ? 1 : 0;
        gsc_set($db, 'is_small_business', $isSmallBusiness ? '1' : '0');
    }
}

// Suggest redirect URI if not set
if (!$gscRedirectUri) {
    $proto          = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host           = $_SERVER['HTTP_HOST'] ?? '';
    $dir            = rtrim(dirname($_SERVER['REQUEST_URI'] ?? ''), '/');
    $gscRedirectUri = $proto . '://' . $host . $dir . '/gsc_callback.php';
}

// Flash messages from redirect
if (isset($_GET['gsc_ok']))  $gscSuccess = true;
if (isset($_GET['gsc_err'])) $gscError   = match($_GET['gsc_err']) {
    'no_creds'   => 'Client ID oder Redirect URI fehlen — bitte zuerst speichern.',
    'no_code'    => 'Kein Autorisierungs-Code von Google erhalten.',
    'token_fail' => 'Token-Austausch fehlgeschlagen — Client Secret prüfen.',
    'access_denied' => 'Zugriff verweigert — Google-Konto prüfen.',
    default      => 'GSC-Fehler: ' . htmlspecialchars($_GET['gsc_err'], ENT_QUOTES, 'UTF-8'),
};

$username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Revision100 — Einstellungen</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .settings-wrap {
      max-width: 620px;
      margin: 48px auto;
      padding: 0 24px 64px;
    }

    .settings-page-title {
      font-family: var(--font-mono);
      font-size: 9px;
      letter-spacing: .25em;
      text-transform: uppercase;
      color: #888;
      margin-bottom: 24px;
    }

    .settings-section { border: var(--line); margin-bottom: 16px; }

    .settings-section-head {
      border-bottom: var(--line);
      padding: 12px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--paper);
      gap: 12px;
    }

    .settings-section-label {
      display: inline-block;
      background: var(--white);
      padding: 8px 16px;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: .22em;
      text-transform: uppercase;
      font-weight: 900;
      margin-bottom: 20px;
      margin-top: 20px;
    }

    .settings-status-badge {
      font-family: var(--font-mono);
      font-size: 8px;
      letter-spacing: .18em;
      text-transform: uppercase;
      color: #bbb;
      border: 1px solid #ddd;
      padding: 2px 8px;
      white-space: nowrap;
    }

    .settings-status-ok {
      font-family: var(--font-mono);
      font-size: 8px;
      letter-spacing: .15em;
      text-transform: uppercase;
      color: #000;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .settings-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .settings-dot.on  { background: #000; }
    .settings-dot.off { background: #ccc; }

    .settings-body { padding: 20px 18px; }

    .settings-label {
      display: inline-block;
      background: var(--white);
      padding: 6px 12px;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: .2em;
      text-transform: uppercase;
      color: #000;
      margin-bottom: 10px;
      font-weight: 700;
      border-radius: 2px;
      box-decoration-break: clone;
    }

    .settings-input {
      width: 100%;
      border: var(--line);
      padding: 10px 12px;
      font-family: var(--font-sans);
      font-size: 14px;
      background: var(--white);
      outline: none;
      -webkit-appearance: none;
      appearance: none;
      margin-bottom: 14px;
    }
    .settings-input:focus {
      outline: 2px solid var(--black);
      outline-offset: -2px;
    }
    .settings-input:disabled {
      background: #f5f5f5;
      color: #ccc;
      cursor: not-allowed;
      border-color: #ddd;
    }

    .settings-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .settings-row-3 { display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 10px; }

    .settings-foot {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 4px;
    }

    .settings-submit {
      background: var(--black);
      color: var(--white);
      border: none;
      padding: 11px 24px;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: .2em;
      text-transform: uppercase;
      cursor: pointer;
    }
    .settings-submit:hover { background: #222; }

    .settings-submit-ghost {
      background: var(--white);
      color: var(--black);
      border: var(--line);
      padding: 11px 24px;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: .2em;
      text-transform: uppercase;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .settings-submit-ghost:hover { background: var(--paper); }

    .settings-error {
      border: 1px solid #000;
      background: #000;
      color: #fff;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: .08em;
      padding: 10px 14px;
      margin-bottom: 16px;
    }

    .settings-ok {
      border: var(--line);
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: .08em;
      padding: 10px 14px;
      margin-bottom: 16px;
    }

    .settings-frozen { opacity: .35; pointer-events: none; user-select: none; }

    .settings-hint {
      font-family: var(--font-mono);
      font-size: 9px;
      letter-spacing: .1em;
      color: #aaa;
      margin-top: 4px;
      line-height: 1.6;
    }

    .settings-meta {
      font-family: var(--font-mono);
      font-size: 9px;
      letter-spacing: .12em;
      color: #bbb;
      margin-top: 14px;
    }

    .settings-checkbox-row { display: flex; gap: 24px; margin-top: 4px; flex-wrap: wrap; }
    .settings-checkbox-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      cursor: not-allowed;
      background: var(--white);
      padding: 6px 12px;
      border-radius: 2px;
      font-weight: 500;
    }
  </style>
</head>
<body class="settings-page">

<header style="padding:16px 32px;">
  <div class="header-inner" style="padding:0;">
    <a href="/" class="logo" style="background:transparent;border:none;padding:0;display:block;margin-bottom:12px;">
      <svg width="200" height="60" viewBox="0 0 500 150" xmlns="http://www.w3.org/2000/svg" style="display:block;">
        <text x="10" y="55%" dominant-baseline="middle" text-anchor="start" style="font-family: 'Impact', 'Haettenschweiler', 'Arial Narrow Bold', sans-serif; font-weight: 900; font-size: 54px; letter-spacing: -0.9px; fill: black;">REVISION100<tspan font-size="24px">™</tspan></text>
      </svg>
    </a>
    <nav>
      <a href="crm.php" title="Dashboard">
        <svg width="24" height="24" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:-2px;">
          <path d="M 10,50 L 50,20 L 90,50 L 80,50 L 80,85 L 20,85 L 20,50 Z" stroke="currentColor" stroke-width="6" stroke-linejoin="round" fill="none" />
          <rect x="35" y="55" width="30" height="30" stroke="currentColor" stroke-width="5" fill="none" />
        </svg>
      </a>
      <a href="settings.php" class="active" title="Einstellungen">
        <svg width="28" height="28" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="vertical-align:-2px;">
          <path d="M 50,5 L 58,15 L 72,10 L 73,23 L 86,22 L 82,34 L 94,40 L 86,50 L 94,60 L 82,66 L 86,78 L 73,77 L 72,90 L 58,85 L 50,95 L 42,85 L 28,90 L 27,77 L 14,78 L 18,66 L 6,60 L 14,50 L 6,40 L 18,34 L 14,22 L 27,23 L 28,10 L 42,15 Z"
                  fill="currentColor" />
          <circle cx="50" cy="50" r="28" fill="white" />
          <circle cx="50" cy="50" r="20" fill="currentColor" />
          <circle cx="50" cy="50" r="8" fill="white" />
        </svg>
      </a>
      <a href="logout.php" title="Logout">
        <svg width="28" height="28" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:-2px;">
          <path d="M 20,10 L 10,10 L 10,90 L 20,90 M 10,50 L 85,50 M 85,50 L 65,30 M 85,50 L 65,70"
                stroke="currentColor" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
    </nav>
  </div>
</header>

<div class="settings-wrap">

  <div class="settings-page-title">Einstellungen — <?= $username ?></div>

  <!-- ── 1. PASSWORT ÄNDERN ─────────────────────────────── -->
  <div class="settings-section">
    <div class="settings-section-head">
      <span class="settings-section-label">Passwort ändern</span>
    </div>
    <div class="settings-body">
      <?php if ($error !== ''): ?>
        <div class="settings-error">✗ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="POST" action="settings.php" autocomplete="off">
        <input type="hidden" name="form_action" value="change_password">

        <label class="settings-label" for="current_password">Aktuelles Passwort</label>
        <input class="settings-input" type="password" id="current_password"
               name="current_password" autocomplete="current-password" required>

        <div class="settings-row">
          <div>
            <label class="settings-label" for="new_password">Neues Passwort</label>
            <input class="settings-input" type="password" id="new_password"
                   name="new_password" autocomplete="new-password" required minlength="8">
          </div>
          <div>
            <label class="settings-label" for="confirm_password">Bestätigen</label>
            <input class="settings-input" type="password" id="confirm_password"
                   name="confirm_password" autocomplete="new-password" required minlength="8">
          </div>
        </div>

        <button class="settings-submit" type="submit">Passwort ändern →</button>
      </form>
      <div class="settings-meta">Min. 8 Zeichen · Nach Änderung: automatischer Logout</div>
    </div>
  </div>

  <!-- ── 2. GOOGLE SEARCH CONSOLE ───────────────────────── -->
  <div class="settings-section">
    <div class="settings-section-head">
      <span class="settings-section-label">Google Search Console</span>
      <span class="settings-status-ok">
        <span class="settings-dot <?= $gscConnected ? 'on' : 'off' ?>"></span>
        <?php if ($gscConnected): ?>
          Verbunden<?= $gscConnectedAt ? ' · ' . date('d.m.Y', strtotime($gscConnectedAt)) : '' ?>
        <?php else: ?>
          Nicht verbunden
        <?php endif; ?>
      </span>
    </div>
    <div class="settings-body">

      <?php if ($gscError !== ''): ?>
        <div class="settings-error">✗ <?= $gscError ?></div>
      <?php endif; ?>
      <?php if ($gscSuccess): ?>
        <div class="settings-ok">✓ <?= $gscConnected ? 'Verbindung erfolgreich hergestellt.' : 'Zugangsdaten gespeichert.' ?></div>
      <?php endif; ?>

      <form method="POST" action="settings.php" autocomplete="off">
        <input type="hidden" name="form_action" value="gsc_save">

        <label class="settings-label" for="gsc_client_id">Client ID</label>
        <input class="settings-input" type="text" id="gsc_client_id" name="gsc_client_id"
               value="<?= htmlspecialchars($gscClientId, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="123456789-abc.apps.googleusercontent.com" autocomplete="off">

        <label class="settings-label" for="gsc_client_secret">Client Secret</label>
        <input class="settings-input" type="text" id="gsc_client_secret" name="gsc_client_secret"
               value="<?= htmlspecialchars($gscSecret, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="GOCSPX-…" autocomplete="off">

        <label class="settings-label" for="gsc_redirect_uri">Redirect URI</label>
        <input class="settings-input" type="text" id="gsc_redirect_uri" name="gsc_redirect_uri"
               value="<?= htmlspecialchars($gscRedirectUri, ENT_QUOTES, 'UTF-8') ?>">
        <div class="settings-hint">
          Diese URL muss in der Google Cloud Console unter „Autorisierte Weiterleitungs-URIs" eingetragen sein.
        </div>

        <div class="settings-foot" style="margin-top:16px;">
          <button class="settings-submit" type="submit">Speichern</button>
          <a class="settings-submit-ghost" href="gsc_auth.php">Mit Google verbinden →</a>
          <?php if ($gscConnected): ?>
            <form method="POST" action="settings.php" style="margin:0;">
              <input type="hidden" name="form_action" value="gsc_disconnect">
              <button class="settings-submit-ghost" type="submit"
                      onclick="return confirm('GSC-Verbindung trennen?')"
                      style="color:#c00;border-color:#c00;">Verbindung trennen</button>
            </form>
          <?php endif; ?>
        </div>
      </form>

      <div class="settings-meta" style="margin-top:18px;">
        Google Cloud Console → APIs &amp; Dienste → Anmeldedaten → OAuth 2.0-Client-IDs<br>
        Scope: webmasters.readonly · Kein Passwort wird an Google gesendet
      </div>
    </div>
  </div>

  <!-- ── 3. SMTP-KONFIGURATION ──────────────────────────── -->
  <div class="settings-section">
    <div class="settings-section-head">
      <span class="settings-section-label">SMTP-Konfiguration</span>
      <span class="settings-status-ok">
        <span class="settings-dot <?= $smtpConfigured ? 'on' : 'off' ?>"></span>
        <?= $smtpConfigured ? 'Konfiguriert' : 'Nicht konfiguriert' ?>
      </span>
    </div>
    <div class="settings-body">
      <form method="POST" action="settings.php">
        <input type="hidden" name="form_action" value="smtp_save">

        <div class="settings-row-3">
          <div>
            <label class="settings-label" for="smtp_host">SMTP-Host</label>
            <input class="settings-input" type="text" id="smtp_host" name="smtp_host"
                   value="<?= htmlspecialchars($smtpHost, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="mail.provider.de" autocomplete="off">
          </div>
          <div>
            <label class="settings-label" for="smtp_port">Port</label>
            <input class="settings-input" type="number" id="smtp_port" name="smtp_port"
                   value="<?= htmlspecialchars($smtpPort, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="587" autocomplete="off">
          </div>
          <div>
            <label class="settings-label" for="smtp_username">Benutzername</label>
            <input class="settings-input" type="text" id="smtp_username" name="smtp_username"
                   value="<?= htmlspecialchars($smtpUsername, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="hallo@rockets-media.de" autocomplete="off">
          </div>
        </div>

        <label class="settings-label" for="smtp_password">Passwort</label>
        <input class="settings-input" type="password" id="smtp_password" name="smtp_password"
               value="<?= htmlspecialchars($smtpPassword, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="••••••••" autocomplete="off">

        <div class="settings-foot" style="margin-top:16px;">
          <button class="settings-submit" type="submit">Speichern</button>
        </div>
        <div class="settings-hint">
          Für Onboarding-E-Mails und automatische Benachrichtigungen
        </div>
      </form>
    </div>
  </div>

  <!-- ── 4. RECHNUNGS-EINSTELLUNGEN ──────────────────────────── -->
  <div class="settings-section">
    <div class="settings-section-head">
      <span class="settings-section-label">Rechnungs-Einstellungen</span>
    </div>
    <div class="settings-body">
      <form method="POST" action="settings.php">
        <input type="hidden" name="form_action" value="business_settings">

        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; margin-bottom: 16px;">
          <input type="checkbox" name="is_small_business" <?= $isSmallBusiness ? 'checked' : '' ?>
                 style="width: 18px; height: 18px; cursor: pointer;">
          <span>Kleinunternehmer (§ 19 UStG — keine Umsatzsteuer)</span>
        </label>

        <button class="settings-submit" type="submit">Speichern</button>

        <div class="settings-meta">
          Gilt für alle Rechnungen. Ohne Häkchen werden 19% MwSt berechnet.
        </div>
      </form>
    </div>
  </div>

  <!-- ── 5. REPORT-PRÄFERENZEN ──────────────────────────── -->
  <div class="settings-section">
    <div class="settings-section-head">
      <span class="settings-section-label">Report-Präferenzen</span>
      <span class="settings-status-badge">Status: In Vorbereitung</span>
    </div>
    <div class="settings-body settings-frozen">
      <label class="settings-label">Export-Format</label>
      <div class="settings-checkbox-row">
        <label class="settings-checkbox-label"><input type="checkbox" disabled> Markdown (.md)</label>
        <label class="settings-checkbox-label"><input type="checkbox" disabled> PDF (Druck-Dialog)</label>
        <label class="settings-checkbox-label"><input type="checkbox" disabled> CSV</label>
      </div>
    </div>
  </div>

</div>

</body>
</html>
