<?php
/**
 * worker_psi.php — R400™ Background Worker für Lighthouse-Audits & LLM-Analyse
 * Cronjob: Alle 'anfrage'-Status-Projekte scannen, analysieren und auf 'bewertet' setzen
 *
 * Nutzung: php /path/to/worker_psi.php
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);

$jobStart = microtime(true);

// Logging
$logFile = __DIR__ . '/worker_psi.log';
function log_worker(string $msg, string $level = 'INFO'): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$ts] [$level] $msg\n", FILE_APPEND);
}

log_worker('=== Worker Start ===');

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

function env(string $k, ?string $d = null): ?string {
    $v = getenv($k);
    return ($v === false || $v === '') ? $d : $v;
}

load_env(__DIR__ . '/../../.env');

$cfg = [
    'pagespeed_key'  => env('PAGESPEED_API_KEY'),
    'anthropic_key'  => env('ANTHROPIC_API_KEY'),
    'anthropic_model'=> env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    'db_path'        => dirname(__DIR__, 2) . '/data/rockets.db',
];

if (!$cfg['pagespeed_key'] || !$cfg['anthropic_key']) {
    log_worker('MISSING: API Keys not configured', 'ERROR');
    exit(1);
}

// ===== DATENBANK =====
function db_conn(string $path): PDO {
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

// ===== LOGGER =====
require_once dirname(__DIR__, 2) . '/Logger.php';

function db_get_pending_projects(PDO $db): array {
    $stmt = $db->prepare('SELECT id, target_url FROM projects WHERE tunnel = ? ORDER BY id ASC');
    $stmt->execute(['anfrage']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function db_save_psi_and_switch(PDO $db, int $pid, array $scores, string $json_quick): bool {
    try {
        $db->beginTransaction();

        // 1. Eintrag in psi_results schreiben
        $stmt = $db->prepare(
            'INSERT INTO psi_results (project_id, strategy, performance_score, seo_score, best_practices_score, accessibility_score, report_quick_json, fetch_timestamp)
             VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            $pid,
            'mobile',
            $scores['performance'] ?? 0,
            $scores['seo'] ?? 0,
            $scores['best_practices'] ?? 0,
            $scores['accessibility'] ?? 0,
            $json_quick
        ]);

        // 2. Projekt-Status auf 'bewertet' setzen
        $stmt = $db->prepare('UPDATE projects SET tunnel = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute(['bewertet', $pid]);

        $db->commit();
        return true;
    } catch (Throwable $ex) {
        $db->rollBack();
        log_worker("DB Error (PID $pid): " . $ex->getMessage(), 'ERROR');
        return false;
    }
}

// ===== API: GOOGLE PAGESPEED =====
function fetch_lighthouse_scores(string $url, string $api_key): ?array {
    $api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?'
         . 'url=' . urlencode($url)
         . '&strategy=mobile'
         . '&category=performance&category=seo&category=best-practices&category=accessibility'
         . '&key=' . urlencode($api_key);

    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_USERAGENT => 'R400-Worker/1.0'
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        log_worker("Lighthouse Fetch Error: $err", 'ERROR');
        return null;
    }

    $data = json_decode($res, true);
    if (!isset($data['lighthouseResult']['categories'])) {
        log_worker("Lighthouse: Invalid response structure", 'ERROR');
        return null;
    }

    $cats = $data['lighthouseResult']['categories'];
    return [
        'performance' => (int)round(($cats['performance']['score'] ?? 0) * 100),
        'seo' => (int)round(($cats['seo']['score'] ?? 0) * 100),
        'best_practices' => (int)round(($cats['best-practices']['score'] ?? 0) * 100),
        'accessibility' => (int)round(($cats['accessibility']['score'] ?? 0) * 100),
    ];
}

// ===== API: ANTHROPIC AUDIT =====
function generate_audit_analysis(array $scores, array $cfg): ?array {
    $prompt = "du bist der r400™ core-auditor. deine aufgabe ist es, die technischen rohdaten eines google lighthouse scans für eine website zu analysieren und eine unbarmherzig ehrliche, messerscharfe diagnose zu formulieren.\n"
        . "tonalität:\n"
        . "- absolut direkt, ungeschönt und pragmatisch. keine marketing-floskeln, kein kitsch, kein falsches sugarcoating. die wahrheit im code ist unumstößlich.\n"
        . "- schreibe komplett in kleinbuchstaben (hfg-konform), außer bei eigennamen oder festen begriffen wie llm, html, api, falls nötig.\n"
        . "- halte dich kurz: maximal 2-3 prägnante sätze pro kategorie.\n"
        . "struktur pro wert (tempo, sichtbar, ki-lesbar, struktur):\n"
        . "jede diagnose muss aus drei teilen bestehen: 1. ist-zustand (befund des schadens), 2. konsequenz (was bedeutet das für das geschäft/die zukunft des kunden?), 3. harte handlungsempfehlung (was muss zwingend getan werden?).\n"
        . "skalierung der schärfe nach score (0-100):\n"
        . "- score < 50 (kritisch): schlage brutal ein. nutze begriffe wie 'vollbremsung', 'blindflug', 'totalschaden'. mach klar, dass hier aktiv geld verbrannt wird.\n"
        . "- score 50-89 (mittelmäßig): kritisiere die handwerklichen fehler. das system läuft mit angezogener handbremse und verliert gegen die konkurrenz.\n"
        . "- score 90-100 (optimal): bestätige die valide, saubere arbeit kurz und sachlich. aktuell kein handlungsbedarf.\n"
        . "scores: tempo {$scores['performance']}, sichtbar {$scores['seo']}, ki-lesbar {$scores['accessibility']}, struktur {$scores['best_practices']}.\n"
        . "generiere das ergebnis als valides json mit den exakten keys: 'tempo', 'sichtbar', 'ki', 'struktur' und befreie den text von jeglichen einleitenden floskeln.";

    $payload = json_encode([
        'model' => $cfg['anthropic_model'],
        'max_tokens' => 1200,
        'messages' => [['role' => 'user', 'content' => $prompt]]
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'content-type: application/json',
            'x-api-key: ' . $cfg['anthropic_key'],
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_POSTFIELDS => $payload
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        log_worker("Anthropic Fetch Error: $err", 'ERROR');
        return null;
    }

    $data = json_decode($res, true);
    if (!isset($data['content'][0]['text'])) {
        log_worker("Anthropic: Invalid response structure", 'ERROR');
        return null;
    }

    $text = trim($data['content'][0]['text']);
    // Entferne Markdown-Fences
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```\s*$/', '', $text);
    $text = trim($text);

    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        log_worker("Anthropic JSON Parse Error: " . substr($text, 0, 100), 'ERROR');
        return null;
    }

    return $parsed;
}

// ===== MAIN LOOP =====
try {
    $db = db_conn($cfg['db_path']);
    Logger::init($db);
    $projects = db_get_pending_projects($db);

    if (empty($projects)) {
        log_worker("No pending projects (tunnel='anfrage')");
        $jobDuration = (microtime(true) - $jobStart) * 1000;
        Logger::logJobExecution('worker_psi', true, $jobDuration, ['projects_found' => 0]);
        exit(0);
    }

    log_worker("Found " . count($projects) . " pending projects");

    foreach ($projects as $proj) {
        $pid = (int)$proj['id'];
        $url = $proj['target_url'];

        log_worker("Processing PID $pid: $url");

        // 1. Lighthouse Scores
        $scores = fetch_lighthouse_scores($url, $cfg['pagespeed_key']);
        if (!$scores) {
            log_worker("Lighthouse failed for PID $pid", 'WARN');
            continue;
        }

        log_worker("Lighthouse OK for PID $pid: " . json_encode($scores));

        // 2. LLM Analyse
        $analysis = generate_audit_analysis($scores, $cfg);
        if (!$analysis) {
            log_worker("Anthropic failed for PID $pid", 'WARN');
            continue;
        }

        $json_quick = json_encode($analysis, JSON_UNESCAPED_UNICODE);
        log_worker("Analysis OK for PID $pid");

        // 3. DB Update + State Change
        if (db_save_psi_and_switch($db, $pid, $scores, $json_quick)) {
            log_worker("SUCCESS: PID $pid updated to 'bewertet'");
        } else {
            log_worker("DB Save failed for PID $pid", 'ERROR');
        }
    }

    log_worker("=== Worker Complete ===");
    $jobDuration = (microtime(true) - $jobStart) * 1000;
    Logger::logJobExecution('worker_psi', true, $jobDuration, ['projects_processed' => count($projects)]);
    exit(0);

} catch (Throwable $ex) {
    log_worker("FATAL: " . $ex->getMessage(), 'FATAL');
    $jobDuration = (microtime(true) - $jobStart) * 1000;
    Logger::logJobExecution('worker_psi', false, $jobDuration, ['error' => $ex->getMessage()]);
    exit(1);
}
