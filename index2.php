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
    $customer_name = trim($_POST['customer_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($target_url) || empty($customer_name) || empty($email)) {
        $error_msg = "Bitte füllen Sie alle Pflichtfelder aus (URL, Name, E-Mail).";
    } else {
        try {
            $dbPath = __DIR__ . '/data/rockets.db';
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 1. Kunde anlegen oder updaten
            $stmt = $db->prepare("INSERT INTO customers (name, email, phone_mobile, created_at) VALUES (?, ?, ?, datetime('now'))");
            $stmt->execute([$customer_name, $email, $phone]);
            $customer_id = $db->lastInsertId();

            // 2. Projekt anlegen
            $secret_token = bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, secret_token, created_at, last_interaction) VALUES (?, ?, ?, 'anfrage', ?, datetime('now'), datetime('now'))");
            $stmt->execute([$customer_id, $customer_name, $target_url, $secret_token]);
            $project_id = $db->lastInsertId();

            // 3. Initiale Notiz hinterlegen
            if (!empty($message)) {
                $stmt = $db->prepare("INSERT INTO project_interactions (project_id, type, content, created_at) VALUES (?, 'Notiz', ?, datetime('now'))");
                $stmt->execute([$project_id, "Kunden-Nachricht bei Anfrage:\n" . $message]);
            }

            // 4. Benachrichtigungs-E-Mail via SMTP senden
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST') ?: 'localhost';
            $mail->SMTPAuth   = getenv('SMTP_USER') ? true : false;
            $mail->Username   = getenv('SMTP_USER') ?: '';
            $mail->Password   = getenv('SMTP_PASS') ?: '';
            $mail->SMTPSecure = getenv('SMTP_SECURE') ?: '';
            $mail->Port       = getenv('SMTP_PORT') ?: 25;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(getenv('SMTP_FROM') ?: 'system@revision100.de', 'Revision100 System');
            $mail->addAddress(getenv('SMTP_TO') ?: 'timo@rockets-media.de');

            $mail->isHTML(true);
            $mail->Subject = "🆕 Neue Audit-Anfrage: " . $target_url;
            $mail->Body    = "<h3>Neue Anfrage im System registriert</h3>"
                           . "<strong>URL:</strong> " . htmlspecialchars($target_url) . "<br>"
                           . "<strong>Name:</strong> " . htmlspecialchars($customer_name) . "<br>"
                           . "<strong>E-Mail:</strong> " . htmlspecialchars($email) . "<br>"
                           . "<strong>Telefon:</strong> " . htmlspecialchars($phone) . "<br><br>"
                           . "<strong>Nachricht:</strong><br>" . nl2br(htmlspecialchars($message)) . "<br><br>"
                           . "<a href='https://" . $_SERVER['HTTP_HOST'] . "/crm.php'>Direkt zum CRM wechseln</a>";

            $mail->send();
            $success_msg = "✓ System-Eintrag erfolgreich abgeschlossen. Ihre URL wurde für das manuelle Quelltext-Audit eingereiht. Sie erhalten die Auswertung zeitnah.";
        } catch (Exception $e) {
            $error_msg = "Lead registriert, Benachrichtigung verzögert: " . $mail->ErrorInfo;
        } catch (PDOException $e) {
            $error_msg = "Datenbank-Fehler beim Speichern des Leads: " . $e->getMessage();
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
            gap: 12px !important;
            width: 100% !important;
        }

        .brand-wrapper {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
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
            margin-left: 32px !important;
        }

        .status-led-audit {
            width: 20px !important;
            height: 6px !important;
            background-color: var(--accent-green) !important;
            border-radius: 0px !important;
            box-shadow: 0 0 12px var(--accent-green) !important;
            margin-top: 2px !important;
        }

        main {
            flex: 1 !important;
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 40px !important;
        }

        /* 3-Spalten Info-Grid auf volle Breite skaliert */
        .grid-container {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 24px !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .info-box {
            border: 1px solid var(--border-dark) !important;
            padding: 24px !important;
            background: #161616 !important;
            width: 100% !important;
            max-width: none !important;
        }

        .info-box h2 {
            font-family: var(--font-mono) !important;
            font-size: 16px !important;
            text-transform: uppercase !important;
            margin-bottom: 12px !important;
            letter-spacing: 1px !important;
            color: var(--text-main) !important;
        }

        .info-box p {
            font-size: 14px !important;
            line-height: 1.5 !important;
            color: var(--text-muted) !important;
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

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr !important;
            }
        }

        .form-group {
            display: flex !important;
            flex-direction: column !important;
            gap: 6px !important;
        }

        .form-group.full-width {
            grid-column: 1 / -1 !important;
        }

        label {
            font-family: var(--font-mono) !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            color: var(--text-muted) !important;
        }

        input, textarea {
            background: var(--bg-dark) !important;
            border: 1px solid var(--border-dark) !important;
            color: var(--text-main) !important;
            padding: 12px !important;
            font-family: var(--font-sans) !important;
            font-size: 14px !important;
            width: 100% !important;
        }

        input:focus, textarea:focus {
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

        button.btn-submit:hover {
            opacity: 0.9 !important;
        }

        .alert {
            padding: 16px !important;
            font-family: var(--font-mono) !important;
            font-size: 14px !important;
            margin-bottom: 20px !important;
            border: 1px solid transparent !important;
        }

        .alert-success {
            background: #0d2b1a !important;
            border-color: #1fa47f !important;
            color: #00ff66 !important;
        }

        .alert-error {
            background: #331414 !important;
            border-color: #cc3a21 !important;
            color: #ff6666 !important;
        }

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

        .footer-links a {
            color: var(--text-muted) !important;
            text-decoration: none !important;
        }

        .footer-links a:hover {
            color: var(--text-main) !important;
        }

        /* Modal / Gateway Styles */
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
                gap: 8px !important;
            }
            .brand-name {
                font-size: 24px !important;
            }
            .header-claim {
                margin-left: 32px !important;
            }
            .page-wrapper {
                padding: 0 16px !important;
            }
            .grid-container {
                grid-template-columns: 1fr !important;
                gap: 16px !important;
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
                <div class="status-led-audit"></div>
                <span class="brand-name">Revision100<span class="tm-size">™</span></span>
            </div>
            <div class="header-claim">
                Quelltext-Sanierung bei <?php echo htmlspecialchars($dynamic_keyword); ?>
            </div>
        </header>

        <main>
            
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-error"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <section class="grid-container">
                <div class="info-box">
                    <h2>01 // Status Quo</h2>
                    <p>Moderne Suchmaschinen-Crawler und AI-Agents bewerten Quelltexte nach mathematischer Effizienz, strikter Semantik und barrierefreier Lade-Infrastruktur. Legacy-Code blockiert die organische Sichtbarkeit fundamental.</p>
                </div>
                <div class="info-box">
                    <h2>02 // Deep Audit</h2>
                    <p>Wir reduzieren, sanieren und optimieren Code-Strukturen auf ein kompromissloses 100% Lighthouse-Äquivalent. Kein optischer Schmuck, kein Marketing-Sprech. Reine, maschinenlesbare Performance.</p>
                </div>
                <div class="info-box">
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
                            <label for="customer_name">Name / Ansprechpartner (Pflichtfeld)</label>
                            <input type="text" id="customer_name" name="customer_name" placeholder="Vorname Nachname" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-Mail-Adresse (Pflichtfeld)</label>
                            <input type="email" id="email" name="email" placeholder="name@unternehmen.de" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Telefonnummer (Optional)</label>
                            <input type="tel" id="phone" name="phone" placeholder="+49 30 1234567">
                        </div>
                        <div class="form-group full-width">
                            <label for="message">Besondere Auffälligkeiten / Bisheriger Sichtbarkeitsverlust (Optional)</label>
                            <textarea id="message" name="message" rows="4" placeholder="z.B. Einbruch nach Core-Update, spezifische Indexierungsfehler..."></textarea>
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
            // Prüfe im Hintergrund, ob bereits eine aktive CRM-Session besteht
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

    // Falls ein Login-Fehler vorliegt, Modal direkt wieder öffnen
    if (window.location.search.indexOf('error=1') !== -1) {
        toggleCrmModal(true);
    }
    </script>
</body>
</html>