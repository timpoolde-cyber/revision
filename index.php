<?php
/**
 * REVISION100™ — SYSTEM INITIALISIERUNG, ROUTING, CRM-INTEGRATION & ADMIN-GATEWAY
 * Sende-Protokoll via PHPMailer & SQLite-Direct-Inject // Inklusive nativem CRM-Login
 */

// SÄULE SIKHERHEIT: Sitzungs-Validierung initialisieren
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

            // Kunde & Projekt anlegen
            $stmt = $db->prepare("INSERT INTO customers (customer_name, email) VALUES (?, ?)");
            $stmt->execute(['Anonyme Anfrage', $contact_mail]);
            $customer_id = $db->lastInsertId();

            $secret_token = bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, secret_token, updated_at) VALUES (?, ?, ?, 'anfrage', ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$customer_id, 'Anonyme Anfrage', $target_url, $secret_token]);

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
            $error_msg = "Fehler beim SMTP-Versand: " . (isset($mail) ? $mail->ErrorInfo : $e->getMessage());
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
            max-width: 1000px !important;
            width: 100% !important;
            min-height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
            padding: 0 32px !important;
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

        .brand-wrapper {
            display: flex !important;
            align-items: center !important;
            gap: 0 !important;
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
            font-size: 15px !important;
            font-family: var(--font-mono) !important;
            margin: 2px 0 0 0 !important;
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

        .content-stack {
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
        }

        .block-segment {
            border-bottom: 1px solid var(--border-dark) !important;
            padding: 40px 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 20px !important;
        }

        .block-segment:first-of-type {
            padding-top: 0 !important;
        }

        .tech-fakt-headline {
            font-family: var(--font-mono) !important;
            font-size: 16px !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            color: var(--text-main) !important;
            margin-bottom: 4px !important;
            text-align: left;
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
            width: 100% !important;
            margin-top: 20px !important;
            margin-bottom: 40px !important;
        }

        .form-section h2 {
            font-family: var(--font-mono) !important;
            font-size: 18px !important;
            text-transform: uppercase !important;
            margin-bottom: 6px !important;
            letter-spacing: 1px;
        }

        .form-section .price-indicator {
            font-family: var(--font-mono) !important;
            font-size: 14px !important;
            color: var(--accent-green) !important;
            margin-bottom: 24px !important;
            text-transform: uppercase;
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
                padding: 35px 0 25px 0 !important;
                margin-bottom: 22px !important;
            }
            .brand-name { 
                font-size: 32px !important; 
            }
            .header-claim {
                font-size: 13px !important;
            }
            body { padding: 0 !important; overflow-x: hidden !important; }
            .page-wrapper { 
    width: 100% !important; 
    max-width: 1200px !important; /* Begrenzt die Breite auf Desktops */
    margin: 0 auto !important;    /* Zentriert die Box auf großen Bildschirmen */
    padding: 0 16px !important; 
    overflow-x: hidden !important; 
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
                // Quelltext-Sanierung bei <?php echo htmlspecialchars($dynamic_keyword); ?> zum Festpreis
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

            <div class="content-stack">
                
                <section class="block-segment">
                    <div>
                        <h2 class="tech-fakt-headline">01 // Lighthouse Score &lt; 40%</h2>
                        <p class="tech-fakt-text">Fehlerhafter Code, verschachtelte DOM-Strukturen und blockierende Skripte drosseln die Ladegeschwindigkeit. Suchmaschinen strafen langsame Systeme gnadenlos ab.</p>
                    </div>
                    
                    <div class="competence-image-container">
                        <svg width="100%" height="120" viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" style="background:#161616; display:block;">
                            <rect x="30" y="25" width="340" height="4" fill="#222222" />
                            <rect x="30" y="25" width="135" height="4" fill="#ff3333" />
                            <line x1="165" y1="15" x2="165" y2="40" stroke="#ff3333" stroke-width="1.5" />
                            <text x="175" y="23" fill="#ff3333" font-family="monospace" font-size="10">FAIL (CRITICAL RUNTIME)</text>
                            
                            <path d="M 30 75 L 120 75 L 165 105 L 370 105" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-dasharray="2,2" />
                            <circle cx="165" cy="105" r="4" fill="#00ff66" />
                            <text x="180" y="101" fill="#ffffff" font-family="monospace" font-size="10">DEFEKT-MESSUNG &lt; 40%</text>
                        </svg>
                    </div>
                    
                    <div class="systemic-consequence">
                        <span>Systemische Konsequenz:</span> Die gemessene Latenz der Code-Infrastruktur führt zu einem messbaren Abbruch der Benutzer-Sitzungen und verhindert die korrekte Datenverarbeitung durch externe Index-Systeme.
                    </div>
                    
                    <div class="strategic-text">
                        <strong>01 // Status Quo</strong><br>
                        Moderne Suchmaschinen-Crawler und AI-Agents bewerten Quelltexte nach mathematischer Effizienz, strikter Semantik und barrierefreier Lade-Infrastruktur. Legacy-Code blockiert die organische Sichtbarkeit fundamental.
                    </div>
                </section>

                <section class="block-segment">
                    <div>
                        <h2 class="tech-fakt-headline">02 // AI-Crawler Blockade</h2>
                        <p class="tech-fakt-text">Fehlende semantische HTML-Strukturen verhindern, dass moderne AI-Agenten den Inhalt korrekt erfassen und indexieren können. Ihre Relevanz sinkt im neuen Such-Ökosystem gegen Null.</p>
                    </div>
                    
                    <div class="competence-image-container">
                        <svg width="100%" height="120" viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" style="background:#161616; display:block;">
                            <g stroke="#333333" stroke-width="1">
                                <line x1="30" y1="20" x2="30" y2="100" />
                                <line x1="150" y1="20" x2="150" y2="100" />
                            </g>
                            <path d="M 30 45 L 150 45 L 100 15" fill="none" stroke="#ff3333" stroke-width="1.5" />
                            <polygon points="100,15 108,14 103,22" fill="#ff3333" />
                            
                            <rect x="150" y="30" width="12" height="60" fill="#333333" />
                            <line x1="150" y1="45" x2="162" y2="45" stroke="#161616" stroke-width="2" />
                            <line x1="150" y1="75" x2="162" y2="75" stroke="#161616" stroke-width="2" />
                            
                            <text x="175" y="50" fill="#aaaaaa" font-family="monospace" font-size="10">CRAWLER REFLEKTION</text>
                            <text x="175" y="65" fill="#ff3333" font-family="monospace" font-size="10">NO-INDEX / BLOCK</text>
                            <circle cx="150" cy="45" r="3" fill="#00ff66" />
                        </svg>
                    </div>
                    
                    <div class="systemic-consequence">
                        <span>Systemische Konsequenz:</span> Unstrukturierte Datensätze führen zu einer fehlerhaften Interpretation der Informationshierarchie durch automatisierte Parser-Systeme.
                    </div>
                    
                    <div class="strategic-text">
                        <strong>02 // Deep Audit</strong><br>
                        Ich reduziere, saniere und optimiere Code-Strukturen auf ein kompromissloses 100% Lighthouse-Äquivalent. Kein optischer Schmuck, kein Marketing-Sprech. Reine, maschinenlesbare Performance.
                    </div>
                </section>

                <section class="block-segment">
                    <div>
                        <h2 class="tech-fakt-headline">03 // Protokoll- &amp; Header-Ineffizienz</h2>
                        <p class="tech-fakt-text">Veraltete Server-Protokolle, ineffiziente Daten-Kompression (Brotli-Defizit) und fehlerhafte HTTP-Header verzögern den initialen Datentransfer (TTFB). Crawler drosseln bei mangelhafter Server-Antwort sofort die Indexierungs-Frequenz.</p>
                    </div>
                    
                    <div class="competence-image-container">
                        <svg width="100%" height="120" viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" style="background:#161616; display:block;">
                            <text x="30" y="25" fill="#ffffff" opacity="0.3" font-family="monospace" font-size="9">SOLL: HTTP/3 (STREAMING)</text>
                            <line x1="30" y1="35" x2="370" y2="35" stroke="#222222" stroke-width="1" />
                            <rect x="150" y="30" width="15" height="10" fill="#ffffff" opacity="0.8" />
                            <rect x="175" y="30" width="15" height="10" fill="#ffffff" opacity="0.8" />
                            <rect x="200" y="30" width="15" height="10" fill="#ffffff" opacity="0.8" />
                            
                            <text x="30" y="70" fill="#ff3333" opacity="0.6" font-family="monospace" font-size="9">IST: HTTP/1.1 (LATENZ-STAU)</text>
                            <line x1="30" y1="80" x2="370" y2="80" stroke="#222222" stroke-width="1" />
                            <rect x="150" y="75" width="15" height="10" fill="#333333" />
                            <rect x="210" y="75" width="15" height="10" fill="#333333" />
                            <rect x="285" y="75" width="15" height="10" fill="#333333" />
                            
                            <line x1="200" y1="30" x2="200" y2="90" stroke="#00ff66" stroke-width="1" stroke-dasharray="2,2" />
                            <circle cx="200" cy="80" r="3" fill="#00ff66" />
                            <text x="223" y="93" fill="#00ff66" font-family="monospace" font-size="9">TTFB VERZÖGERUNG</text>
                        </svg>
                    </div>
                    
                    <div class="systemic-consequence">
                        <span>Systemische Konsequenz:</span> Die mangelhafte Performance der Transport-Schicht führt zu Timeouts bei externen Abfragesystemen und verhindert eine stabile Daten-Injektion.
                    </div>
                    
                    <div class="strategic-text">
                        <strong>03 // System-Intervention zum Festpreis</strong><br>
                        Ich saniere nicht nur den Core, sondern optimiere die gesamte Auslieferungs-Infrastruktur auf ein kompromissloses 100% Lighthouse-Äquivalent. Reine, maschinenlesbare Performance, KI-Ready und ohne Overhead.
                    </div>
                </section>

            </div>

            <section class="form-section">
                <h2>System Intervention zum Festpreis</h2>
                <div class="price-indicator">Investition: 4.800,– € Netto // Einmalig ohne Abo</div>
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
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
            <div class="manifest-banner" style="max-width: none; font-size: 11px; color: var(--text-muted); margin-top: 5px;">Kein Schmuck. Nur System. Kein Marketing-Sprech. Nur Daten.</div>
            <div class="footer-links">
                <a href="/datenschutz">Datenschutz</a> // 
                <a href="/impressum">Impressum</a> // 
                <a href="#" id="crmLink" onclick="handleCrmGateway(event)" style="color: #2c3a3c;">login</a>
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
            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 12px;">
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