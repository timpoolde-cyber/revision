<?php
/**
 * worker_psi.php — R400™ PSI & Anthropic Analysis Background Worker
 * Lädt alle Projekte in 'anfrage'-Status, führt Audits durch und aktualisiert auf 'bewertet'
 *
 * Nutzung: php /path/to/worker_psi.php
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);

$jobStart = microtime(true);

// ===== LOGGING =====
$logFile = __DIR__ . '/worker_psi.log';
function log_worker(string $msg, string $level = 'INFO'): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$ts] [$level] $msg\n", FILE_APPEND);
}

log_worker('=== PSI Worker Start ===');

// ===== ENV & CONFIG =====
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

$cfg = [
    'pagespeed_key'   => getenv('PAGESPEED_API_KEY') ?: '',
    'anthropic_key'   => getenv('ANTHROPIC_API_KEY') ?: '',
    'anthropic_model' => getenv('ANTHROPIC_MODEL') ?: 'claude-sonnet-4-6',
    'db_path'         => dirname(__DIR__) . '/data/rockets.db',
];

// ===== HELPER FUNCTIONS =====
function clamp_score(mixed $v): int {
    return (int)round(max(0.0, min(1.0, (float)$v)) * 100);
}

function http_get(string $url, int $timeout = 30): ?string {
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        log_worker('[http_get] invalid url: ' . $url, 'WARN');
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'R400Worker/1.0'
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($result === false) {
        log_worker('[http_get] curl error: ' . $error, 'WARN');
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
    curl_close($ch);
    return is_string($result) ? $result : null;
}

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
    return min($score, 100);
}

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
        log_worker('[pagespeed] timeout', 'WARN');
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['lighthouseResult']['categories'])) {
        log_worker('[pagespeed] no categories in response', 'WARN');
        return null;
    }

    $cats = $data['lighthouseResult']['categories'];
    return [
        'tempo' => clamp_score($cats['performance']['score'] ?? 0),
        'sichtbar' => clamp_score($cats['seo']['score'] ?? 0),
        'struktur' => clamp_score($cats['best-practices']['score'] ?? 0),
    ];
}

function anthropic_analysis(string $url, array $scores, array $cfg): ?array {
    if (!$cfg['anthropic_key']) {
        return null;
    }

    $prompt = "Analysiere diese Website-Metriken im JSON-Format:\n"
        . "url: $url\n"
        . "performance: " . $scores['tempo'] . "\n"
        . "seo: " . $scores['sichtbar'] . "\n"
        . "structure: " . $scores['struktur'] . "\n\n"
        . "Gib einen JSON mit 'quick_json' (3-4 Bullet Points pro Metrik) und 'deep_text' (2 Absätze zur Architektur) zurück.";

    $body = json_encode([
        'model' => $cfg['anthropic_model'],
        'max_tokens' => 1024,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ]);

    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $cfg['anthropic_key'],
    ];

    $response = http_post('https://api.anthropic.com/v1/messages', $headers, $body, 60);
    if (!$response) {
        log_worker('[anthropic] no response', 'WARN');
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['content'][0]['text'])) {
        log_worker('[anthropic] invalid response structure', 'WARN');
        return null;
    }

    $text = trim($data['content'][0]['text']);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```\s*$/', '', $text);
    $text = trim($text);

    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        log_worker('[anthropic] parse failed', 'WARN');
        return null;
    }

    return $parsed;
}

// ===== DATABASE =====
function db_conn(string $path): PDO {
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function db_get_pending_audits(PDO $db): array {
    $stmt = $db->prepare('SELECT id, customer_name, target_url FROM projects WHERE tunnel = ? ORDER BY id ASC');
    $stmt->execute(['anfrage']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

require_once dirname(__DIR__) . '/Logger.php';

// ===== MAIN LOOP =====
try {
    $db = db_conn($cfg['db_path']);
    Logger::init($db);

    $projects = db_get_pending_audits($db);

    if (empty($projects)) {
        log_worker('No pending audits found');
        $jobDuration = (microtime(true) - $jobStart) * 1000;
        Logger::logJobExecution('worker_psi', true, $jobDuration, ['projects_processed' => 0]);
        exit(0);
    }

    log_worker('Found ' . count($projects) . ' projects in anfrage status');

    foreach ($projects as $proj) {
        $pid = (int)$proj['id'];
        $url = $proj['target_url'];

        log_worker("Processing PID $pid: $url");

        if (!$cfg['pagespeed_key'] || !$cfg['anthropic_key']) {
            log_worker("PID $pid: APIs not configured, skipping", 'WARN');
            continue;
        }

        // Pagespeed
        $scores = pagespeed_scores($url, $cfg['pagespeed_key']);
        if (!$scores) {
            log_worker("PID $pid: Pagespeed failed", 'WARN');
            continue;
        }

        // KI-Score
        $html = http_get($url, 15);
        $scores['ki'] = $html ? ki_score($html) : 0;

        log_worker("PID $pid: Scores: tempo=" . $scores['tempo'] . ", sichtbar=" . $scores['sichtbar'] . ", ki=" . $scores['ki']);

        // Anthropic Analysis
        $analysis = anthropic_analysis($url, $scores, $cfg);

        // Save to DB
        try {
            db_save_psi_results($db, $pid, $scores, $analysis);
            db_update_project_phase($db, $pid, 'bewertet');
            log_worker("PID $pid: SUCCESS - audit complete, tunnel→bewertet");
        } catch (Throwable $ex) {
            log_worker("PID $pid: DB save failed: " . $ex->getMessage(), 'ERROR');
        }
    }

    log_worker('=== PSI Worker Complete ===');
    $jobDuration = (microtime(true) - $jobStart) * 1000;
    Logger::logJobExecution('worker_psi', true, $jobDuration, ['projects_processed' => count($projects)]);
    exit(0);

} catch (Throwable $ex) {
    log_worker('FATAL: ' . $ex->getMessage(), 'FATAL');
    $jobDuration = (microtime(true) - $jobStart) * 1000;
    Logger::logJobExecution('worker_psi', false, $jobDuration, ['error' => $ex->getMessage()]);
    exit(1);
}
