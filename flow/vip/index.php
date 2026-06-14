<?php
declare(strict_types=1);
// /flow/vip/index.php - r400™ kundenportal & audit-routing

// ===== env laden =====
function load_env(string $path): void {
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $k = trim(substr($line, 0, $pos));
        $v = trim(trim(substr($line, $pos + 1)), "\"'");
        if ($k !== '' && getenv($k) === false) { putenv("$k=$v"); $_ENV[$k] = $v; }
    }
}
load_env(__DIR__ . '/../../.env');

function env(string $k, ?string $d = null): ?string {
    $v = getenv($k);
    return ($v === false || $v === '') ? $d : $v;
}

$cfg = [
    'pagespeed_key'  => env('PAGESPEED_API_KEY'),
    'anthropic_key'  => env('ANTHROPIC_API_KEY'),
    'anthropic_model'=> env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    'mail_to'        => env('MAIL_TO', 'timpool.de@gmail.com'),
    'mail_from'      => env('MAIL_FROM', 'r400@revision100.de'),
    'db_path'        => dirname(__DIR__, 2) . '/data/rockets.db',
];

// ===== helfer =====
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_private_ip(string $ip): bool {
    $ip = trim($ip);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function http_get(string $url, int $timeout = 30): ?string {
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        error_log('[r400] invalid url: ' . $url);
        return null;
    }

    $host = $parsed['host'];
    $ips = @gethostbyname($host);
    if ($ips === false || $ips === $host) {
        error_log('[r400] dns resolution failed: ' . $host);
        return null;
    }

    if (is_private_ip($ips)) {
        error_log('[r400] blocked private ip: ' . $ips . ' for host: ' . $host);
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'R400/1.0'
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($result === false) {
        error_log('[r400] curl: ' . $error);
        return null;
    }
    return is_string($result) ? $result : null;
}

function http_post(string $url, array $headers, string $body, int $timeout = 30): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($result === false) {
        error_log('[r400] curl post: ' . $error);
        return null;
    }
    return is_string($result) ? $result : null;
}

function clamp_score(mixed $v): int {
    return (int)round(max(0.0, min(1.0, (float)$v)) * 100);
}

// Telefonnummern-Formatierung Funktion
function format_phone_number(string $value): string {
    if (empty($value)) return '';

    // Entferne Leerzeichen, Trennstriche, Schrägstriche
    $cleaned = preg_replace('/[\s\-\/]/', '', $value);

    // Wenn mit '00' beginnt: ersetze durch '+'
    if (strpos($cleaned, '00') === 0) {
        $cleaned = '+' . substr($cleaned, 2);
    }
    // Wenn mit einzelner '0' beginnt: ersetze durch '+49'
    else if (strpos($cleaned, '0') === 0) {
        $cleaned = '+49' . substr($cleaned, 1);
    }
    // Wenn bereits mit '+' beginnt: unverändert
    // (keine weitere Aktion nötig)

    return $cleaned;
}

// ===== ki-score heuristik =====
function ki_score(string $html): int {
    $score = 0;
    $patterns = [
        15 => '/<title[^>]*>\s*\S/i',
        15 => '/<meta[^>]+name=["\']description["\'][^>]+content=["\']\s*\S/i',
        20 => '/<script[^>]+type=["\']application\/ld\+json["\']/i',
        10 => '/<html[^>]+lang=/i',
        10 => '/<link[^>]+rel=["\']canonical["\']/i',
        10 => '/<meta[^>]+property=["\']og:/i',
        10 => '/<h1[\s>]/i',
        10 => '/<(article|main|section|nav|header|footer)[\s>]/i',
    ];
    foreach ($patterns as $points => $regex) {
        if (preg_match($regex, $html)) {
            $score += $points;
        }
    }
    return min(100, $score);
}

// ===== anthropic audit-analyse =====
function anthropic_analysis(string $url, array $scores, array $cfg): ?array {
    if (!$cfg['anthropic_key']) {
        return null;
    }

    $prompt = "du bist ein unbestechlicher system-auditor für web-performance und ki-schnittstellen-readiness.\n"
        . "antworte nur mit validem json. keine präambel, kein markdown, keine fences.\n\n"
        . "url: {$url}\n"
        . "scores: tempo {$scores['tempo']}, sichtbar {$scores['sichtbar']}, ki-lesbar {$scores['ki']}, struktur {$scores['struktur']}\n\n"
        . "antworte mit genau diesem json-schema:\n"
        . "{\n"
        . "  \"quick_json\": {\n"
        . "    \"tempo\": \"1 nüchterner, präziser satz zur ladegeschwindigkeit.\",\n"
        . "    \"sichtbar\": \"1 nüchterner, präziser satz zur auffindbarkeit.\",\n"
        . "    \"ki\": \"1 nüchterner, präziser satz zur maschinenlesbarkeit des quelltexts.\",\n"
        . "    \"struktur\": \"1 nüchterner, präziser satz zur code-qualität.\",\n"
        . "    \"recommendation\": \"die wichtigste, sofort umzusetzende technische handlungsempfehlung.\"\n"
        . "  },\n"
        . "  \"deep_text\": \"eine ausführliche, tiefgehende code-analyse (ca. 3-4 absätze). analysiere schonungslos die technische schuld, die architektur-bremsen und die konkreten barrieren für ki-crawler. nutze rein technische fakten, den funktionalen du-stil, keine werblichen floskeln, komplett kleingeschrieben.\"\n"
        . "}\n";

    $payload = json_encode([
        'model' => $cfg['anthropic_model'],
        'max_tokens' => 1500,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ], JSON_UNESCAPED_UNICODE);

    $headers = [
        'content-type: application/json',
        'x-api-key: ' . $cfg['anthropic_key'],
        'anthropic-version: 2023-06-01'
    ];

    $response = http_post('https://api.anthropic.com/v1/messages', $headers, $payload, 45);
    if (!$response) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['content'][0]['text'])) {
        error_log('[r400] anthropic response invalid');
        return null;
    }

    $text = trim($data['content'][0]['text']);
    // entferne markdown-fences falls vorhanden
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```\s*$/', '', $text);
    $text = trim($text);

    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        error_log('[r400] anthropic parse failed: ' . substr($text, 0, 200));
        return null;
    }

    return $parsed;
}

// ===== google pagespeed api =====
function pagespeed_scores(string $url, string $api_key): ?array {
    $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?'
        . 'url=' . urlencode($url)
        . '&strategy=mobile'
        . '&category=performance'
        . '&category=seo'
        . '&category=best-practices'
        . '&key=' . urlencode($api_key);

    $json = http_get($api_url, 40);
    if (!$json) {
        error_log('[r400] pagespeed timeout');
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['lighthouseResult']['categories'])) {
        error_log('[r400] pagespeed no categories');
        return null;
    }

    $cats = $data['lighthouseResult']['categories'];
    return [
        'tempo' => clamp_score($cats['performance']['score'] ?? 0),
        'sichtbar' => clamp_score($cats['seo']['score'] ?? 0),
        'struktur' => clamp_score($cats['best-practices']['score'] ?? 0),
    ];
}

// ===== datenbank =====
function db_conn(string $path): PDO {
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // A1: Idempotente Migration für channel-Spalte
    try {
        $cols = $pdo->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('channel', $cols, true)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN channel TEXT DEFAULT 'lead'");
        }
    } catch (Throwable $e) { /* ignore */ }

    foreach (['short_code' => "TEXT", 'notified_at' => "DATETIME"] as $col => $type) {
        try {
            $cols = $pdo->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array($col, $cols, true)) $pdo->exec("ALTER TABLE projects ADD COLUMN $col $type");
        } catch (Throwable $e) { /* still */ }
    }

    return $pdo;
}

function db_get_by_token(PDO $db, string $token): ?array {
    $stmt = $db->prepare('SELECT c.id as cid, c.customer_name, c.email, p.id as pid, p.target_url, p.tunnel FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.secret_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function db_get_psi_results(PDO $db, int $project_id): ?array {
    $stmt = $db->prepare('SELECT * FROM psi_results WHERE project_id = ? ORDER BY fetch_timestamp DESC LIMIT 1');
    $stmt->execute([$project_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function db_create_customer(PDO $db, string $name, string $email, string $phone): int {
    $stmt = $db->prepare('INSERT INTO customers (customer_name, email, phone_mobile) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $phone]);
    return (int)$db->lastInsertId();
}

function db_create_project(PDO $db, int $customer_id, string $customer_name, string $target_url, string $token): int {
    $stmt = $db->prepare('INSERT INTO projects (customer_id, customer_name, target_url, tunnel, channel, secret_token, token_created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
    $stmt->execute([$customer_id, $customer_name, $target_url, 'anfrage', 'vip', $token]);
    return (int)$db->lastInsertId();
}

function db_save_psi_results(PDO $db, int $project_id, array $scores, ?array $analysis): int {
    $quick_json = $analysis && isset($analysis['quick_json']) ? json_encode($analysis['quick_json'], JSON_UNESCAPED_UNICODE) : null;
    $deep_text = $analysis ? ($analysis['deep_text'] ?? null) : null;

    $stmt = $db->prepare('INSERT INTO psi_results (project_id, strategy, performance_score, seo_score, best_practices_score, accessibility_score, report_quick_json, report_deep, fetch_timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
    $stmt->execute([
        $project_id,
        'mobile',
        $scores['tempo'] ?? 0,
        $scores['sichtbar'] ?? 0,
        $scores['struktur'] ?? 0,
        $scores['ki'] ?? 0,
        $quick_json,
        $deep_text
    ]);
    return (int)$db->lastInsertId();
}

function db_update_project_phase(PDO $db, int $project_id, string $phase): void {
    $stmt = $db->prepare('UPDATE projects SET tunnel = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$phase, $project_id]);
}

// ===== routing =====
$token = $_GET['t'] ?? $_GET['token'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$db = db_conn($cfg['db_path']);

$customer = null;
$project = null;
$psi = null;

if ($token) {
    $customer = db_get_by_token($db, $token);
    if ($customer && $customer['pid']) {
        $project = $customer;
        $psi = db_get_psi_results($db, $project['pid']);

        // B6: Tracking-Logik — token_used_at setzen (außer bei adm=1)
        if (!(isset($_GET['adm']) && $_GET['adm'] === '1')) {
            try {
                // Setze token_used_at nur einmalig (wenn noch leer)
                $stmt = $db->prepare('UPDATE projects SET token_used_at = CURRENT_TIMESTAMP WHERE id = ? AND token_used_at IS NULL');
                $stmt->execute([$project['pid']]);
            } catch (Throwable $e) {
                error_log('[r400] token tracking error: ' . $e->getMessage());
            }
        }
    }
}

// GET-basierter Opt-Out Handler: Direkte DB-Update mit projects.secret_token
if ($method === 'GET' && $token && isset($_GET['action']) && $_GET['action'] === 'optout') {
    try {
        $stmt = $db->prepare('UPDATE projects SET tunnel = ? WHERE secret_token = ?');
        $stmt->execute(['abgeschaltet', $token]);
        header('Location: ?t=' . urlencode($token), true, 303);
        exit;
    } catch (Throwable $ex) {
        error_log('[r400] optout error: ' . $ex->getMessage());
    }
}

// form submission
if ($method === 'POST' && $token && isset($_POST['action']) && $_POST['action'] === 'deactivate_notifications') {
    if ($project && $project['pid']) {
        try {
            db_update_project_phase($db, $project['pid'], 'abgeschaltet');
            json_response(['ok' => true, 'message' => 'benachrichtigungen deaktiviert']);
        } catch (Throwable $ex) {
            error_log('[r400] deactivate error: ' . $ex->getMessage());
            json_response(['ok' => false, 'error' => 'fehler beim deaktivieren'], 500);
        }
    }
}

// form submission
if ($method === 'POST' && !$token) {
    $url = trim($_POST['url'] ?? '');
    $contact_primary = trim($_POST['contact_primary'] ?? $_POST['email'] ?? '');
    $contact_secondary = trim($_POST['contact_secondary'] ?? $_POST['phone'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if (!$url) {
        json_response(['ok' => false, 'error' => 'url erforderlich'], 400);
    }

    // URL-Toleranz: Fehlender Protokoll-Prefix hinzufügen
    if (!empty($url) && strpos($url, 'http') === false) {
        $url = 'https://' . $url;
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        json_response(['ok' => false, 'error' => 'ungültige url'], 400);
    }

    // Intelligente Feldidentifikation (Startet mit Ziffer/+/0 → Telefon, enthält @ → E-Mail)
    $email = '';
    $phone = '';

    $primary_starts_with_digit = preg_match('/^[\d+]/', $contact_primary);
    $primary_has_at_sign = strpos($contact_primary, '@') !== false;

    if ($primary_has_at_sign) {
        $email = $contact_primary;
        if (!empty($contact_secondary)) {
            $phone = $contact_secondary;
        }
    } else if ($primary_starts_with_digit || preg_match('/^0\d/', $contact_primary)) {
        $phone = $contact_primary;
        if (!empty($contact_secondary)) {
            $email = $contact_secondary;
        }
    } else {
        // Fallback: Primary als E-Mail
        $email = $contact_primary;
        if (!empty($contact_secondary)) {
            $phone = $contact_secondary;
        }
    }

    // B2: Akzeptiere email ODER phone (nicht mehr AND)
    if (empty($email) && empty($phone)) {
        json_response(['ok' => false, 'error' => 'e-mail oder telefon erforderlich'], 400);
    }

    // Formatiere Telefonnummer ins internationale Format
    if (!empty($phone)) {
        $phone = format_phone_number($phone);
    }

    // token generieren
    $token = bin2hex(random_bytes(16));

    try {
        // kunde anlegen
        $customer_id = db_create_customer($db, $name ?: 'unbekannt', $email, $phone ?: '');
        // projekt anlegen (token lebt am projekt)
        $project_id = db_create_project($db, $customer_id, $name ?: ($email ?: 'Unbekannt'), $url, $token);

        // B4: Eingangsbestätigung senden (SMS wenn phone, sonst mail)
        $confirmation_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://')
            . $_SERVER['HTTP_HOST']
            . dirname($_SERVER['PHP_SELF'])
            . '?t=' . urlencode($token);

        if (!empty($phone)) {
            // B4: SMS-Versand für Phone-Kunden (direkt, nicht via Worker)
            require_once __DIR__ . '/../../core/lib/SipgateClient.php';
            $sipgate = new SipgateClient();
            $sms_message = "// r400™ // website-revision\n"
                         . "deine anfrage ist eingegangen.\n"
                         . "hier geht's zu deinen ergebnissen:\n"
                         . $confirmation_url . "\n";
            $sipgate->sendSMS($phone, $sms_message);
        } else if (!empty($email)) {
            // Mail-Versand (direkt, nur Kunde)
            $subject = 'r400 · deine anfrage ist eingegangen';
            $body = "hallo" . ($name ? ' ' . $name : '') . ",\n\n"
                . "deine anfrage ist eingegangen.\n"
                . "hier geht's zu deinen ergebnissen:\n"
                . $confirmation_url . "\n\n"
                . "die erste analyse läuft gerade.\n"
                . "in kürze bekommst du den quick report.\n\n"
                . "viele grüße\n"
                . "r400\n";
            $headers = 'From: ' . $cfg['mail_from'] . "\r\nContent-Type: text/plain; charset=utf-8\r\n";
            @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        }

        // Mail an admin (mit KP-Link und Token)
        $admin_subject = 'r400 neue anfrage: ' . ($email ?: $phone);
        $admin_body = "neue anfrage über r400 kundenportal\n"
            . "name: " . ($name ?: 'nicht angegeben') . "\n"
            . "email: " . ($email ?: 'nicht angegeben') . "\n"
            . "telefon: " . ($phone ?: 'nicht angegeben') . "\n"
            . "url: " . $url . "\n"
            . "token: " . $token . "\n"
            . "kp-link: " . $confirmation_url . "\n"
            . "zeit: " . date('c') . "\n";
        $admin_headers = 'From: ' . $cfg['mail_from'] . "\r\nContent-Type: text/plain; charset=utf-8\r\n";
        @mail($cfg['mail_to'], '=?UTF-8?B?' . base64_encode($admin_subject) . '?=', $admin_body, $admin_headers);

        json_response(['ok' => true, 'token' => $token]);

    } catch (Throwable $ex) {
        error_log('[r400] db error: ' . $ex->getMessage());
        json_response(['ok' => false, 'error' => 'datenbankfehler'], 500);
    }
}

// ===== html rendering =====
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>r400™ audit</title>
<style>
:root {
    --bg: #0b0b0c;
    --card: #131315;
    --line: #232327;
    --in: #161618;
    --fg: #f4f4f5;
    --mu: #8a8a92;
    --di: #6f6f78;
    --gr: #16c784;
    --gk: #06150f;
    --am: #e0a52e;
    --rd: #e2674a;
    --mo: ui-monospace, 'SF Mono', 'Menlo', monospace;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);font-family:var(--mo);font-size:13px;line-height:1.5;-webkit-font-smoothing:antialiased}
.wrap{max-width:600px;margin:0 auto;padding:32px 20px 60px}
.section{margin-bottom:28px}
.title{font-size:14px;font-weight:bold;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:8px}
.form-group{margin-bottom:12px;display:flex;flex-direction:column;gap:4px}
.form-label{font-size:11px;text-transform:uppercase;color:var(--di);font-weight:bold}
.form-input{padding:10px;border:1px solid var(--line);font-family:var(--mo);font-size:13px;background:var(--in);color:var(--fg);width:100%;box-sizing:border-box}
.form-input:focus{outline:none;border-color:var(--gr)}
.form-input.required{border-color:var(--rd)}
.form-input.optional{border-color:var(--gr)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
.cell{background:var(--card);border:1px solid var(--line);padding:12px 14px;display:flex;flex-direction:column;gap:6px}
.score-num{font-size:20px;font-weight:bold;color:var(--gr)}
.score-label{font-size:11px;text-transform:uppercase;color:var(--mu)}
.loader{display:inline-block;width:20px;height:20px;border:2px solid var(--line);border-top-color:var(--gr);border-radius:50%;animation:spin 0.8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.loader-text{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--mu)}
.btn{padding:10px 16px;border:1px solid var(--line);background:var(--gr);color:var(--gk);font-family:var(--mo);font-size:12px;font-weight:bold;cursor:pointer;text-transform:uppercase;width:100%}
.btn:hover{opacity:0.9}
.text-block{background:var(--card);border:1px solid var(--line);padding:14px;font-size:12px;line-height:1.6;color:var(--fg)}
.form-2col{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.footer{text-align:center;font-size:11px;color:var(--di);margin-top:40px;padding-top:20px;border-top:1px solid var(--line)}
.footer a{color:var(--gr);text-decoration:none}
.footer a:hover{text-decoration:underline}
.vip-pending-container{max-width:600px;margin:40px auto;font-family:monospace;color:#ffffff;line-height:1.6}
.vip-title{font-size:14px;font-weight:700;letter-spacing:1px;margin-bottom:24px;color:#ffffff}
.vip-msg-grey{color:#777777;margin-bottom:8px}
.vip-msg-white{color:#ffffff;margin-bottom:24px}
.vip-status-badge{display:inline-block;border:1px solid #333333;padding:6px 12px;font-size:11px;color:#00FF66;text-transform:lowercase}
.vip-action-section{margin-top:60px;border-top:1px solid #222222;padding-top:16px}
.vip-btn-optout{color:#555555;text-decoration:none;font-size:11px;transition:color 0.2s ease}
.vip-btn-optout:hover{color:#ff3333}
</style>
</head>
<body>
<div class="wrap">

<?php if (!$customer): ?>

<div class="section">
    <div class="title">R400™ // website-revision</div>

    <div class="text-block" style="margin-bottom: 24px; border: none; padding: 0;">
        hey, du hast meine karte. hier kannst du deine url checken.<br>
        ist deine website schnell genug — oder weg vom ki-fenster?
    </div>

    <form method="post" onsubmit="return submitForm(event)" id="auditForm">
        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-weight: bold; text-transform: uppercase;">WEBSITE *</label>
                <input type="text" id="url_field" name="url" class="form-input required" required placeholder="z.b. example.de" autocomplete="url" style="width: 100%; display: block;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-weight: bold; text-transform: uppercase;">MAIL oder MOBILE *</label>
                <input type="text" id="contact_primary" name="contact_primary" class="form-input required" required placeholder="damit ich dich erreichen kann" style="width: 100%; display: block;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="text-transform: uppercase;">NAME</label>
                <input type="text" id="customer_name" name="customer_name" class="form-input optional" placeholder="optional" autocomplete="name" style="width: 100%; display: block;">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" id="contact_secondary_label" style="text-transform: uppercase;">MOBILNUMMER (OPTIONAL)</label>
                <input type="text" id="contact_secondary" name="contact_secondary" class="form-input optional" placeholder="+49 123 456789" autocomplete="tel" style="width: 100%; display: block;">
            </div>
        </div>

        <button type="submit" class="btn" id="submitBtn" style="width: 100%; text-transform: uppercase;">[ CHECK STARTEN → ]</button>
    </form>

    <div class="text-block" style="margin-top: 16px; color: var(--di); font-size: 11px;">
        // signal kommt in kürze.
    </div>
</div>

<?php elseif ($project['tunnel'] === 'anfrage'): ?>

<div class="vip-pending-container">

    <div class="vip-title">PORTAL — STATE 1 // PENDING</div>

    <div class="vip-msg-grey">// deine anfrage ist eingegangen.</div>

    <div class="vip-msg-white">
        die analyse läuft.<br>
        du bekommst eine nachricht sobald<br>
        die ersten werte vorliegen.
    </div>

    <div class="vip-status-badge">status: wird bearbeitet</div>

    <div class="vip-action-section">
        <a href="?t=<?php echo htmlspecialchars($token); ?>&action=optout" class="vip-btn-optout">
            // benachrichtigungen stoppen &rarr;
        </a>
        <div style="margin-top: 20px; font-size: 11px; color: var(--di);">
            <a href="#ueber" style="color: var(--gr); text-decoration: none;">// über timo →</a>
        </div>
    </div>

</div>

<?php elseif ($project['tunnel'] === 'abgeschaltet'): ?>

<div class="vip-pending-container">

    <div class="vip-title">PORTAL — STATE // TERMINATED</div>

    <div class="vip-msg-grey">// prozess auf deinen wunsch beendet.</div>

    <div class="vip-msg-white">
        es werden keine daten mehr verarbeitet.
    </div>

    <div class="vip-action-section">
        <div style="font-size: 11px;">
            <a href="#ueber" style="color: var(--gr); text-decoration: none;">// über timo →</a>
        </div>
    </div>

</div>

<?php elseif ($project['tunnel'] === 'bewertet' && $psi): ?>

<div class="section">
    <div class="title">PORTAL — STATE 2 // QUICK REPORT</div>

    <div class="text-block" style="margin-bottom: 24px; border: none; padding: 0;">
        // erste auswertung fertig.
    </div>

    <?php
    $quick = json_decode($psi['report_quick_json'], true);
    if (is_array($quick)):
        $qj = $quick;
    ?>

    <div class="report-scores" style="margin-bottom: 32px;">
        <div class="score-row" style="margin-bottom: 20px;">
            <div style="font-weight: bold; font-size: 16px;">[ <?= (int)$psi['performance_score'] ?> ] TEMPO</div>
            <div style="font-size: 13px; margin-top: 4px; color: var(--fg);"><?= e($qj['tempo'] ?? '') ?></div>
        </div>
        <div class="score-row" style="margin-bottom: 20px;">
            <div style="font-weight: bold; font-size: 16px;">[ <?= (int)$psi['seo_score'] ?> ] SICHTBAR</div>
            <div style="font-size: 13px; margin-top: 4px; color: var(--fg);"><?= e($qj['sichtbar'] ?? '') ?></div>
        </div>
        <div class="score-row" style="margin-bottom: 20px;">
            <div style="font-weight: bold; font-size: 16px;">[ <?= (int)$psi['accessibility_score'] ?> ] KI-LESBAR</div>
            <div style="font-size: 13px; margin-top: 4px; color: var(--fg);"><?= e($qj['ki'] ?? '') ?></div>
        </div>
        <div class="score-row" style="margin-bottom: 20px;">
            <div style="font-weight: bold; font-size: 16px;">[ <?= (int)$psi['best_practices_score'] ?> ] STRUKTUR</div>
            <div style="font-size: 13px; margin-top: 4px; color: var(--fg);"><?= e($qj['struktur'] ?? '') ?></div>
        </div>
    </div>

    <div class="text-block" style="margin-bottom: 24px; border: none; padding: 0;">
        der vollständige befund folgt<br>
        innerhalb von 24 stunden.
    </div>

    <div class="status-badge" style="font-size: 11px; color: var(--di); text-transform: uppercase; margin-bottom: 32px;">
        status: tiefenanalyse läuft
    </div>

    <div class="action-block" style="border-top: 1px solid var(--line); padding-top: 16px;">
        <a href="#" onclick="return deactivateNotifications(event)" style="color: var(--di); text-decoration: none; font-size: 11px;">
            // benachrichtigungen stoppen →
        </a>
        <div style="margin-top: 12px; font-size: 11px;">
            <a href="#ueber" style="color: var(--gr); text-decoration: none;">// über timo →</a>
        </div>
    </div>
</div>

    <?php endif; ?>

<?php elseif ($project['tunnel'] === 'bereit' && $psi): ?>

<div class="section">
    <div class="title">PORTAL — STATE 2 // QUICK REPORT</div>

    <div class="text-block" style="margin-bottom: 24px; border: none; padding: 0;">
        // erste auswertung fertig.
    </div>

    <?php
    $quick = json_decode($psi['report_quick_json'], true);
    if (is_array($quick)):
        $qj = $quick;
    ?>

    <div class="report-scores" style="margin-bottom: 32px;">
        <div class="score-row" style="margin-bottom: 20px;">
            <div style="font-weight: bold; font-size: 16px;">[ <?= (int)$psi['performance_score'] ?> ] TEMPO</div>
            <div style="font-size: 13px; margin-top: 4px; color: var(--fg);"><?= e($qj['tempo'] ?? '') ?></div>
        </div>
        <div class="score-row" style="margin-bottom: 20px;">
            <div style="font-weight: bold; font-size: 16px;">[ <?= (int)$psi['seo_score'] ?> ] SICHTBAR</div>
            <div style="font-size: 13px; margin-top: 4px; color: var(--fg);"><?= e($qj['sichtbar'] ?? '') ?></div>
        </div>
        <div class="score-row" style="margin-bottom: 20px;">
            <div style="font-weight: bold; font-size: 16px;">[ <?= (int)$psi['accessibility_score'] ?> ] KI-LESBAR</div>
            <div style="font-size: 13px; margin-top: 4px; color: var(--fg);"><?= e($qj['ki'] ?? '') ?></div>
        </div>
        <div class="score-row" style="margin-bottom: 20px;">
            <div style="font-weight: bold; font-size: 16px;">[ <?= (int)$psi['best_practices_score'] ?> ] STRUKTUR</div>
            <div style="font-size: 13px; margin-top: 4px; color: var(--fg);"><?= e($qj['struktur'] ?? '') ?></div>
        </div>
    </div>

    <div class="text-block" style="margin-bottom: 24px; border: none; padding: 0;">
        der vollständige befund folgt<br>
        innerhalb von 24 stunden.
    </div>

    <div class="status-badge" style="font-size: 11px; color: var(--di); text-transform: uppercase; margin-bottom: 32px;">
        status: tiefenanalyse läuft
    </div>

    <div class="action-block" style="border-top: 1px solid var(--line); padding-top: 16px;">
        <a href="#" onclick="return deactivateNotifications(event)" style="color: var(--di); text-decoration: none; font-size: 11px;">
            // benachrichtigungen stoppen →
        </a>
        <div style="margin-top: 12px; font-size: 11px;">
            <a href="#ueber" style="color: var(--gr); text-decoration: none;">// über timo →</a>
        </div>
    </div>
</div>

    <?php endif; ?>

<?php elseif ($project['tunnel'] === 'kontaktiert' && $psi): ?>

<div class="section">
<div class="title">tiefenanalyse</div>
<div class="text-block">
<?php
    $deep = json_decode($psi['report_quick_json'], true);
    if (is_array($deep) && isset($deep['deep_text'])) {
        echo nl2br(e($deep['deep_text']));
    } else {
        echo nl2br(e($psi['report_deep'] ?? ''));
    }
?>
</div>
</div>

<div class="section">
<button class="btn" onclick="alert('kontaktaufnahme folgt')">code-revision beauftragen →</button>
</div>

<div class="section">
<div class="action-block" style="border-top: 1px solid var(--line); padding-top: 16px;">
    <a href="#" onclick="return deactivateNotifications(event)" style="color: var(--di); text-decoration: none; font-size: 11px;">
        // benachrichtigungen stoppen →
    </a>
    <div style="margin-top: 12px; font-size: 11px;">
        <a href="#ueber" style="color: var(--gr); text-decoration: none;">// über timo →</a>
    </div>
</div>
</div>

<?php endif; ?>

</div>

<script>
// Telefonnummern-Formatierung: Konvertiere zu internationalem Format (+49...)
function formatPhoneNumber(value) {
    if (!value) return '';

    // Entferne Leerzeichen, Trennstriche, Schrägstriche
    let cleaned = value.replace(/[\s\-\/]/g, '');

    // Wenn mit '00' beginnt: ersetze durch '+'
    if (cleaned.startsWith('00')) {
        cleaned = '+' + cleaned.substring(2);
    }
    // Wenn mit einzelner '0' beginnt: ersetze durch '+49'
    else if (cleaned.startsWith('0') && !cleaned.startsWith('00')) {
        cleaned = '+49' + cleaned.substring(1);
    }
    // Wenn bereits mit '+' beginnt: unverändert
    else if (cleaned.startsWith('+')) {
        // Unverändert
    }

    return cleaned;
}

// Chamäleon-Feld: contact_secondary mutiert basierend auf contact_primary (Nur Label/Placeholder)
const contactPrimaryInput = document.getElementById('contact_primary');
const contactSecondaryLabel = document.getElementById('contact_secondary_label');
const contactSecondaryInput = document.getElementById('contact_secondary');

function updateSecondaryFieldLabel() {
    if (!contactPrimaryInput || !contactSecondaryLabel || !contactSecondaryInput) return;

    const value = contactPrimaryInput.value.trim();

    // Telefon erkannt: Startet mit Ziffer, '+' oder '/'
    if (/^\d/.test(value) || /^\+/.test(value) || /^\//.test(value)) {
        contactSecondaryLabel.textContent = 'E-Mail-Adresse (optional)';
        contactSecondaryInput.placeholder = 'name@unternehmen.de';
        contactSecondaryInput.autocomplete = 'email';
    }
    // E-Mail erkannt: Enthält '@'
    else if (/@/.test(value)) {
        contactSecondaryLabel.textContent = 'Mobilnummer (optional)';
        contactSecondaryInput.placeholder = '+49 123 456789';
        contactSecondaryInput.autocomplete = 'tel';
    }
    // Standard: Mobilnummer
    else {
        contactSecondaryLabel.textContent = 'Mobilnummer (optional)';
        contactSecondaryInput.placeholder = '+49 123 456789';
        contactSecondaryInput.autocomplete = 'tel';
    }
}

// Event-Listener auf mehrere Events: input, change, blur, keyup
if (contactPrimaryInput) {
    ['input', 'change', 'blur', 'keyup'].forEach(event => {
        contactPrimaryInput.addEventListener(event, updateSecondaryFieldLabel);
    });

    // Formatierung für contact_primary bei blur (wenn Telefon)
    contactPrimaryInput.addEventListener('blur', function() {
        const value = this.value.trim();
        if (value && (/^\d/.test(value) || /^\+/.test(value) || /^\//.test(value))) {
            this.value = formatPhoneNumber(value);
        }
    });
}

// Formatierung für contact_secondary bei blur (wenn Telefon)
if (contactSecondaryInput) {
    contactSecondaryInput.addEventListener('blur', function() {
        const value = this.value.trim();
        // Formatiere nur wenn aktuell als Telefon fungierend
        const primaryValue = contactPrimaryInput?.value.trim() || '';
        const primaryIsEmail = /@/.test(primaryValue);
        if (value && primaryIsEmail) {
            this.value = formatPhoneNumber(value);
        }
    });
}

// URL-Toleranz: Protokoll-Prefix hinzufügen bei blur
const urlField = document.getElementById('url_field');
if (urlField) {
    urlField.addEventListener('blur', function() {
        if (this.value && !this.value.match(/^https?:\/\//i)) {
            this.value = 'https://' + this.value;
        }
    });
}

async function submitForm(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'wird verarbeitet...';

    const url = document.getElementById('url_field').value.trim();
    const contact_primary = document.getElementById('contact_primary').value.trim();
    const customer_name = document.getElementById('customer_name').value.trim();
    const contact_secondary = document.getElementById('contact_secondary').value.trim();

    let email = '';
    let phone = '';

    // Intelligente Feldidentifikation: Startet mit Ziffer/+/ → Telefon, enthält @ → E-Mail
    const primaryStartsWithDigit = /^[\d+\/]/.test(contact_primary);
    const primaryHasAtSign = /@/.test(contact_primary);

    if (primaryHasAtSign) {
        email = contact_primary;
        if (contact_secondary) phone = contact_secondary;
    } else if (primaryStartsWithDigit) {
        phone = contact_primary;
        if (contact_secondary) email = contact_secondary;
    } else {
        email = contact_primary;
        if (contact_secondary) phone = contact_secondary;
    }

    const fd = new FormData(document.getElementById('auditForm'));
    fd.set('url', url);
    fd.set('email', email);
    fd.set('phone', phone);
    fd.set('name', customer_name);
    fd.delete('contact_primary');
    fd.delete('contact_secondary');
    fd.delete('customer_name');
    fd.delete('url_field');

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.ok && json.token) {
            window.location = '?token=' + encodeURIComponent(json.token);
        } else {
            alert('fehler: ' + (json.error || 'unbekannt'));
            btn.disabled = false;
            btn.textContent = '[ CHECK STARTEN → ]';
        }
    } catch (err) {
        alert('fehler: ' + err.message);
        btn.disabled = false;
        btn.textContent = '[ CHECK STARTEN → ]';
    }
}

async function deactivateNotifications(e) {
    e.preventDefault();
    if (!confirm('Benachrichtigungen deaktivieren und Funnel stoppen?')) return false;

    const fd = new FormData();
    fd.append('action', 'deactivate_notifications');

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: fd
        });
        if (response.ok) {
            window.location.reload();
        }
    } catch (error) {
        console.error('Fehler:', error);
    }
    return false;
}

// URL-Toleranz: Protokoll-Prefix hinzufügen bei blur
const urlInput = document.querySelector('input[name="url"]');
if (urlInput) {
    urlInput.addEventListener('blur', function() {
        if (this.value && !this.value.match(/^https?:\/\//i)) {
            this.value = 'https://' + this.value;
        }
    });
}
</script>

<!-- B8: über timo section -->
<div id="ueber" style="max-width: 600px; margin: 80px auto 60px; padding: 32px 20px; color: var(--mu); font-size: 12px; line-height: 1.6; text-align: center;">
    <div style="margin-bottom: 20px;">
        <strong style="color: var(--fg);">r400™</strong> / timo e. pohlhaus
    </div>
    <div style="margin-bottom: 20px;">
        website-revision & lighthouse audits.<br>
        drei fragen: ist sie schnell genug? für ki nutzbar? gut strukturiert?
    </div>
    <div>
        <a href="https://r400.de" style="color: var(--gr); text-decoration: none;">r400.de →</a>
    </div>
</div>

</body>
</html>
