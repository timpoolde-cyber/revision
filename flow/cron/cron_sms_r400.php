<?php
/**
 * cron_sms_r400.php — R400™ SMS Benachrichtigungs-Cronjob
 * Versendet personalisierte Benachrichtigungen für Quick Report und Deep Report Phasen
 *
 * Nutzung: php /path/to/cron_sms_r400.php
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);

$jobStart = microtime(true);

// ===== LOGGING =====
$logFile = __DIR__ . '/cron_sms.log';
function log_sms(string $msg, string $level = 'INFO'): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$ts] [$level] $msg\n", FILE_APPEND);
}

log_sms('=== SMS Cronjob Start ===');

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

// ===== DATENBANK =====
function db_conn(string $path): PDO {
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

// ===== LOGGER =====
require_once dirname(__DIR__, 2) . '/Logger.php';

function db_get_pending_sms(PDO $db): array {
    $stmt = $db->prepare('
        SELECT p.id, p.secret_token, p.tunnel, p.customer_id, c.phone_mobile, c.email
        FROM projects p
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE p.tunnel IN (?, ?)
        AND c.phone_mobile IS NOT NULL
        AND c.phone_mobile != \'\'
        ORDER BY p.id ASC
    ');
    $stmt->execute(['bewertet', 'bereit']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function db_update_tunnel_status(PDO $db, int $pid, string $tunnel): bool {
    try {
        $stmt = $db->prepare('UPDATE projects SET tunnel = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$tunnel, $pid]);
        return true;
    } catch (Throwable $ex) {
        log_sms("DB Error (PID $pid): " . $ex->getMessage(), 'ERROR');
        return false;
    }
}

// ===== SIPGATE CLIENT =====
require_once __DIR__ . '/../../core/lib/SipgateClient.php';

// ===== MAIN LOOP =====
try {
    $db = db_conn(dirname(__DIR__, 2) . '/data/rockets.db');
    Logger::init($db);
    $sipgate = new SipgateClient();

    $projects = db_get_pending_sms($db);

    if (empty($projects)) {
        log_sms("No pending SMS projects found");
        $jobDuration = (microtime(true) - $jobStart) * 1000;
        Logger::logJobExecution('cron_sms_r400', true, $jobDuration, ['projects_found' => 0]);
        exit(0);
    }

    log_sms("Found " . count($projects) . " projects with pending SMS");

    foreach ($projects as $proj) {
        $pid = (int)$proj['id'];
        $token = $proj['secret_token'] ?? '';
        $phone = $proj['phone_mobile'] ?? '';
        $tunnel = $proj['tunnel'];

        // Skip if deactivated
        if ($tunnel === 'abgeschaltet') {
            log_sms("SKIP: PID $pid (abgeschaltet)");
            continue;
        }

        // FALL A: Quick Report (tunnel = 'bewertet')
        if ($tunnel === 'bewertet') {
            $message = "// r400™ // website-revision\n"
                     . "erste ergebnisse liegen vor.\n"
                     . "dein quick report ist live:\n"
                     . "r400.de/vip/?token=$token";

            log_sms("Sending quick report SMS to PID $pid ($phone)");
            $result = $sipgate->sendSMS($phone, $message);

            if ($result['ok']) {
                if (db_update_tunnel_status($db, $pid, 'bereit')) {
                    log_sms("SUCCESS: Quick SMS sent for PID $pid, tunnel→bereit");
                } else {
                    log_sms("DB Update failed for PID $pid", 'WARN');
                }
            } else {
                log_sms("SMS Send Error (PID $pid): " . ($result['error'] ?? 'Unknown'), 'ERROR');
            }
        }

        // FALL B: Deep Report (tunnel = 'bereit' but SMS already sent, move to 'kontaktiert')
        if ($tunnel === 'bereit') {
            $message = "// r400™ // website-revision\n"
                     . "der vollständige architektonische befund ist fertig.\n"
                     . "abruf im portal:\n"
                     . "r400.de/vip/?token=$token";

            log_sms("Sending deep report SMS to PID $pid ($phone)");
            $result = $sipgate->sendSMS($phone, $message);

            if ($result['ok']) {
                if (db_update_tunnel_status($db, $pid, 'kontaktiert')) {
                    log_sms("SUCCESS: Deep SMS sent for PID $pid, tunnel→kontaktiert");
                } else {
                    log_sms("DB Update failed for PID $pid", 'WARN');
                }
            } else {
                log_sms("SMS Send Error (PID $pid): " . ($result['error'] ?? 'Unknown'), 'ERROR');
            }
        }
    }

    log_sms("=== SMS Cronjob Complete ===");
    $jobDuration = (microtime(true) - $jobStart) * 1000;
    Logger::logJobExecution('cron_sms_r400', true, $jobDuration, ['projects_processed' => count($projects)]);
    exit(0);

} catch (Throwable $ex) {
    log_sms("FATAL: " . $ex->getMessage(), 'FATAL');
    $jobDuration = (microtime(true) - $jobStart) * 1000;
    Logger::logJobExecution('cron_sms_r400', false, $jobDuration, ['error' => $ex->getMessage()]);
    exit(1);
}
