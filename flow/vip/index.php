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

function http_get(string $url, int $timeout = 30): ?string {
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
    return $pdo;
}

function db_get_by_token(PDO $db, string $token): ?array {
    $stmt = $db->prepare('SELECT c.id as cid, c.customer_name, c.email, p.id as pid, p.target_url, p.tunnel FROM customers c LEFT JOIN projects p ON p.customer_id = c.id WHERE c.secret_token = ? LIMIT 1');
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

function db_create_customer(PDO $db, string $name, string $email, string $phone, string $token): int {
    $stmt = $db->prepare('INSERT INTO customers (customer_name, email, phone_mobile, secret_token, token_created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
    $stmt->execute([$name, $email, $phone, $token]);
    return (int)$db->lastInsertId();
}

function db_create_project(PDO $db, int $customer_id, string $customer_name, string $target_url): int {
    $stmt = $db->prepare('INSERT INTO projects (customer_id, customer_name, target_url, tunnel, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
    $stmt->execute([$customer_id, $customer_name, $target_url, 'anfrage']);
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
$token = $_GET['token'] ?? null;
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

        // Admin-Schutz: Keine Tracking-Updates bei adm=1
        if (!(isset($_GET['adm']) && $_GET['adm'] === '1')) {
            // Tracking-Logik hier (falls später implementiert)
        }
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
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!$url || !$email) {
        json_response(['ok' => false, 'error' => 'url und email erforderlich'], 400);
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        json_response(['ok' => false, 'error' => 'ungültige url'], 400);
    }

    // token generieren
    $token = bin2hex(random_bytes(16));

    try {
        // kunde anlegen
        $customer_id = db_create_customer($db, $name ?: 'unbekannt', $email, $phone ?: '', $token);
        // projekt anlegen
        $project_id = db_create_project($db, $customer_id, $name ?: $email, $url);

        // apis im hintergrund triggern
        if (!$cfg['pagespeed_key'] || !$cfg['anthropic_key']) {
            json_response(['ok' => true, 'token' => $token, 'message' => 'kunde angelegt, apis nicht konfiguriert']);
        }

        // pagespeed
        $scores = pagespeed_scores($url, $cfg['pagespeed_key']);
        if ($scores) {
            // ki-score hinzufügen
            $html = http_get($url, 15);
            $scores['ki'] = $html ? ki_score($html) : 0;

            // anthropic analyse
            $analysis = anthropic_analysis($url, $scores, $cfg);

            // in db speichern
            db_save_psi_results($db, $project_id, $scores, $analysis);

            // projekt-phase auf 'bewertet' setzen
            db_update_project_phase($db, $project_id, 'bewertet');

            // mail an admin
            $subject = 'r400 neue anfrage: ' . $email;
            $body = "neue anfrage über r400 kundenportal\n"
                . "name: " . ($name ?: 'nicht angegeben') . "\n"
                . "email: " . $email . "\n"
                . "telefon: " . ($phone ?: 'nicht angegeben') . "\n"
                . "url: " . $url . "\n"
                . "token: " . $token . "\n"
                . "scores: tempo " . $scores['tempo'] . ", sichtbar " . $scores['sichtbar'] . ", ki " . $scores['ki'] . ", struktur " . $scores['struktur'] . "\n"
                . "zeit: " . date('c') . "\n";
            $headers = 'From: ' . $cfg['mail_from'] . "\r\nContent-Type: text/plain; charset=utf-8\r\n";
            @mail($cfg['mail_to'], '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        }

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
</style>
</head>
<body>
<div class="wrap">

<?php if (!$customer): ?>

<div class="section">
    <div class="title">R400™ // timo e. pohlhaus / website-revision</div>

    <div class="text-block" style="margin-bottom: 24px; border: none; padding: 0;">
        du hast meine karte.<br>
        hier ist der check.
    </div>

    <form method="post" onsubmit="return submitForm(event)" id="auditForm">
        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="font-weight: bold; text-transform: uppercase;">WEBSITE *</label>
            <input type="url" name="url" class="form-input required" required placeholder="z.b. example.de" autocomplete="url" style="width: 100%; display: block;">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="font-weight: bold; text-transform: uppercase;">MAIL oder MOBILE *</label>
            <input type="text" name="contact" class="form-input required" required placeholder="damit ich dich erreichen kann" style="width: 100%; display: block;">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="text-transform: uppercase;">NAME</label>
            <input type="text" name="name" class="form-input optional" placeholder="optional" autocomplete="name" style="width: 100%; display: block;">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label" style="text-transform: uppercase;">TELEFON</label>
            <input type="tel" name="phone" class="form-input optional" placeholder="optional" autocomplete="tel" style="width: 100%; display: block;">
        </div>

        <button type="submit" class="btn" id="submitBtn" style="width: 100%; text-transform: uppercase;">[ CHECK STARTEN → ]</button>
    </form>

    <div class="text-block" style="margin-top: 16px; color: var(--di); font-size: 11px;">
        // signal kommt in kürze.
    </div>
</div>

<?php elseif ($project['tunnel'] === 'anfrage'): ?>

<div class="section">
    <div class="title">PORTAL — STATE 1 // PENDING</div>

    <div class="text-block" style="margin-bottom: 24px; border: none; padding: 0;">
        // deine anfrage ist eingegangen.<br>
        die analyse läuft.<br>
        du bekommst eine nachricht sobald<br>
        die ersten werte vorliegen.
    </div>

    <div class="status-badge" style="font-size: 11px; color: var(--di); text-transform: uppercase; margin-bottom: 32px;">
        status: wird bearbeitet
    </div>

    <div class="action-block" style="border-top: 1px solid var(--line); padding-top: 16px;">
        <a href="#" onclick="return deactivateNotifications(event)" style="color: var(--di); text-decoration: none; font-size: 11px;">
            // benachrichtigungen abschalten →
        </a>
    </div>
</div>

<?php elseif ($project['tunnel'] === 'abgeschaltet'): ?>

<div class="section">
    <div class="title">PORTAL — STATE 4 // BENACHRICHTIGUNGEN AUS</div>

    <div class="text-block" style="margin-bottom: 24px; border: none; padding: 0;">
        // benachrichtigungen deaktiviert.<br>
        dein befund liegt im portal bereit.<br>
        wenn du ihn besprechen möchtest,<br>
        ruf mich an.
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
            // benachrichtigungen abschalten →
        </a>
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
        // benachrichtigungen abschalten →
    </a>
</div>
</div>

<?php endif; ?>

</div>

<script>
async function submitForm(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'wird verarbeitet...';

    const contact = document.querySelector('input[name="contact"]').value.trim();
    let email = '';
    let phone = '';

    if (contact.includes('@')) {
        email = contact;
    } else {
        phone = contact;
    }

    const fd = new FormData(document.getElementById('auditForm'));
    fd.set('email', email);
    fd.set('phone', phone);
    fd.delete('contact');
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
</script>

</body>
</html>
