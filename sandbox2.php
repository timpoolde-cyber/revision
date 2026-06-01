<?php
/**
 * REVISION100™ — SYSTEM INITIALISIERUNG, ROUTING, CRM-INTEGRATION & ADMIN-GATEWAY
 * Sende-Protokoll via PHPMailer & SQLite-Direct-Inject // Inklusive nativem CRM-Login
 */

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

// PHPMailer-Klassen einbinden
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

// 1. ROUTING-WEICHE (DYNAMIC CONTENT INJECTION)
$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$request_path = parse_url($request_uri, PHP_URL_PATH);
$request_path = trim($request_path, '/');

$page_title = "REVISION100™ — Quelltext-Sanierung bei Google-Rankingverlust";
$meta_desc  = "REVISION100™: Radikale Quelltext-Sanierung bei Google-Rankingverlust. Fokus: Inhaber & Geschäftsführer. Methode: Code-Reduktion. Tarif: Festpreis.";
$segment_headline = "// QUELLTEXT-SANIERUNG BEI GOOGLE-RANKINGVERLUST";

if ($request_path === 'website-relaunch-fehler') {
    $page_title = "REVISION100™ — Quelltext-Sanierung nach Relaunch-Fehlern";
    $meta_desc  = "REVISION100™: Korrektur von Code-Ballast und Ladeblockaden nach unsauberem Relaunch. Rankings zurückholen.";
    $segment_headline = "// QUELLTEXT-SANIERUNG NACH WEBSITE-RELAUNCH";
}
elseif ($request_path === 'klickeinbruch-kmu') {
    $page_title = "REVISION100™ — Sofort-Sanierung bei fatalem Klickeinbruch";
    $meta_desc  = "REVISION100™: Radikale Bereinigung blockierender Quelltext-Strukturen bei plötzlichem Verlust der organischen Klicks.";
    $segment_headline = "// SYSTEM-SANIERUNG BEI AKUTEM KLICKEINBRUCH";
}

// 2. FORMULAR-VERARBEITUNG & CRM-EINSPEISUNG
$status_meldung = "";
$status_typ = "";
$form_target_url = "";
$form_contact_mail = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Unterscheidung: Ist es der CRM-Login oder eine Audit-Anfrage?
    if (isset($_POST['crm_login_action'])) {
        // CRM LOGIN LOGIK (Nutzt die vorhandene Login-Funktion aus Ihrem CRM)
        $username = filter_input(INPUT_POST, 'crm_user', FILTER_DEFAULT);
        $password = filter_input(INPUT_POST, 'crm_pass', FILTER_DEFAULT);
        
        // Annahme: Ihre login()-Funktion existiert in session_handler.php oder auth.php
        // Falls Ihre auth-Funktion anders heißt, passen Sie den Funktionsaufruf hier an
        if (function_exists('login') && login($username, $password)) {
            header('Location: crm.php');
            exit;
        } else {
            $status_meldung = "FEHLER: Authentifizierung fehlgeschlagen. Zugriff verweigert.";
            $status_typ = "error";
        }
    } else {
        // NORMALE AUDIT-ANFRAGE
        $target_url = filter_input(INPUT_POST, 'target_url', FILTER_SANITIZE_URL);
        $contact_mail = filter_input(INPUT_POST, 'contact_mail', FILTER_VALIDATE_EMAIL);

        $form_target_url = $target_url;
        $form_contact_mail = $contact_mail;

        if (!$target_url || !$contact_mail) {
            $status_meldung = "FEHLER: Ungültige Dateneingabe. Parameter unvollständig.";
            $status_typ = "error";
        } else {
            try {
                $db_path = __DIR__ . '/data/rockets.db';
                $db = new PDO('sqlite:' . $db_path);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $parsed_domain = parse_url($target_url, PHP_URL_HOST);
                $company_name = $parsed_domain ? $parsed_domain : $target_url;
                $secret_token = bin2hex(random_bytes(16));

                $stmt_customer = $db->prepare("INSERT INTO customers (company, email, secret_token, created_at) VALUES (?, ?, ?, DATETIME('now'))");
                $stmt_customer->execute([$company_name, $contact_mail, $secret_token]);
                $customer_id = $db->lastInsertId();

                $stmt_project = $db->prepare("INSERT INTO projects (customer_id, target_url, tunnel, betrag, created_at, updated_at) VALUES (?, ?, 'erst-audit', 4800, DATETIME('now'), DATETIME('now'))");
                $stmt_project->execute([$customer_id, $target_url]);

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'send.one.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'system@revision100.de';
                $mail->Password   = 'qajac7y4tecu';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('system@revision100.de', 'REVISION100 System');
                $mail->addAddress('timo.pohlhaus@revision100.de');

                $mail->isHTML(false);
                $mail->Subject = 'REVISION100: Erst-Audit angefordert';
                
                $msg  = "PROTOKOLL: NEUANFRAGE SYSTEM-AUDIT (AUTOMATISCH INS CRM VERERBT)\n";
                $msg .= "========================================================\n";
                $msg .= "CUSTOMER ID:        " . $customer_id . "\n";
                $msg .= "EINSTIEGSPFAD:      /" . $request_path . "\n";
                $msg .= "SYSTEM-BASIS (URL): " . $target_url . "\n";
                $msg .= "KONTAKT (E-MAIL):   " . $contact_mail . "\n";
                $msg .= "ZEITSTEMPEL:        " . date("Y-m-d H:i:s") . " CEST\n";
                $msg .= "========================================================\n";
                
                $mail->Body = $msg;
                $mail->send();
                
                $status_meldung = "STATUS: SYSTEM-ANFRAGE REGISTRIERT // ANALYSE EINGELEITET.";
                $status_typ = "success";
                
                $form_target_url = "";
                $form_contact_mail = "";

            } catch (Exception $e) {
                $status_meldung = "FEHLER: Datenübertragung in den CRM-Maschinenraum blockiert.";
                $status_typ = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($meta_desc); ?>">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        /* --- SYSTEM DESIGN SYSTEM (HfG Ulm / Funktionalismus) --- */
        :root {
            --bg-color: #1a2324;
            --panel-bg: #111819;
            --border-color: #2c3a3c;
            --text-main: #d1dcd3;
            --text-muted: #a8b8ba;
            --teal-accent: #339999;
            --orange-action: #ff6600;
            --orange-hover: #e05500;
            --font-mono: ui-monospace, SFMono-Regular, "SF Pro Mono", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: var(--font-mono);
            font-size: 14px;
            line-height: 1.5;
            padding: 40px 20px;
        }

        .system-container {
            max-width: 1000px;
            margin: 0 auto;
            border: 1px solid var(--border-color);
            background-color: var(--panel-bg);
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        /* --- HEADER STRUCTURE --- */
        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 25px;
            margin-bottom: 30px;
        }

        .identity-block {
            max-width: 65%;
        }

        .logo-main {
            font-size: 26px;
            font-weight: bold;
            letter-spacing: 1px;
            line-height: 1.1;
            color: #ffffff;
            margin-bottom: 15px;
        }

        .claim-protocol {
            font-size: 13px;
            line-height: 1.6;
            color: var(--text-main);
        }

        .protocol-line {
            display: block;
        }

        .status-block {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #0b1011;
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 2px;
            font-size: 11px;
            letter-spacing: 1px;
        }

        .led-green-pulsing {
            width: 8px;
            height: 8px;
            background-color: #00ff66;
            border-radius: 50%;
            box-shadow: 0 0 8px #00ff66;
            animation: pulse-led 2s infinite ease-in-out;
        }

        @keyframes pulse-led {
            0% { opacity: 0.4; box-shadow: 0 0 2px #00ff66; }
            50% { opacity: 1; box-shadow: 0 0 10px #00ff66; }
            100% { opacity: 0.4; box-shadow: 0 0 2px #00ff66; }
        }

        /* --- DIAGNOSTIC INFOBLOCKS --- */
        .diagnostic-section {
            margin-bottom: 35px;
        }

        .grid-blocks {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            header { flex-direction: column; gap: 20px; }
            .identity-block { max-width: 100%; }
            .grid-blocks { grid-template-columns: 1fr; }
        }

        .info-block {
            border: 1px solid var(--border-color);
            background-color: #131c1e;
            padding: 18px;
        }

        .block-status {
            font-size: 11px;
            color: var(--orange-action);
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 8px;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 5px;
        }

        .block-meta {
            font-size: 13px;
            margin-bottom: 10px;
            color: var(--text-main);
        }

        .block-meta span {
            color: var(--text-muted);
        }

        /* --- SVG VECTOR GRAPHIC RESPONSIVE --- */
        .vector-graphic-container {
            border: 1px solid var(--border-color);
            background-color: #0d1314;
            padding: 20px;
            margin-bottom: 35px;
        }

        .graphic-title {
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 1px;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .graphic-split-flex {
            display: flex;
            gap: 30px;
        }

        .graphic-column {
            flex: 1;
            min-width: 280px;
        }

        .graphic-column.left-system {
            border-right: 1px dashed var(--border-color);
            padding-right: 15px;
        }

        @media (max-width: 768px) {
            .graphic-split-flex {
                flex-direction: column;
                gap: 40px;
            }
            .graphic-column.left-system {
                border-right: none;
                border-bottom: 1px dashed var(--border-color);
                padding-right: 0;
                padding-bottom: 30px;
            }
        }

        /* --- TERMINAL / FORM --- */
        .terminal-interface {
            border: 1px solid var(--border-color);
            background-color: #0f1617;
            padding: 25px;
        }

        .terminal-header {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-layout { grid-template-columns: 1fr; }
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        input[type="text"], input[type="email"], input[type="password"] {
            background-color: #182224;
            border: 1px solid var(--border-color);
            color: #ffffff;
            padding: 12px;
            font-family: var(--font-mono);
            font-size: 14px;
            border-radius: 2px;
        }

        input:focus {
            outline: none;
            border-color: var(--teal-accent);
        }

        .action-container {
            grid-column: 1 / -1;
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 10px;
        }

        .financial-metric {
            font-size: 13px;
            color: var(--text-muted);
            background-color: #141d1f;
            padding: 10px 15px;
            border-left: 3px solid var(--teal-accent);
        }

        .terminal-status-display {
            font-size: 13px;
            padding: 12px;
            border: 1px solid var(--border-color);
            background-color: #0b1011;
            font-family: var(--font-mono);
        }

        .terminal-status-display.success {
            color: #00ff66;
            border-color: #006622;
        }

        .terminal-status-display.error {
            color: #ff6600;
            border-color: #662200;
        }

        button.btn-execute {
            background-color: var(--orange-action);
            color: #ffffff;
            border: none;
            padding: 16px 20px;
            font-family: var(--font-mono);
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
            cursor: pointer;
            text-transform: uppercase;
            border-radius: 2px;
            transition: background 0.2s;
        }

        button.btn-execute:hover {
            background-color: var(--orange-hover);
        }

        /* --- MINIMALISTIC ADMIN MODAL / OVERLAY --- */
        .crm-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(11, 16, 17, 0.95);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }

        .crm-modal-box {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            max-width: 400px;
            width: 100%;
            padding: 25px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.8);
        }

        .crm-modal-header {
            font-size: 12px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            letter-spacing: 1px;
        }

        .crm-close-btn {
            cursor: pointer;
            color: var(--orange-action);
            font-weight: bold;
        }

        /* --- FOOTER --- */
        footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        footer a {
            color: var(--text-muted);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="system-container">

        <header>
            <div class="identity-block">
                <div class="logo-main">REVISION100™</div>
                
                <div class="claim-protocol" style="font-size: 13px; line-height: 1.8; color: var(--text-main); margin-top: 15px;">
                    <span class="protocol-line" style="color: #ffffff; font-weight: bold; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 8px; display: block; letter-spacing: 0.5px;">
                        <?php echo htmlspecialchars($segment_headline); ?>
                    </span>
                    <span class="protocol-line" style="display: flex; gap: 15px; margin-bottom: 2px;">
                        <span style="color: var(--text-muted); width: 80px; display: inline-block; flex-shrink: 0;">FOKUS:</span>
                        <span style="color: var(--text-main);">Inhaber & Geschäftsführer (KMU)</span>
                    </span>
                    <span class="protocol-line" style="display: flex; gap: 15px; margin-bottom: 2px;">
                        <span style="color: var(--text-muted); width: 80px; display: inline-block; flex-shrink: 0;">METHODE:</span>
                        <span style="color: var(--text-main);">Radikale Code-Reduktion</span>
                    </span>
                    <span class="protocol-line" style="display: flex; gap: 15px;">
                        <span style="color: var(--text-muted); width: 80px; display: inline-block; shrink: 0;">TARIF:</span>
                        <span style="color: var(--orange-action); font-weight: bold;">4.800 EUR netto Festpreis</span>
                    </span>
                </div>
            </div>
            
            <div class="status-block">
                <div class="led-green-pulsing" aria-hidden="true"></div>
                <span>DIAGNOSTIK-TERMINAL BEREIT</span>
            </div>
        </header>

        <main>
            <section class="diagnostic-section">
                <div class="grid-blocks">
                    
                    <div class="info-block">
                        <div class="block-status">STATUS: CODE-BALLAST</div>
                        <div class="block-meta"><span>Ursache:</span> Überladene Themes, ungenutzte Bibliotheken, verschachtelte DOM-Ebenen.</div>
                        <div class="block-meta"><span>Konsequenz:</span> Crawler brechen Scan vorzeitig ab. Relevante Inhalte werden ignoriert.</div>
                    </div>

                    <div class="info-block">
                        <div class="block-status">STATUS: MASCHINENLESBARKEIT SPERRE</div>
                        <div class="block-meta"><span>Ursache:</span> Fehlerhafte semantische HTML5-Struktur der Standard-Systeme.</div>
                        <div class="block-meta"><span>Konsequenz:</span> KI-Crawler und Such-Bots können Relevanz-Faktoren nicht parsen.</div>
                    </div>

                    <div class="info-block">
                        <div class="block-status">STATUS: PERFORMANCE DEFIZIT</div>
                        <div class="block-meta"><span>Ursache:</span> Render-blockierendes JavaScript, mangelhafte Server-Antwortzeiten.</div>
                        <div class="block-meta"><span>Konsequenz:</span> Core Web Vitals ungenügend. Algorithmische Abwertung durch Google.</div>
                    </div>

                </div>
            </section>

            <section class="vector-graphic-container">
                <div class="graphic-title">System-Diagramm: Crawler-Traversierung (DOM-Tree Analyse)</div>
                <div class="graphic-split-flex">
                    <div class="graphic-column left-system">
                        <svg viewBox="0 0 380 320" width="100%" height="100%" style="background-color: transparent; display: block;" xmlns="http://www.w3.org/2000/svg">
                            <text x="0" y="20" fill="#a8b8ba" font-family="ui-monospace, monospace" font-size="11" letter-spacing="1">[IST-ZUSTAND: UNREDUZIERTER QUELLTEXT]</text>
                            <text x="0" y="40" fill="#ff6600" font-family="ui-monospace, monospace" font-size="10">STATUS: PARSING ABGEBROCHEN // EFFIZIENZ: 12%</text>
                            <line x1="10" y1="90" x2="60" y2="90" stroke="#339999" stroke-width="1.5" />
                            <text x="10" y="80" fill="#a8b8ba" font-family="ui-monospace, monospace" font-size="9">CRAWLER</text>
                            <line x1="60" y1="90" x2="60" y2="230" stroke="#2c3a3c" stroke-width="1.5" />
                            <line x1="60" y1="130" x2="120" y2="130" stroke="#2c3a3c" stroke-width="1.5" />
                            <line x1="120" y1="130" x2="120" y2="180" stroke="#2c3a3c" stroke-width="1.5" />
                            <line x1="120" y1="160" x2="180" y2="160" stroke="#2c3a3c" stroke-width="1.5" />
                            <line x1="180" y1="155" x2="190" y2="165" stroke="#ff6600" stroke-width="1.5" />
                            <line x1="190" y1="155" x2="180" y2="165" stroke="#ff6600" stroke-width="1.5" />
                            <text x="125" y="155" fill="#ff6600" font-family="ui-monospace, monospace" font-size="9">DIV-BALLAST [X]</text>
                            <line x1="60" y1="210" x2="150" y2="210" stroke="#339999" stroke-width="1.5" stroke-dasharray="2,2" />
                            <line x1="150" y1="205" x2="160" y2="215" stroke="#ff6600" stroke-width="1.5" />
                            <line x1="160" y1="205" x2="150" y2="215" stroke="#ff6600" stroke-width="1.5" />
                            <text x="70" y="202" fill="#ff6600" font-family="ui-monospace, monospace" font-size="9">RENDER-BLOCK [X]</text>
                            <circle cx="240" cy="130" r="3" fill="#2c3a3c" />
                            <circle cx="240" cy="210" r="3" fill="#2c3a3c" />
                            <text x="255" y="133" fill="#2c3a3c" font-family="ui-monospace, monospace" font-size="9">DATA_01</text>
                            <text x="255" y="213" fill="#2c3a3c" font-family="ui-monospace, monospace" font-size="9">DATA_02</text>
                            <text x="10" y="290" fill="#a8b8ba" font-family="ui-monospace, monospace" font-size="10">-&gt; INHALTE NICHT ERREICHT // RANKINGVERLUST</text>
                        </svg>
                    </div>
                    <div class="graphic-column">
                        <svg viewBox="0 0 380 320" width="100%" height="100%" style="background-color: transparent; display: block;" xmlns="http://www.w3.org/2000/svg">
                            <text x="10" y="20" fill="#a8b8ba" font-family="ui-monospace, monospace" font-size="11" letter-spacing="1">[ZIEL-SYSTEM: REVISION100™]</text>
                            <text x="10" y="40" fill="#00ff66" font-family="ui-monospace, monospace" font-size="10">STATUS: OPERATIV // EFFIZIENZ: 100%</text>
                            <line x1="20" y1="90" x2="70" y2="90" stroke="#00ff66" stroke-width="1.5" />
                            <text x="20" y="80" fill="#a8b8ba" font-family="ui-monospace, monospace" font-size="9">CRAWLER</text>
                            <line x1="70" y1="90" x2="70" y2="260" stroke="#00ff66" stroke-width="1.5" />
                            <line x1="70" y1="130" x2="130" y2="130" stroke="#00ff66" stroke-width="1" />
                            <circle cx="130" cy="130" r="4" fill="#00ff66" />
                            <text x="145" y="133" fill="#d1dcd3" font-family="ui-monospace, monospace" font-size="9">DATA_01: RELEVANZ (PARSED)</text>
                            <line x1="70" y1="175" x2="130" y2="175" stroke="#00ff66" stroke-width="1" />
                            <circle cx="130" cy="175" r="4" fill="#00ff66" />
                            <text x="145" y="178" fill="#d1dcd3" font-family="ui-monospace, monospace" font-size="9">DATA_02: PRODUKTE (PARSED)</text>
                            <line x1="70" y1="220" x2="130" y2="220" stroke="#00ff66" stroke-width="1" />
                            <circle cx="130" cy="220" r="4" fill="#00ff66" />
                            <text x="145" y="223" fill="#d1dcd3" font-family="ui-monospace, monospace" font-size="9">DATA_03: KONTEXT  (PARSED)</text>
                            <text x="20" y="290" fill="#00ff66" font-family="ui-monospace, monospace" font-size="10">-&gt; VOLLSTÄNDIGE INDIZIERUNG // RANKINGS STABILISIERT</text>
                        </svg>
                    </div>
                </div>
            </section>

            <section class="terminal-interface">
                <div class="terminal-header">PROZESS: SYSTEM-AUDIT EINLEITEN</div>
                <form action="" method="POST" class="form-layout">
                    <div class="input-group">
                        <label for="target-url">System-Basis (URL):</label>
                        <input type="text" id="target-url" name="target_url" placeholder="https://www.ihre-kmu-unternehmensseite.de" value="<?php echo htmlspecialchars($form_target_url); ?>" required>
                    </div>
                    <div class="input-group">
                        <label for="contact-mail">Ziel-Adresse (E-Mail):</label>
                        <input type="email" id="contact-mail" name="contact_mail" placeholder="inhaber@ihre-firma.de" value="<?php echo htmlspecialchars($form_contact_mail); ?>" required>
                    </div>
                    <div class="action-container">
                        <div class="financial-metric">
                            Hebel: Identifikation der Code-Blockaden binnen 24 Std. // Optionale Quelltext-Sanierung zum Festpreis: 4.800 EUR netto.
                        </div>
                        <?php if (!empty($status_meldung)): ?>
                            <div class="terminal-status-display <?php echo $status_typ; ?>">
                                <?php echo htmlspecialchars($status_meldung); ?>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn-execute">Operativen Eingriff starten: Erst-Audit anfordern</button>
                    </div>
                </form>
            </section>
        </main>

        <div id="crmModal" class="crm-modal-overlay">
            <div class="crm-modal-box">
                <div class="crm-modal-header">
                    <span>SYSTEM-GATEWAY: MASCHINENRAUM</span>
                    <span class="crm-close-btn" onclick="toggleCrmModal(false)">[X]</span>
                </div>
                <form action="" method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                    <input type="hidden" name="crm_login_action" value="1">
                    <div class="input-group">
                        <label for="crm-user">Identifikation (User):</label>
                        <input type="text" id="crm-user" name="crm_user" required autocomplete="username">
                    </div>
                    <div class="input-group">
                        <label for="crm-pass">Schlüssel (Passwort):</label>
                        <input type="password" id="crm-pass" name="crm_pass" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-execute" style="padding: 12px; font-size: 12px; margin-top: 10px;">Login verifizieren</button>
                </form>
            </div>
        </div>

        <footer>
            <div>Revision100™ ein Service von Timo E. Pohlhaus – <a href="https://timpool.de" style="color: var(--text-muted); text-decoration: none;">timpool.de</a></div>
            <div class="manifest-banner" style="max-width: none; font-size: 11px; color: var(--text-muted); margin-top: 5px;">Manifest: Kein Schmuck. Nur System. Kein Marketing-Sprech. Nur Daten.</div>
            <div class="footer-links">
                <a href="/datenschutz">Datenschutz</a> // 
                <a href="/impressum">Impressum</a> // 
                <a href="#" id="crmLink" onclick="handleCrmGateway(event)" style="color: #2c3a3c;">[Maschinenraum]</a>
            </div>
        </footer>

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
                // Wenn eingeloggt, direkt in den Maschinenraum springen
                window.location.href = 'crm.php';
            } else {
                // Wenn nicht eingeloggt, unauffällige Maske öffnen
                toggleCrmModal(true);
            }
        } catch (error) {
            toggleCrmModal(true);
        }
    }
    </script>
</body>
</html>