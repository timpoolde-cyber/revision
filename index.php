<?php
/**
 * REVISION100™ — SYSTEM INITIALISIERUNG, ROUTING, CRM-INTEGRATION & ADMIN-GATEWAY
 * Sende-Protokoll via PHPMailer & SQLite-Direct-Inject // Inklusive nativem CRM-Login
 */

// SÄULE SICHERHEIT: Sitzungs-Validierung initialisieren
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Native Session-Validierung Ihres CRMs einbinden
require_once __DIR__ . '/session_handler.php';

// Lade System-Konfiguration (Isoliert, außerhalb der Versionskontrolle)
$config = require __DIR__ . '/config/config.php';
$smtp_config = $config['smtp'];

// Falls der Admin bereits eingeloggt ist und das Login-Formular aufruft, direkt weiterleiten
if (isset($_GET['action']) && $_GET['action'] === 'check_session') {
    header('Content-Type: application/json');
    if (function_exists('is_logged_in') && is_logged_in()) {
        echo json_encode(['logged' => true]);
    } else {
        echo json_encode(['logged' => false]);
    }
    exit;
}

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

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
            $db = new PDO('sqlite:' . $config['database']['path']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Setze customer_name direkt zur Target-URL
            $customer_name = $target_url;

            // Kunde & Projekt anlegen
            $stmt = $db->prepare("INSERT INTO customers (customer_name, email) VALUES (?, ?)");
            $stmt->execute([$customer_name, $contact_mail]);
            $customer_id = $db->lastInsertId();

            $secret_token = bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, secret_token, updated_at) VALUES (?, ?, ?, 'anfrage', ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$customer_id, $customer_name, $target_url, $secret_token]);

            // Benachrichtigung senden
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp_config['host'];
            $mail->SMTPAuth   = $smtp_config['auth'];
            $mail->Username   = $smtp_config['user'];
            $mail->Password   = $smtp_config['pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_config['port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('system@revision100.de', 'Revision100');
            $mail->addAddress($contact_mail);

            $mail->isHTML(true);
            $mail->Subject = "🆕 Neue Audit-Anfrage: " . $target_url;
            $mail->Body    = "<h3>Neue Anfrage registriert</h3>"
                           . "<strong>URL:</strong> " . htmlspecialchars($target_url) . "<br>"
                           . "<strong>E-Mail:</strong> " . htmlspecialchars($contact_mail) . "<br>";

            $mail->send();
            $success_msg = "✓ System-Eintrag erfolgreich abgeschlossen. Ihre URL wurde eingereiht.";
        } catch (Exception $e) {
            // SÄULE SICHERHEIT: Keine Server- oder SMTP-Interna im Frontend leaken (Information Disclosure Fix)
            error_log('REVISION100 SMTP FEHLER: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage()));
            $error_msg = "Verbindung zum System fehlgeschlagen. Bitte versuchen Sie es später erneut.";
            if (isset($mail) && !empty($mail->ErrorInfo)) {
                file_put_contents('mail_error.log', date('Y-m-d H:i:s') . ' - ' . $mail->ErrorInfo . PHP_EOL, FILE_APPEND);
            }
        } catch (PDOException $e) {
            // SÄULE SICHERHEIT: Datenbankpfade abfangen und sichern
            error_log('REVISION100 DB FEHLER: ' . $e->getMessage());
            $error_msg = "Verbindung zur Datenbank fehlgeschlagen. Bitte versuchen Sie es später erneut.";
        }
    }
}

// 3. ANMELDE-LOGIK FÜR DEN INTERNEN BEREICH (CRM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crm_login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $db = new PDO('sqlite:' . $config['database']['path']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Alias-Zuordnung korrigiert auf password_hash der Tabellenstruktur
        $stmt = $db->prepare("SELECT id, username, password_hash AS password FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['crm_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            header('Location: crm.php');
            exit;
        } else {
            header('Location: ' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '?error=1');
            exit;
        }
    } catch (PDOException $e) {
        die("Datenbank-Fehler: " . $e->getMessage());
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        :root {
            --bg-dark: #111111;
            --border-dark: #333333;
            --text-main: #ededed;      /* Gedämpftes Weiß für hochwertigeren Lese-Eindruck */
            --text-muted: #aaaaaa;
            --accent-green: #3ddc84;   /* Industrie-konformes, professionelles Status-Grün */
            --accent-red: #e5544b;     /* Defekt-Farbwert für die SVGs */
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

        /* Das eiserne 600px HfG-Ulm-Korsett */
        .page-wrapper {
            max-width: 600px !important;
            width: 100% !important;
            min-height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
            margin: 0 auto !important;
            padding: 0 20px !important;
        }

        header {
            background: var(--bg-dark) !important;
            border-bottom: 1px solid var(--border-dark) !important;
            padding: 55px 0 35px 0 !important;
            margin-bottom: 40px !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 6px !important;
            width: 100% !important;
        }

        .brand-name {
            font-family: var(--font-mono) !important;
            color: var(--text-main) !important;
            font-size: 44px !important;
            font-weight: 900 !important;
            letter-spacing: -1.5px !important;
            line-height: 1.0 !important;
            margin: 0 !important;
        }

        .brand-name span.tm-size {
            font-size: 18px !important;
            vertical-align: super !important;
            font-weight: 400 !important;
        }

        .header-claim {
            color: var(--text-muted) !important;
            font-size: 14px !important;
            font-family: var(--font-mono) !important;
            margin: 2px 0 0 0 !important;
            line-height: 1.4 !important;
        }

        .status-sub-line {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            margin-top: 8px !important;
        }

        .status-led-audit {
            width: 15px !important;
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
            0% { opacity: 0.5; box-shadow: 0 0 4px var(--accent-green); }
            50% { opacity: 1; box-shadow: 0 0 12px var(--accent-green); }
            100% { opacity: 0.5; box-shadow: 0 0 4px var(--accent-green); }
        }

        .container {
            width: 100% !important;
            box-sizing: border-box !important;
            margin-bottom: 32px !important;
        }

        .content {
            padding: 0 !important;
            margin: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 20px !important;
        }

        .section-title {
            font-family: var(--font-sans) !important;
            font-weight: bold !important;
            font-size: 13px !important;
            margin-bottom: 4px !important;
            text-transform: uppercase !important;
            border-bottom: 1px solid var(--border-dark) !important;
            padding-bottom: 8px !important;
            color: var(--text-main) !important;
            letter-spacing: 0.5px !important;
        }

        /* Beweiszeile: Technische Validierung für Entwickler */
        .proof-line {
            font-family: var(--font-mono) !important;
            font-size: 11px !important;
            color: var(--text-muted) !important;
            opacity: 0.75 !important;
            border-left: 2px solid var(--border-dark) !important;
            padding-left: 10px !important;
            line-height: 1.5 !important;
            margin-top: -8px !important;
        }

        /* Leistungs-Katalog: Transparenter Leistungsumfang (Drin/Nicht drin) */
        .scope-divider {
            font-family: var(--font-mono) !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            color: var(--text-muted) !important;
            margin: 14px 0 6px 0 !important;
        }

        .scope-list {
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
            margin: 4px 0 4px 0 !important;
        }

        .scope-item {
            font-family: var(--font-mono) !important;
            font-size: 13px !important;
            color: var(--text-main) !important;
            line-height: 1.4 !important;
        }

        .scope-item.in::before {
            content: "+ " !important;
            color: var(--accent-green) !important;
            font-weight: 700 !important;
        }

        .scope-item.out {
            color: var(--text-muted) !important;
            opacity: 0.5 !important;
        }

        .scope-item.out::before {
            content: "– " !important;
            color: var(--text-muted) !important;
            font-weight: 700 !important;
        }

        /* Persona-Block für den Trust-Anker */
        .persona-block {
            border: 1px solid var(--border-dark) !important;
            background: #161616 !important;
            padding: 20px !important;
            display: flex !important;
            gap: 16px !important;
            align-items: center !important;
        }

        .persona-photo {
            width: 56px !important;
            height: 56px !important;
            object-fit: cover !important;
            border: 1px solid var(--border-dark) !important;
            flex-shrink: 0 !important;
            background: #222 !important;
        }

        .persona-text {
            font-size: 13px !important;
            line-height: 1.5 !important;
            color: var(--text-muted) !important;
        }

        .persona-text strong {
            color: var(--text-main) !important;
        }

        .persona-text a {
            color: var(--accent-green) !important;
            text-decoration: none !important;
        }

        .form-group {
            margin-bottom: 14px !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 4px !important;
        }

        .form-label {
            font-family: var(--font-mono) !important;
            font-size: 11px !important;
            color: var(--text-muted) !important;
            text-transform: uppercase !important;
            font-weight: bold !important;
        }

        .form-input {
            padding: 10px !important;
            border: 1px solid var(--border-dark) !important;
            font-family: var(--font-mono) !important;
            font-size: 13px !important;
            box-sizing: border-box !important;
            width: 100% !important;
            background: #161616 !important;
            color: var(--text-main) !important;
        }

        .form-input:focus {
            outline: none !important;
            border-color: var(--text-muted) !important;
        }

        .tech-fakt-text {
            font-size: 14px !important;
            line-height: 1.5 !important;
            color: var(--text-muted) !important;
        }

        .competence-image-container {
            width: 100% !important;
            background: #161616 !important;
            border: 1px solid var(--border-dark) !important;
            padding: 24px !important;
            box-sizing: border-box !important;
        }

        .systemic-consequence {
            font-family: var(--font-mono) !important;
            font-size: 13px !important;
            color: var(--text-main) !important;
            line-height: 1.5 !important;
        }

        .systemic-consequence span {
            color: var(--accent-green) !important;
        }

        .strategic-text {
            font-size: 14px !important;
            line-height: 1.6 !important;
            color: var(--text-muted) !important;
        }

        .form-section {
            border: 1px solid var(--border-dark) !important;
            padding: 32px !important;
            background: #161616 !important;
        }

        .form-section .price-indicator {
            font-family: var(--font-mono) !important;
            font-size: 14px !important;
            color: var(--accent-green) !important;
            margin-bottom: 24px !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold !important;
        }

        .form-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 20px !important;
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
            width: 100% !important;
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
            margin-top: auto !important;
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
                padding: 35px 0 25px 0 !important;
                margin-bottom: 22px !important;
            }
            .brand-name { font-size: 32px !important; }
            .header-claim { font-size: 13px !important; }
            .form-grid { grid-template-columns: 1fr !important; }
            .form-section { padding: 20px !important; }
            .page-wrapper { padding: 0 16px !important; }
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
            // Bei <?php echo htmlspecialchars($dynamic_keyword); ?>: Ihre Website wird nicht mehr gefunden. Ich repariere die Technik dahinter — zum Festpreis.
        </div>
        <div class="status-sub-line">
            <div class="status-led-audit"></div>
            <span class="status-text-audit">System bereit für URL-Analyse</span>
        </div>
    </header>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- SEKTION 01 -->
    <div class="container">
        <div class="content">
            <div class="section-title">01 // Zu langsam — das kostet Sie Kunden</div>
            <p class="tech-fakt-text">Lädt Ihre Seite zu langsam, springen Besucher ab, bevor sie überhaupt Inhalte sehen. Zudem schiebt Google langsame Seiten im Ranking nach hinten. Sie verlieren doppelt: Reichweite und Anfragen.</p>
            <div class="proof-line">Technischer Befund: Lighthouse unter 40%, blockierende Skripte, verschachtelter DOM, aufgeblähter Quelltext.</div>
                        
            <div class="competence-image-container">
                <svg width="100%" height="120" viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" style="background:#161616; display:block;">
                    <rect x="30" y="25" width="340" height="4" fill="#222222" />
                    <rect x="30" y="25" width="135" height="4" fill="var(--accent-red)" />
                    <line x1="165" y1="15" x2="165" y2="40" stroke="var(--accent-red)" stroke-width="1.5" />
                    <text x="175" y="23" fill="var(--accent-red)" font-family="monospace" font-size="10">FAIL (CRITICAL RUNTIME)</text>

                    <path d="M 30 75 L 120 75 L 165 105 L 370 105" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-dasharray="2,2" />
                    <circle cx="165" cy="105" r="4" fill="var(--accent-green)" />
                    <text x="180" y="101" fill="#ffffff" font-family="monospace" font-size="10">DEFEKT-MESSUNG &lt; 40%</text>
                </svg>
            </div>

            <div class="systemic-consequence">
                <span>Systemische Konsequenz:</span> Jede Sekunde Verzögerung minimiert Ihre Konversionsrate. Wer zu lange wartet, bricht ab — und kehrt nicht zurück.
            </div>

            <div class="strategic-text">
                <strong>01 // Status Quo</strong><br>
                Moderne Suchmaschinen und KI-Systeme bewerten Ihren Quelltext nach mathematischer Effizienz, sauberer Semantik und barrierefreier Lade-Infrastruktur. Veralteter Legacy-Code blockiert Ihre organische Sichtbarkeit fundamental.
            </div>
        </div>
    </div>

    <!-- SEKTION 02 -->
    <div class="container">
        <div class="content">
            <div class="section-title">02 // Die KI-Suche findet Sie nicht mehr</div>
            <p class="tech-fakt-text">Immer mehr Entscheider fragen ChatGPT, Google AI oder Perplexity, statt klassisch zu googeln. Diese Systeme lesen Ihre Seite anders als ein Mensch. Ist Ihr Code nicht sauber strukturiert, übergehen KI-Crawler Sie komplett — Sie existieren in der Antwort schlicht nicht. Ihre Konkurrenz schon.</p>
            <div class="proof-line">Technischer Befund: Fehlende semantische HTML-Strukturen und strukturierte JSON-LD Datensätze (Schema), die Parser zum Informationsextrakt zwingend voraussetzen.</div>
                        
            <div class="competence-image-container">
                <svg width="100%" height="120" viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" style="background:#161616; display:block;">
                    <g stroke="#333333" stroke-width="1">
                        <line x1="30" y1="20" x2="30" y2="100" />
                        <line x1="150" y1="20" x2="150" y2="100" />
                    </g>
                    <path d="M 30 45 L 150 45 L 100 15" fill="none" stroke="var(--accent-red)" stroke-width="1.5" />
                    <polygon points="100,15 108,14 103,22" fill="var(--accent-red)" />
                                
                    <rect x="150" y="30" width="12" height="60" fill="#333333" />
                    <line x1="150" y1="45" x2="162" y2="45" stroke="#161616" stroke-width="2" />
                    <line x1="150" y1="75" x2="162" y2="75" stroke="#161616" stroke-width="2" />

                    <text x="175" y="50" fill="#aaaaaa" font-family="monospace" font-size="10">CRAWLER REFLEKTION</text>
                    <text x="175" y="65" fill="var(--accent-red)" font-family="monospace" font-size="10">NO-INDEX / BLOCK</text>
                    <circle cx="150" cy="45" r="3" fill="var(--accent-green)" />
                </svg>
            </div>

            <div class="systemic-consequence">
                <span>Systemische Konsequenz:</span> Unstrukturierte Datensätze führen zu einer fehlerhaften Interpretation der Informationshierarchie durch automatisierte RAG-Systeme.
            </div>

            <div class="strategic-text">
                <strong>02 // Deep Audit</strong><br>
                Ich reduziere, saniere und optimiere Code-Strukturen auf ein kompromissloses, maschinenlesbares Niveau. Kein optischer Schmuck, kein Marketing-Sprech. Reine, funktionale Substanz für Algorithmen.
            </div>
        </div>
    </div>

    <!-- SEKTION 03 -->
    <div class="container">
        <div class="content">
            <div class="section-title">03 // Schnell und maschinenlesbar — beides oder nichts</div>
            <p class="tech-fakt-text">Geschwindigkeit und saubere semantische Struktur gehören untrennbar zusammen. Schnell, aber unlesbar: ignoriert. Lesbar, aber träge: vom Crawler abgebrochen. Erst die fehlerfreie Symbiose beider Faktoren bringt Ihre Domain zurück in die Such- und KI-Antworten.</p>
            <div class="proof-line">Technischer Befund: Native HTTP/3-Unterstützung, Brotli-Kompression, optimierte Transport-Header und minimale TTFB-Latenzen.</div>

            <div class="competence-image-container">
                <svg width="100%" height="120" viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" style="background:#161616; display:block;">
                    <text x="30" y="25" fill="#ffffff" opacity="0.3" font-family="monospace" font-size="9">SOLL: HTTP/3 (STREAMING)</text>
                    <line x1="30" y1="35" x2="370" y2="35" stroke="#222222" stroke-width="1" />
                    <rect x="150" y="30" width="15" height="10" fill="#ffffff" opacity="0.8" />
                    <rect x="175" y="30" width="15" height="10" fill="#ffffff" opacity="0.8" />
                    <rect x="200" y="30" width="15" height="10" fill="#ffffff" opacity="0.8" />

                    <text x="30" y="70" fill="var(--accent-red)" opacity="0.6" font-family="monospace" font-size="9">IST: HTTP/1.1 (LATENZ-STAU)</text>
                    <line x1="30" y1="80" x2="370" y2="80" stroke="#222222" stroke-width="1" />
                    <rect x="150" y="75" width="15" height="10" fill="#333333" />
                    <rect x="210" y="75" width="15" height="10" fill="#333333" />
                    <rect x="285" y="75" width="15" height="10" fill="#333333" />

                    <line x1="200" y1="30" x2="200" y2="90" stroke="var(--accent-green)" stroke-width="1" stroke-dasharray="2,2" />
                    <circle cx="200" cy="80" r="3" fill="var(--accent-green)" />
                    <text x="223" y="93" fill="var(--accent-green)" font-family="monospace" font-size="9">TTFB VERZÖGERUNG</text>
                </svg>
            </div>

            <div class="systemic-consequence">
                <span>Systemische Konsequenz:</span> Mangelhafte Server-Antwortzeiten zwingen Web-Parser zur Reduzierung der Indexierungs-Frequenz Ihrer gesamten System-Infrastruktur.
            </div>

            <div class="strategic-text">
                <strong>03 // System-Intervention zum Festpreis</strong><br>
                Ich saniere parallel die Ausführungsschicht und das Transport-Protokoll. Das Ergebnis ist ein schlankes, KI-bereites Fundament, das Auslieferung und Quellcode radikal beschleunigt.
            </div>
        </div>
    </div>

    <!-- FORMULAR / LEAD GENERATION MIT INHALTS-ANALYSE -->
    <div class="container">
        <div class="content">
            <div class="section-title">Was Sie bekommen — und was nicht</div>
            <div class="form-section">
                
                <div class="scope-divider">Leistungsumfang (Inklusive)</div>
                <div class="scope-list">
                    <div class="scope-item in">Komplette Quelltext-Decontamination — Core und Auslieferung</div>
                    <div class="scope-item in">Zielwert: Bis zu 100% Lighthouse-Performance auf allen Achsen</div>
                    <div class="scope-item in">Validierte semantische HTML5-Strukturierung für Suchsysteme</div>
                    <div class="scope-item in">Integration strukturierter JSON-LD Entitäten-Daten (KI-Readiness)</div>
                    <div class="scope-item in">Protokoll-Beschleunigung (TTFB, Header, Kompression)</div>
                    <div class="scope-item in">Garantierter Festpreis — Einmalinvestition, kein Abonnement</div>
                </div>

                <div class="scope-divider">Ausschlusskriterien (Nicht enthalten)</div>
                <div class="scope-list">
                    <div class="scope-item out">Visuelles Redesign, neues Layout oder Oberflächengrafiken</div>
                    <div class="scope-item out">Inhaltliche Texterstellung, Bildbearbeitung oder Copywriting</div>
                    <div class="scope-item out">Laufende SEO-Beratung oder monatliche Retainer-Verpflichtungen</div>
                </div>

                <div class="proof-line" style="margin: 16px 0 24px 0;">Ich verändere Ihre gewohnte Optik nicht. Ich repariere das technische Fundament darunter.</div>

                <div class="price-indicator">Investition: 4.800,– € Netto — Fix. Keine Überraschungen.</div>
                
                <div class="proof-line" style="margin-top: -16px; margin-bottom: 24px;">Vorab erfolgt eine kostenfreie, manuelle Analyse Ihrer URL. Sie sehen exakt, was machbar ist, bevor Sie investieren.</div>

                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                    <input type="hidden" name="action" value="submit_lead">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="target_url">Ziel-URL (Pflichtfeld)</label>
                            <input type="url" id="target_url" class="form-input" name="target_url" placeholder="https://www.ihre-website.de" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="contact_mail">E-Mail-Adresse (Pflichtfeld)</label>
                            <input type="email" id="contact_mail" class="form-input" name="contact_mail" placeholder="name@unternehmen.de" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Analyse-Prozess starten</button>
                </form>
            </div>
        </div>
    </div>

    <!-- TRUST-ANKER: PERSONA-BLOCK -->
    <div class="container">
        <div class="content">
            <div class="section-title">Wer operiert am System?</div>
            <div class="persona-block">
                <img src="/timo.jpg" class="persona-photo" alt="Timo E. Pohlhaus">
                <div class="persona-text">
                    <strong>Timo E. Pohlhaus</strong> — Seit über 30 Jahren verantwortlich für Code-Infrastrukturen, strategisches Marketing und digitale Systeme. Kein Agentur-Überbau, kein wechselndes Personal, keine Praktikanten. Ein Spezialist, der Ihr System persönlich analysiert und optimiert.<br>
                    <a href="https://timpool.de" target="_blank">→ Die vollständige Dokumentation</a>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div>Revision100™ ein Service von Timo E. Pohlhaus – <a href="https://timpool.de" style="color: var(--text-muted); text-decoration: none;">timpool.de</a></div>
        <div class="manifest-banner" style="max-width: none; font-size: 11px; color: var(--text-muted); margin-top: 5px;">Kein Schmuck. Nur System. Kein Marketing-Sprech. Nur Daten.</div>
        <div class="footer-links">
            <a href="/datenschutz">Datenschutz</a> //
            <a href="/impressum">Impressum</a> //
            <a href="#" id="crmLink" onclick="handleCrmGateway(event)" style="color: #2c3a3c;">login</a>
        </div>
    </footer>

    <!-- INTERNES CRM GATEWAY MODAL -->
    <div id="crmModal" class="modal-mask" onclick="toggleCrmModal(false)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="font-family: var(--font-mono); font-size: 14px; text-transform: uppercase; border-bottom: 1px solid var(--border-dark); padding-bottom: 8px; margin-bottom: 8px;">
                System-Zentrale Authentifizierung
            </div>
            <?php if (isset($_GET['error'])): ?>
                <div style="color: #ff6666; font-family: var(--font-mono); font-size: 12px; margin-bottom: 8px;">✓ Zugriff verweigert.</div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                <input type="hidden" name="action" value="crm_login">
                <div class="form-group">
                    <label class="form-label">Kennung</label>
                    <input type="text" class="form-input" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label">Passwort</label>
                    <input type="password" class="form-input" name="password" required autocomplete="current-password">
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

</div> </body>
</html>