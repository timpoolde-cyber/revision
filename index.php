<?php
/**
 * R400™ — SYSTEM INITIALISIERUNG, ROUTING, CRM-INTEGRATION & ADMIN-GATEWAY
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

$page_title = "R400™ — Quelltext-Sanierung bei Google-Rankingverlust";
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

            $mail->setFrom('system@revision100.de', 'R400');
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
            --panel: #161616;
            --border-dark: #333333;
            --text-main: #ededed;      /* Gedämpftes Weiß für hochwertigeren Lese-Eindruck */
            --text-muted: #9a9a9a;
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
            background: var(--panel) !important;
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
            background: var(--panel) !important;
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
            box-sizing: border-box !important;
        }

        .competence-image-container svg {
            display: block !important;
            width: 100% !important;
            height: auto !important;
        }

        /* --- CSS-STEUERUNG FÜR DIE INTERAKTIVEN DIAGRAMME (DEZENTER BUTTON UNTEN LINKS) --- */
        .vis-toggle { position: absolute !important; left: -9999px !important; }
        .vis-btn { position: absolute !important; left: 14px !important; bottom: 14px !important; z-index: 2 !important; cursor: pointer !important; user-select: none !important; font-family: var(--font-mono,monospace) !important; font-size: 9px !important; letter-spacing: 1px !important; color: var(--text-muted) !important; border: 1px solid var(--border-dark) !important; padding: 4px 8px !important; background: rgba(17,17,17,.55) !important; transition: color .15s, border-color .15s !important; }
        .vis-btn::before { content: "▶ START" !important; }
        .vis-btn:hover { color: var(--accent-green) !important; border-color: var(--accent-green) !important; }
        #run01:checked ~ .vis-btn::before,
        #run02a:checked ~ .vis-btn::before,
        #run02b:checked ~ .vis-btn::before,
        #run03:checked ~ .vis-btn::before { content: "⏸ PAUSE" !important; }

        /* Animationen standardmäßig pausieren */
        .s1-leave, .s1-bob, .s1-load, .s1-dot, .s1-res,
        .rc-bot, .rc-beam, .rc-m1, .rc-m2, .rc-m3, .rc-m4,
        .s2-row, .s2-pulse, .s2-res, .s2-caret,
        .s3-fill, .s3-l1, .s3-l2, .s3-stamp, .s3-low, .s3-high { animation-play-state: paused !important; }

        /* Aktivierung bei Klick */
        #run01:checked ~ .vis-btn,
        #run02a:checked ~ .vis-btn,
        #run02b:checked ~ .vis-btn,
        #run03:checked ~ .vis-btn { }

        #run01:checked ~ svg :is(.s1-leave,.s1-bob,.s1-load,.s1-dot,.s1-res),
        #run02a:checked ~ svg :is(.rc-bot,.rc-beam,.rc-m1,.rc-m2,.rc-m3,.rc-m4),
        #run02b:checked ~ svg :is(.s2-row,.s2-pulse,.s2-res,.s2-caret),
        #run03:checked ~ svg :is(.s3-fill,.s3-l1,.s3-l2,.s3-stamp,.s3-low,.s3-high) { animation-play-state: running !important; }

        /* --- ANIMATIONS-SCHLÜSSEL --- */
        .s1-leave { animation: s1-leave 5s infinite; }
        .s1-bob { animation: s1-bob 1s infinite ease-in-out; }
        .s1-load { transform-box: fill-box; transform-origin: left center; animation: s1-load 5s infinite; }
        .s1-dot { animation: s1-dot 1.2s infinite; } .s1-dot.b { animation-delay: .2s; } .s1-dot.c { animation-delay: .4s; }
        .s1-res { animation: s1-res 5s infinite; }

        .rc-bot { animation: rc-move 8s infinite; }
        .rc-beam { animation: rc-beam 1.2s infinite ease-in-out; }
        .rc-m1 { animation: rc-m1 8s infinite; } .rc-m2 { animation: rc-m2 8s infinite; }
        .rc-m3 { animation: rc-m3 8s infinite; } .rc-m4 { animation: rc-m4 8s infinite; }

        .s2-row { animation: s2-row 5s infinite; opacity: 0; }
        .s2-row.r1 { animation-delay: .2s; } .s2-row.r2 { animation-delay: .8s; } .s2-row.r3 { animation-delay: 1.4s; }
        .s2-pulse { animation: s2-pulse 1.6s infinite; }
        .s2-res { animation: s2-res 5s infinite; }
        .s2-caret { animation: s2-caret .8s steps(1) infinite; }

        .s3-fill { transform-box: fill-box; transform-origin: left center; animation: s3-fill 4s infinite; }
        .s3-l1 { animation: s3-l1 4s infinite; } .s3-l2 { animation: s3-l2 4s infinite; }
        .s3-stamp { transform-box: fill-box; transform-origin: center; animation: s3-stamp 4s infinite; }
        .s3-low { animation: s3-low 4s infinite; } .s3-high { animation: s3-high 4s infinite; }

        @keyframes s1-leave { 0%{transform:translateX(0);opacity:1;} 50%{transform:translateX(0);opacity:1;} 70%,100%{transform:translateX(-115px);opacity:0;} }
        @keyframes s1-bob { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-2px);} }
        @keyframes s1-load { 0%{transform:scaleX(.02);} 50%{transform:scaleX(.34);} 100%{transform:scaleX(.36);} }
        @keyframes s1-dot { 0%,100%{opacity:.15;} 50%{opacity:1;} }
        @keyframes s1-res { 0%,62%{opacity:0;} 78%,100%{opacity:1;} }

        @keyframes rc-move { 0%,18%{transform:translateX(0);} 25%,43%{transform:translateX(90px);} 50%,68%{transform:translateX(180px);} 75%,93%{transform:translateX(270px);} 100%{transform:translateX(0);} }
        @keyframes rc-beam { 0%,100%{opacity:.10;} 50%{opacity:.22;} }
        @keyframes rc-m1 { 0%,4%{opacity:0;} 8%,99%{opacity:1;} 100%{opacity:0;} }
        @keyframes rc-m2 { 0%,29%{opacity:0;} 33%,99%{opacity:1;} 100%{opacity:0;} }
        @keyframes rc-m3 { 0%,54%{opacity:0;} 58%,99%{opacity:1;} 100%{opacity:0;} }
        @keyframes rc-m4 { 0%,79%{opacity:0;} 83%,99%{opacity:1;} 100%{opacity:0;} }

        @keyframes s2-row { 0%{opacity:0;transform:translateX(-6px);} 8%{opacity:1;transform:translateX(0);} 88%{opacity:1;} 96%,100%{opacity:0;} }
        @keyframes s2-pulse { 0%,100%{opacity:.45;} 50%{opacity:.95;} }
        @keyframes s2-res { 0%,55%{opacity:0;} 72%,100%{opacity:1;} }
        @keyframes s2-caret { 0%,100%{opacity:1;} 50%{opacity:0;} }

        @keyframes s3-fill { 0%{transform:scaleX(.4);fill:var(--accent-red);} 55%{transform:scaleX(1);fill:var(--accent-green);} 88%{transform:scaleX(1);fill:var(--accent-green);} 100%{transform:scaleX(.4);fill:var(--accent-red);} }
        @keyframes s3-l1 { 0%,22%{fill:var(--accent-red);} 40%,100%{fill:var(--accent-green);} }
        @keyframes s3-l2 { 0%,40%{fill:var(--accent-red);} 55%,100%{fill:var(--accent-green);} }
        @keyframes s3-stamp { 0%,52%{opacity:0;transform:scale(.85);} 66%,88%{opacity:1;transform:scale(1);} 100%{opacity:0;} }
        @keyframes s3-low { 0%,40%{opacity:1;} 55%,100%{opacity:0;} }
        @keyframes s3-high { 0%,45%{opacity:0;} 60%,100%{opacity:1;} }

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
            background: var(--panel) !important;
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
            background: var(--panel) !important;
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
            <span class="brand-name">R400<span class="tm-size">™</span></span>
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
                        
            <!-- INTERAKTIVER COMPETENCE-IMAGE-CONTAINER BLOCK 01 -->
            <div class="competence-image-container" style="position:relative;overflow:hidden;background:var(--panel,#161616);border:1px solid var(--border-dark,#333);padding:24px;">
                <input type="checkbox" id="run01" class="vis-toggle">
                <label for="run01" class="vis-btn"></label>
                <svg viewBox="0 0 400 150" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Besucher wartet auf eine langsam ladende Seite und geht">
                    <rect x="235" y="34" width="140" height="86" style="fill:var(--panel,#161616);stroke:var(--border-dark,#333);" stroke-width="1"/>
                    <line x1="235" y1="48" x2="375" y2="48" style="stroke:var(--border-dark,#333);" stroke-width="1"/>
                    <circle cx="244" cy="41" r="2" fill="#444"/><circle cx="252" cy="41" r="2" fill="#444"/><circle cx="260" cy="41" r="2" fill="#444"/>
                    <text x="305" y="112" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="8" text-anchor="middle">DEINE SEITE</text>
                    <rect x="250" y="74" width="110" height="6" fill="#2a2a2a"/>
                    <rect class="s1-load" x="250" y="74" width="110" height="6" style="fill:var(--accent-red,#e5544b);"/>
                    <text x="250" y="68" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="8">lädt…</text>
                    <g class="s1-leave"><g class="s1-bob">
                    <circle class="s1-dot" cx="100" cy="40" r="2.5" style="fill:var(--text-muted,#9a9a9a);"/>
                    <circle class="s1-dot b" cx="110" cy="40" r="2.5" style="fill:var(--text-muted,#9a9a9a);"/>
                    <circle class="s1-dot c" cx="120" cy="40" r="2.5" style="fill:var(--text-muted,#9a9a9a);"/>
                    <circle cx="110" cy="62" r="9" fill="none" style="stroke:var(--text-main,#ededed);" stroke-width="2"/>
                    <line x1="110" y1="71" x2="110" y2="98" style="stroke:var(--text-main,#ededed);" stroke-width="2"/>
                    <line x1="110" y1="79" x2="97" y2="90" style="stroke:var(--text-main,#ededed);" stroke-width="2"/>
                    <line x1="110" y1="79" x2="123" y2="90" style="stroke:var(--text-main,#ededed);" stroke-width="2"/>
                    <line x1="110" y1="98" x2="99" y2="118" style="stroke:var(--text-main,#ededed);" stroke-width="2"/>
                    <line x1="110" y1="98" x2="121" y2="118" style="stroke:var(--text-main,#ededed);" stroke-width="2"/>
                    </g></g>
                    <text x="110" y="135" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="8" text-anchor="middle">BESUCHER</text>
                    <text class="s1-res" x="200" y="20" style="fill:var(--accent-red,#e5544b);" font-family="monospace" font-size="11" font-weight="bold" text-anchor="middle">→ ABGESPRUNGEN</text>
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
                        
            <!-- INTERAKTIVER COMPETENCE-IMAGE-CONTAINER BLOCK 02a (URSACHE) -->
            <div class="competence-image-container" style="position:relative;overflow:hidden;background:var(--panel,#161616);border:1px solid var(--border-dark,#333);padding:24px;margin-bottom: 12px;">
                <input type="checkbox" id="run02a" class="vis-toggle">
                <label for="run02a" class="vis-btn"></label>
                <svg viewBox="0 0 400 150" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ein Crawler-Roboter liest strukturierte Seiten und überspringt deine unleserliche Seite">
                    <rect x="40" y="92" width="50" height="40" style="fill:var(--panel,#161616);stroke:var(--border-dark,#333);" stroke-width="1"/>
                    <rect x="46" y="100" width="38" height="3" style="fill:var(--text-muted,#9a9a9a);"/><rect x="46" y="108" width="38" height="3" style="fill:var(--text-muted,#9a9a9a);"/><rect x="46" y="116" width="26" height="3" style="fill:var(--text-muted,#9a9a9a);"/>
                    <rect x="130" y="92" width="50" height="40" style="fill:var(--panel,#161616);stroke:var(--border-dark,#333);" stroke-width="1"/>
                    <rect x="136" y="100" width="38" height="3" style="fill:var(--text-muted,#9a9a9a);"/><rect x="136" y="108" width="38" height="3" style="fill:var(--text-muted,#9a9a9a);"/><rect x="136" y="116" width="26" height="3" style="fill:var(--text-muted,#9a9a9a);"/>
                    <rect x="220" y="92" width="50" height="40" style="fill:var(--panel,#161616);stroke:var(--accent-red,#e5544b);" stroke-width="1" stroke-dasharray="3,3"/>
                    <path d="M226 102 l8 -3 l8 4 l8 -3 l8 4" fill="none" style="stroke:var(--accent-red,#e5544b);" stroke-width="1.5"/>
                    <path d="M226 114 l10 4 l8 -4 l10 3" fill="none" style="stroke:var(--accent-red,#e5544b);" stroke-width="1.5"/>
                    <text x="245" y="148" style="fill:var(--accent-red,#e5544b);" font-family="monospace" font-size="8" text-anchor="middle">DEINE SEITE</text>
                    <rect x="310" y="92" width="50" height="40" style="fill:var(--panel,#161616);stroke:var(--border-dark,#333);" stroke-width="1"/>
                    <rect x="316" y="100" width="38" height="3" style="fill:var(--text-muted,#9a9a9a);"/><rect x="316" y="108" width="38" height="3" style="fill:var(--text-muted,#9a9a9a);"/><rect x="316" y="116" width="26" height="3" style="fill:var(--text-muted,#9a9a9a);"/>
                    <text class="rc-m1" x="65" y="84" style="fill:var(--accent-green,#3ddc84);" font-family="monospace" font-size="13" font-weight="bold" text-anchor="middle">✓</text>
                    <text class="rc-m2" x="155" y="84" style="fill:var(--accent-green,#3ddc84);" font-family="monospace" font-size="13" font-weight="bold" text-anchor="middle">✓</text>
                    <text class="rc-m3" x="245" y="84" style="fill:var(--accent-red,#e5544b);" font-family="monospace" font-size="13" font-weight="bold" text-anchor="middle">✗</text>
                    <text class="rc-m4" x="335" y="84" style="fill:var(--accent-green,#3ddc84);" font-family="monospace" font-size="13" font-weight="bold" text-anchor="middle">✓</text>
                    <g class="rc-bot">
                    <circle cx="65" cy="12" r="2.5" style="fill:var(--accent-green,#3ddc84);"/>
                    <line x1="65" y1="14" x2="65" y2="20" style="stroke:var(--text-main,#ededed);" stroke-width="1.5"/>
                    <rect x="47" y="20" width="36" height="24" rx="3" style="fill:var(--panel,#161616);stroke:var(--text-main,#ededed);" stroke-width="1.5"/>
                    <circle cx="58" cy="31" r="2.5" style="fill:var(--accent-green,#3ddc84);"/>
                    <circle cx="72" cy="31" r="2.5" style="fill:var(--accent-green,#3ddc84);"/>
                    <line x1="55" y1="39" x2="75" y2="39" style="stroke:var(--text-muted,#9a9a9a);" stroke-width="1"/>
                    <polygon class="rc-beam" points="55,44 75,44 82,86 48,86" style="fill:var(--accent-green,#3ddc84);"/>
                    </g>
                </svg>
            </div>

            <!-- INTERAKTIVER COMPETENCE-IMAGE-CONTAINER BLOCK 02b (FOLGE) -->
            <div class="competence-image-container" style="position:relative;overflow:hidden;background:var(--panel,#161616);border:1px solid var(--border-dark,#333);padding:24px;">
                <input type="checkbox" id="run02b" class="vis-toggle">
                <label for="run02b" class="vis-btn"></label>
                <svg viewBox="0 0 400 165" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Eine KI-Antwort empfiehlt drei Mitbewerber, deine Seite wird ausgelassen">
                    <rect x="18" y="16" width="246" height="128" rx="4" style="fill:var(--panel,#161616);stroke:var(--border-dark,#333);" stroke-width="1"/>
                    <circle cx="33" cy="32" r="4" style="fill:var(--accent-green,#3ddc84);"/>
                    <text x="44" y="35" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="9">KI-ANTWORT</text>
                    <text x="33" y="56" fill="#777" font-family="monospace" font-size="9">» bester anbieter in deiner stadt?</text>
                    <g class="s2-row r1"><text x="33" y="82" style="fill:var(--accent-green,#3ddc84);" font-family="monospace" font-size="11">✓ Mitbewerber A</text></g>
                    <g class="s2-row r2"><text x="33" y="103" style="fill:var(--accent-green,#3ddc84);" font-family="monospace" font-size="11">✓ Mitbewerber B</text></g>
                    <g class="s2-row r3"><text x="33" y="124" style="fill:var(--accent-green,#3ddc84);" font-family="monospace" font-size="11">✓ Mitbewerber C</text><text class="s2-caret" x="148" y="124" style="fill:var(--accent-green,#3ddc84);" font-family="monospace" font-size="11">_</text></g>
                    <line x1="264" y1="80" x2="300" y2="80" style="stroke:var(--accent-red,#e5544b);" stroke-width="1" stroke-dasharray="3,3"/>
                    <text x="282" y="74" style="fill:var(--accent-red,#e5544b);" font-family="monospace" font-size="11" text-anchor="middle">✗</text>
                    <rect x="300" y="55" width="84" height="50" rx="3" style="fill:var(--bg-dark,#111);stroke:var(--accent-red,#e5544b);" stroke-width="1" stroke-dasharray="3,3"/>
                    <text x="342" y="78" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="9" text-anchor="middle">DEINE</text>
                    <text x="342" y="90" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="9" text-anchor="middle">SEITE</text>
                    <text class="s2-pulse" x="342" y="120" style="fill:var(--accent-red,#e5544b);" font-family="monospace" font-size="8" text-anchor="middle">nicht lesbar</text>
                    <text class="s2-res" x="200" y="158" style="fill:var(--text-main,#ededed);" font-family="monospace" font-size="11" font-weight="bold" text-anchor="middle">DIE KI EMPFIEHLT — NUR DICH NICHT.</text>
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

            <!-- INTERAKTIVER COMPETENCE-IMAGE-CONTAINER BLOCK 03 -->
            <div class="competence-image-container" style="position:relative;overflow:hidden;background:var(--panel,#161616);border:1px solid var(--border-dark,#333);padding:24px;">
                <input type="checkbox" id="run03" class="vis-toggle">
                <label for="run03" class="vis-btn"></label>
                <svg viewBox="0 0 400 150" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Score steigt von 40 auf 100 Prozent wenn Tempo und Struktur beide grün sind">
                    <text x="40" y="40" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="9">LIGHTHOUSE</text>
                    <rect x="40" y="52" width="320" height="14" fill="#2a2a2a"/>
                    <rect class="s3-fill" x="40" y="52" width="320" height="14"/>
                    <text class="s3-low" x="180" y="63" fill="#111" font-family="monospace" font-size="10" font-weight="bold" text-anchor="middle">40%</text>
                    <text class="s3-high" x="356" y="63" fill="#111" font-family="monospace" font-size="10" font-weight="bold" text-anchor="end">100%</text>
                    <g class="s3-stamp">
                    <rect x="288" y="26" width="84" height="20" rx="2" fill="none" style="stroke:var(--accent-green);" stroke-width="1.5"/>
                    <text x="330" y="40" style="fill:var(--accent-green);" font-family="monospace" font-size="11" font-weight="bold" text-anchor="middle">SICHTBAR</text>
                    </g>
                    <circle class="s3-l1" cx="50" cy="100" r="5"/>
                    <text x="62" y="104" style="fill:var(--text-main);" font-family="monospace" font-size="11">TEMPO</text>
                    <circle class="s3-l2" cx="50" cy="124" r="5"/>
                    <text x="62" y="128" style="fill:var(--text-main);" font-family="monospace" font-size="11">STRUKTUR</text>
                    <text x="250" y="116" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="9" text-anchor="middle">erst wenn beide grün sind,</text>
                    <text x="250" y="128" style="fill:var(--text-muted,#9a9a9a);" font-family="monospace" font-size="9" text-anchor="middle">wirst du gefunden.</text>
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
        <div>R400™ ein Service von Timo E. Pohlhaus – <a href="https://timpool.de" style="color: var(--text-muted); text-decoration: none;">timpool.de</a></div>
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