<?php
/**
 * REVISION100™ — SYSTEM INITIALISIERUNG, ROUTING, CRM-INTEGRATION & ADMIN-GATEWAY
 * Sende-Protokoll via PHPMailer & SQLite-Direct-Inject // Inklusive nativem CRM-Login
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Native Session-Validierung Ihres CRMs einbinden
require_once __DIR__ . '/session_handler.php';

// Falls der Admin bereits eingeloggt ist und das Login-Formular aufruft, direkt weiterleiten
if (isset($_GET['action']) && $_GET['action'] === 'check_session') {
    if (function_exists('is_logged_in') && is_logged_in()) {
        echo json_encode(['logged' => true]);
    } else {
        echo json_encode(['logged' => false]);
    }
    exit;
}

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

// 1. ROUTING-WEICHE (DYNAMIC CONTENT INJECTION)
$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$request_path = parse_url($request_uri, PHP_URL_PATH);
$request_path = trim($request_path, '/');

$page_title = "REVISION100™ — Quelltext-Sanierung bei Google-Rankingverlust";
$dynamic_keyword = "Google-Rankingverlust";

if (!empty($request_path)) {
    // Sanitization & Mapping
    $slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $request_path);
    if ($slug === 'sichtbarkeitsverlust' || $slug === 'sichtbarkeit') {
        $dynamic_keyword = "Sichtbarkeitsverlust";
    } elseif ($slug === 'umsatzeinbruch' || $slug === 'umsatz') {
        $dynamic_keyword = "Umsatzeinbruch";
    } elseif ($slug === 'core-update' || $slug === 'update') {
        $dynamic_keyword = "Google Core-Update";
    } elseif ($slug === 'index-fehler' || $slug === 'deindexierung') {
        $dynamic_keyword = "Indexierungs-Fehlern";
    }
}

// 2. LEAD-ERFASSUNG (DIRECT SQLITE INJECT)
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_lead') {
    $target_url = filter_var($_POST['target_url'] ?? '', FILTER_SANITIZE_URL);
    $contact_mail = filter_var($_POST['contact_mail'] ?? '', FILTER_VALIDATE_EMAIL);

    if (empty($target_url) || empty($contact_mail)) {
        $error_msg = "Bitte füllen Sie beide Pflichtfelder aus.";
    } else {
        try {
            $dbPath = __DIR__ . '/data/rockets.db';
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Kunde & Projekt anlegen
            $stmt = $db->prepare("INSERT INTO customers (name, email, created_at) VALUES (?, ?, datetime('now'))");
            $stmt->execute(['Anonyme Anfrage', $contact_mail]);
            $customer_id = $db->lastInsertId();

            $secret_token = bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, secret_token, created_at, last_interaction) VALUES (?, ?, ?, 'anfrage', ?, datetime('now'), datetime('now'))");
            $stmt->execute([$customer_id, 'Anonyme Anfrage', $target_url, $secret_token]);

            // Benachrichtigung senden
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'send.one.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'system@revision100.de';
            $mail->Password   = 'v,W69-A;E_8m';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('system@revision100.de', 'Revision100 System');
            $mail->addAddress('timo@rockets-media.de');

            $mail->isHTML(true);
            $mail->Subject = "🆕 Neue Audit-Anfrage: " . $target_url;
            $mail->Body    = "<h3>Neue Anfrage registriert</h3>"
                           . "<strong>URL:</strong> " . htmlspecialchars($target_url) . "<br>"
                           . "<strong>E-Mail:</strong> " . htmlspecialchars($contact_mail) . "<br>";

            $mail->send();
            $success_msg = "✓ System-Eintrag erfolgreich abgeschlossen. Ihre URL wurde eingereiht.";
        } catch (Exception $e) {
            $error_msg = "Fehler beim SMTP-Versand: " . $mail->ErrorInfo;
        } catch (PDOException $e) {
            $error_msg = "Datenbank-Fehler: " . $e->getMessage();
        }
    }
}

// 3. ANMELDE-LOGIK FÜR DEN INTERNEN BEREICH (CRM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crm_login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $dbPath = __DIR__ . '/data/rockets.db';
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            header('Location: crm.php');
            exit;
        } else {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?error=1');
            exit;
        }
    } catch (PDOException $e) {
        die("Kritischer Systemfehler bei Authentifizierung.");
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        :root {
            --bg-dark: #111111;
            --border-dark: #333333;
            --text-main: #ffffff;
            --text-muted: #aaaaaa;
            --accent-green: #00ff66;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg-dark) !important;
            color: var(--text-main) !important;
            font-family: var(--font-sans) !important;
            overflow-x: hidden !important;
        }

        .page-wrapper {
            max-width: 100% !important;
            width: 100% !important;
            min-height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
            padding: 0 32px !important;
        }

        header {
            background: var(--bg-dark) !important;
            border-bottom: 1px solid var(--border-dark) !important;
            padding: 45px 0 35px 0 !important;
            margin-bottom: 40px !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px !important;
            width: 100% !important;
        }

        .brand-wrapper {
            display: flex !important;
            align-items: center !important;
            gap: 0 !important;
        }

        .brand-name {
            font-family: var(--font-mono) !important;
            color: var(--text-main) !important;
            font-size: 32px !important;
            font-weight: 700 !important;
            letter-spacing: -1px !important;
            line-height: 1.0 !important;
            margin: 0 !important;
        }

        .brand-name span.tm-size {
            font-size: 14px !important;
            vertical-align: super !important;
            font-weight: 400 !important;
        }

        .header-claim {
            color: var(--text-muted) !important;
            font-size: 14px !important;
            font-family: var(--font-mono) !important;
            margin: 4px 0 0 0 !important;
        }

        .status-sub-line {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            margin-top: 4px !important;
        }

        .status-led-audit {
            width: 20px !important;
            height: 6px !important;
            background-color: var(--accent-green) !important;
            border-radius: 0px !important;
            box-shadow: 0 0 12px var(--accent-green) !important;
            animation: pulseGlow 2s infinite ease-in-out !important;
            display: inline-block !important;
        }

        .status-text-audit {
            font-family: var(--font-mono) !important;
            font-size: 11px !important;
            color: #ffffff !important;
            opacity: 0.4 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            display: inline-block !important;
        }

        @keyframes pulseGlow {
            0% { opacity: 0.5; box-shadow: 0 0 4px #00ff66; }
            50% { opacity: 1; box-shadow: 0 0 12px #00ff66; }
            100% { opacity: 0.5; box-shadow: 0 0 4px #00ff66; }
        }

        main {
            flex: 1 !important;
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 40px !important;
        }

        .grid-blocks {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 20px !important;
            margin-bottom: 30px !important;
        }

        .info-box {
            border: 1px solid var(--border-dark) !important;
            padding: 24px !important;
            background: #161616 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
        }

        .box-icon {
            display: flex !important;
            align-items: center !important;
            height: 24px !important;
            color: var(--text-main) !important;
        }

        .info-box h2 {
            font-family: var(--font-mono) !important;
            font-size: 16px !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            color: var(--text-main) !important;
            margin: 0 !important;
        }

        .info-box p {
            font-size: 14px !important;
            line-height: 1.5 !important;
            color: var(--text-muted) !important;
            margin: 0 !important;
        }

        .form-section {
            border: 1px solid var(--border-dark) !important;
            padding: 32px !important;
            background: #161616 !important;
            width: 100% !important;
            margin-bottom: 40px !important;
        }

        .form-section h2 {
            font-family: var(--font-mono) !important;
            font-size: 18px !important;
            text-transform: uppercase !important;
            margin-bottom: 20px !important;
            letter-spacing: 1px;
        }

        .form-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 20px !important;
        }

        .form-group {
            display: flex !important;
            flex-direction: column !important;
            gap: 6px !important;
        }

        label {
            font-family: var(--font-mono) !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            color: var(--text-muted) !important;
        }

        input {
            background: var(--bg-dark) !important;
            border: 1px solid var(--border-dark) !important;
            color: var(--text-main) !important;
            padding: 12px !important;
            font-family: var(--font-sans) !important;
            font-size: 14px !important;
            width: 100% !important;
        }

        input:focus {
            border-color: var(--text-muted) !important;
            outline: none !important;
        }

        button.btn-submit {
            background: var(--text-main) !important;
            color: var(--bg-dark) !important;
            border: none !important;
            padding: 14px 24px !important;
            font-family: var(--font-mono) !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            cursor: pointer !important;
            margin-top: 10px !important;
            width: auto !important;
            align-self: flex-start !important;
        }

        .alert {
            padding: 16px !important;
            font-family: var(--font-mono) !important;
            font-size: 14px !important;
            margin-bottom: 20px !important;
            border: 1px solid transparent !important;
        }

        .alert-success { background: #0d2b1a !important; border-color: #1fa47f !important; color: #00ff66 !important; }
        .alert-error { background: #331414 !important; border-color: #cc3a21 !important; color: #ff6666 !important; }

        footer {
            border-top: 1px solid var(--border-dark) !important;
            padding: 30px 0 !important;
            font-family: var(--font-mono) !important;
            font-size: 12px !important;
            color: var(--text-muted) !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 10px !important;
        }

        .footer-links a { color: var(--text-muted) !important; text-decoration: none !important; }
        .footer-links a:hover { color: var(--text-main) !important; }

        .modal-mask {
            position: fixed !important;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85) !important;
            display: none;
            justify-content: center !important;
            align-items: center !important;
            z-index: 9999 !important;
        }

        .modal-box {
            background: #161616 !important;
            border: 1px solid var(--border-dark) !important;
            padding: 32px !important;
            width: 100% !important;
            max-width: 400px !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 16px !important;
        }

        @media (max-width: 768px) {
            header {
                padding: 25px 0 19px 0 !important;
                margin-bottom: 22px !important;
            }
            .brand-name { 
                font-size: 24px !important; 
            }
            .header-claim {
                font-size: 13px !important;
            }
            body { padding: 0 !important; overflow-x: hidden !important; }
            .page-wrapper { width: 100% !important; padding: 0 16px !important; overflow-x: hidden !important; }
            
            .grid-blocks {
                grid-template-columns: 1fr !important;
                gap: 16px !important;
                width: 100% !important;
            }
            .form-grid {
                grid-template-columns: 1fr !important;
            }
            .form-section {
                padding: 20px !important;
            }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">

        <header>
            <div class="brand-wrapper">
                <span class="brand-name">Revision100<span class="tm-size">™</span></span>
            </div>
            <div class="header-claim">
                // Quelltext-Sanierung bei <?php echo htmlspecialchars($dynamic_keyword); ?>
            </div>
            <div class="status-sub-line">
                <div class="status-led-audit"></div>
                <span class="status-text-audit">System bereit für Code Audit</span>
            </div>
        </header>

        <main>
            
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-error"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <section class="grid-blocks">
                <div class="info-box">
                    <div class="box-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    </div>
                    <h2>01 // Status Quo</h2>
                    <p>Moderne Suchmaschinen-Crawler und AI-Agents bewerten Quelltexte nach mathematischer Effizienz, strikter Semantik und barrierefreier Lade-Infrastruktur. Legacy-Code blockiert die organische Sichtbarkeit fundamental.</p>
                </div>
                <div class="info-box">
                    <div class="box-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    </div>
                    <h2>02 // Deep Audit</h2>
                    <p>Wir reduzieren, sanieren und optimieren Code-Strukturen auf ein kompromissloses 100% Lighthouse-Äquivalent. Kein optischer Schmuck, kein Marketing-Sprech. Reine, maschinenlesbare Performance.</p>
                </div>
                <div class="info-box">
                    <div class="box-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    </div>
                    <h2>03 // System-Intervention</h2>
                    <p>Über das untenstehende Interface initialisieren Sie das manuelle Quelltext-Audit. Die Auswertung erfolgt direkt über unsere interne Werkbank und liefert die harten Fakten für Ihre IT-Infrastruktur.</p>
                </div>
            </section>

            <section class="form-section">
                <h2>Quelltext-Audit einleiten</h2>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($request_path ? '/' . $request_path : '')); ?>" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                    <input type="hidden" name="action" value="submit_lead">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="target_url">Ziel-URL (Pflichtfeld)</label>
                            <input type="url" id="target_url" name="target_url" placeholder="https://www.ihre-website.de" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_mail">E-Mail-Adresse (Pflichtfeld)</label>
                            <input type="email" id="contact_mail" name="contact_mail" placeholder="name@unternehmen.de" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Audit-Prozess starten</button>
                </form>
            </section>

        </main>

        <footer>
            <div>Revision100™ ein Service von Timo E. Pohlhaus – <a href="https://timpool.de" style="color: var(--text-muted); text-decoration: none;">timpool.de</a></div>
            <div class="manifest-banner" style="max-width: none; font-size: 11px; color: var(--text-muted); margin-top: 5px;">Manifest: Kein Schmuck. Nur System. Kein Marketing-Sprech. Nur Daten.</div>
            <div class="footer-links">
                <a href="/datenschutz">Datenschutz</a> // 
                <a href="/impressum">Impressum</a> // 
                <a href="#" id="crmLink" onclick="handleCrmGateway(event)" style="color: #2c3a3c;">[System]</a>
            </div>
        </footer>

    </div>

    <div id="crmModal" class="modal-mask" onclick="toggleCrmModal(false)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="font-family: var(--font-mono); font-size: 14px; text-transform: uppercase; border-bottom: 1px solid var(--border-dark); padding-bottom: 8px; margin-bottom: 8px;">
                System-Zentrale Authentifizierung
            </div>
            <?php if (isset($_GET['error'])): ?>
                <div style="color: #ff6666; font-family: var(--font-mono); font-size: 12px; margin-bottom: 8px;">✓ Zugriff verweigert.</div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                <input type="hidden" name="action" value="crm_login">
                <div class="form-group">
                    <label>Kennung</label>
                    <input type="text" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label>Passwort</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-submit" style="width: 100%; margin-top: 4px;">Anmelden</button>
            </form>
        </div>
    </div>

    <script>
    function toggleCrmModal(show) {
        document.getElementById('crmModal').style.display = show ? 'flex' : 'none';
    }

    async function handleCrmGateway(e) {
        e.preventDefault();
        try {
            const response = await fetch('?action=check_session');
            const data = await response.json();
            if (data.logged) {
                window.location.href = 'crm.php';
            } else {
                toggleCrmModal(true);
            }
        } catch (error) {
            toggleCrmModal(true);
        }
    }

    if (window.location.search.indexOf('error=1') !== -1) {
        toggleCrmModal(true);
    }
    </script>
</body>
</html>