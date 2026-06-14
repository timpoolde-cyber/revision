<?php
// /Users/timpoolair/R100-CRM/api.php
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

require_once __DIR__ . '/session_handler.php';
require_once __DIR__ . '/Logger.php';

$requestStart = microtime(true);

// All requests require authentication
$method = $_SERVER['REQUEST_METHOD'];
$action = ($method === 'GET') ? ($_GET['action'] ?? '') : (json_decode(file_get_contents('php://input'), true)['action'] ?? '');

check_auth();

header('Content-Type: application/json');

$dbPath = __DIR__ . '/data/rockets.db';
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    Logger::init($db);

    try {
        $cols = $db->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('channel', $cols, true)) {
            $db->exec("ALTER TABLE projects ADD COLUMN channel TEXT DEFAULT 'lead'");
        }
    } catch (Throwable $e) {
    }

    foreach (['short_code' => "TEXT", 'notified_at' => "DATETIME"] as $col => $type) {
        try {
            $cols = $db->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array($col, $cols, true)) $db->exec("ALTER TABLE projects ADD COLUMN $col $type");
        } catch (Throwable $e) { /* still */ }
    }
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
    exit;
}

// Telefonnummern-Formatierung
function formatPhoneNumberAPI($phone) {
    $phone = trim($phone);
    if (empty($phone)) { return ''; }

    if (strpos($phone, '+49') === 0) {
        return $phone;
    }

    $cleaned = preg_replace('/[^0-9]/', '', $phone);

    if (strpos($cleaned, '0049') === 0) {
        $cleaned = substr($cleaned, 4);
    } elseif (strpos($cleaned, '49') === 0) {
        $cleaned = substr($cleaned, 2);
    } elseif (strpos($cleaned, '0') === 0) {
        $cleaned = substr($cleaned, 1);
    }

    if (strlen($cleaned) < 3) { return $phone; }

    $vorwahl = substr($cleaned, 0, 3);
    $rest = substr($cleaned, 3);

    return '+49 ' . $vorwahl . ' ' . $rest;
}

// Hilfsfunktion: Email versendet
function sendEmail($to, $subject, $body, $from = null, $attachment = null) {
    if (!$from) {
        $from = getenv('ADMIN_EMAIL') ?: 'r400@revision100.de';
    }

    $headers = "From: {$from}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if ($attachment) {
        $boundary = md5(time());
        $headers = "From: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $body . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: application/pdf; name=\"status-paper.pdf\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"status-paper.pdf\"\r\n\r\n";
        $message .= $attachment . "\r\n";
        $message .= "--{$boundary}--";

        return mail($to, $subject, $message, $headers);
    }

    return mail($to, $subject, $body, $headers);
}

function fetchPageSpeedInsights($targetUrl, $apiKey, $pdo) {
    $baseApiUrl = 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed';
    $categories = ['performance', 'accessibility', 'best-practices', 'seo'];
    $categoryParts = array();
    foreach ($categories as $cat) {
        $categoryParts[] = "category=" . urlencode($cat);
    }
    $categoryParams = implode('&', $categoryParts);

    $strategies = ['mobile', 'desktop'];
    $results = [];

    foreach ($strategies as $strategy) {
        $apiUrl = "{$baseApiUrl}?url=" . urlencode($targetUrl) . "&key={$apiKey}&{$categoryParams}&strategy={$strategy}";

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 60,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($apiUrl, false, $context);

            if ($response === false) {
                $results[$strategy] = ['error' => 'Unable to reach API'];
                continue;
            }

            $data = json_decode($response, true);

            if (isset($data['error'])) {
                $results[$strategy] = ['error' => $data['error']['message'] ?? 'API Error', 'raw' => $response];
                continue;
            }

            $score = $data['lighthouseResult']['categories']['performance']['score'] ?? null;
            $accessibilityScore = $data['lighthouseResult']['categories']['accessibility']['score'] ?? null;
            $bestPracticesScore = $data['lighthouseResult']['categories']['best-practices']['score'] ?? null;
            $seoScore = $data['lighthouseResult']['categories']['seo']['score'] ?? null;

            if ($score === null) {
                $results[$strategy] = ['error' => 'Performance score not found in API response', 'raw' => $response];
                continue;
            }

            $performanceScore = (int)round($score * 100);
            $accessScore = $accessibilityScore ? (int)round($accessibilityScore * 100) : null;
            $practScore = $bestPracticesScore ? (int)round($bestPracticesScore * 100) : null;
            $sScore = $seoScore ? (int)round($seoScore * 100) : null;

            $results[$strategy] = [
                'success' => true,
                'score' => $performanceScore,
                'accessibility' => $accessScore,
                'best_practices' => $practScore,
                'seo' => $sScore,
                'raw' => $response
            ];

        } catch (Exception $e) {
            error_log("PSI fetch error: " . $e->getMessage());
            $results[$strategy] = ['error' => 'API error'];
        }
    }

    return $results;
}

// Berechne aktuelle Phase und Farbe basierend auf Zeitstempel
function calculatePhaseStatus($projectId, $db, $timeZone = 'Europe/Berlin') {
    try {
        $stmt = $db->prepare(
            "SELECT phase_1_initiated_at, phase_2_evaluated_at, phase_3_contacted_at,
                    phase_4_engaged_at, phase_5_implemented_at, phase_6_closed_at
             FROM projects WHERE id = ?"
        );
        $stmt->execute([$projectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return ['phase' => null, 'color' => 'gray', 'secondsRemaining' => null];
        }

        // Bestimme höchste Phase mit Zeitstempel
        $phases = [
            1 => $result['phase_1_initiated_at'],
            2 => $result['phase_2_evaluated_at'],
            3 => $result['phase_3_contacted_at'],
            4 => $result['phase_4_engaged_at'],
            5 => $result['phase_5_implemented_at'],
            6 => $result['phase_6_closed_at']
        ];

        $currentPhase = null;
        $phaseTimestamp = null;
        foreach (array_reverse($phases, true) as $num => $ts) {
            if ($ts && !empty($ts)) {
                $currentPhase = $num;
                $phaseTimestamp = $ts;
                break;
            }
        }

        if (!$currentPhase) {
            return ['phase' => null, 'color' => 'gray', 'secondsRemaining' => null];
        }

        // Berechne Zeit in Berlin-Zeitzone
        $now = new DateTimeImmutable('now', new DateTimeZone($timeZone));
        try {
            $phaseStart = new DateTimeImmutable($phaseTimestamp, new DateTimeZone('UTC'))
                ->setTimezone(new DateTimeZone($timeZone));
        } catch (Exception $e) {
            // Fallback für ungültige Timestamps
            return ['phase' => $currentPhase, 'color' => 'gray', 'secondsRemaining' => null];
        }

        $secondsElapsed = $now->getTimestamp() - $phaseStart->getTimestamp();
        $color = 'gray';
        $secondsRemaining = null;

        if ($currentPhase === 1) {
            // Stufe 1: 0-3h grün, 3-5h orange, >5h rot
            if ($secondsElapsed <= 3 * 3600) {
                $color = 'green';
                $secondsRemaining = (3 * 3600) - $secondsElapsed;
            } elseif ($secondsElapsed <= 5 * 3600) {
                $color = 'orange';
                $secondsRemaining = (5 * 3600) - $secondsElapsed;
            } else {
                $color = 'red';
                $secondsRemaining = null;
            }
        }
        elseif ($currentPhase === 2) {
            // Stufe 2: 0-24h grün, 24-36h orange, 36-48h rot, >48h rot
            if ($secondsElapsed <= 24 * 3600) {
                $color = 'green';
                $secondsRemaining = (24 * 3600) - $secondsElapsed;
            } elseif ($secondsElapsed <= 36 * 3600) {
                $color = 'orange';
                $secondsRemaining = (36 * 3600) - $secondsElapsed;
            } else {
                $color = 'red';
                $secondsRemaining = null;
            }
        }
        elseif ($currentPhase === 3 || $currentPhase === 4 || $currentPhase === 5) {
            // Stufe 3, 4, 5: ereignisbasiert, sofort grün, keine Alterung
            $color = 'green';
            $secondsRemaining = null;
        }
        elseif ($currentPhase === 6) {
            // Stufe 6: 0-14d grün mit Countdown, >14d grau
            $limitSeconds = 14 * 86400;
            if ($secondsElapsed <= $limitSeconds) {
                $color = 'green';
                $secondsRemaining = $limitSeconds - $secondsElapsed;
            } else {
                $color = 'gray';
                $secondsRemaining = null;
            }
        }

        return [
            'phase' => $currentPhase,
            'color' => $color,
            'secondsRemaining' => $secondsRemaining
        ];
    } catch (Exception $e) {
        error_log("calculatePhaseStatus error: " . $e->getMessage());
        return ['phase' => null, 'color' => 'gray', 'secondsRemaining' => null];
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'get_leads';

    if ($action === 'get_psi_scores') {
        $projectId = $_GET['project_id'] ?? null;
        if ($projectId) {
            try {
                $stmt = $db->prepare("SELECT performance_score, accessibility_score, best_practices_score, seo_score, strategy, fetch_timestamp, error_message FROM psi_results WHERE project_id = ? ORDER BY fetch_timestamp DESC");
                $stmt->execute([$projectId]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $results]);
            } catch (Exception $e) {
                error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
        }
        exit;
    }


    if ($action === 'debug_project') {
        $projectId = $_GET['id'] ?? null;
        if ($projectId) {
            $stmt = $db->prepare("SELECT p.id, p.customer_id, p.customer_name, c.id as cid, c.email, c.customer_name as c_name FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
            $stmt->execute([$projectId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'debug' => $result]);
        }
        exit;
    }

    if ($action === 'db_status') {
        $tables = [];
        try {
            $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}

        $projectCount = 0;
        try {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM projects");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $projectCount = $result['cnt'] ?? 0;
        } catch (Exception $e) {}

        $psiCount = 0;
        try {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM psi_results");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $psiCount = $result['cnt'] ?? 0;
        } catch (Exception $e) {}

        $psiSample = null;
        try {
            $stmt = $db->query("SELECT * FROM psi_results ORDER BY fetch_timestamp DESC LIMIT 1");
            $psiSample = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'tables' => $tables,
            'project_count' => $projectCount,
            'psi_measurement_count' => $psiCount,
            'latest_psi_sample' => $psiSample
        ]);
        exit;
    }



    if ($action === 'get_customers') {
        $stmt = $db->query("SELECT id, customer_name, email, phone_mobile, address, city, postal_code, latitude, longitude FROM customers ORDER BY customer_name ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'get_leads') {
        $query = "
            SELECT p.id, p.customer_name, p.target_url, p.tunnel, p.alert_level, p.last_score, p.updated_at,
                   p.channel,
                   p.phase_1_initiated_at, p.phase_2_evaluated_at, p.phase_3_contacted_at,
                   p.phase_4_engaged_at, p.phase_5_implemented_at, p.phase_6_closed_at,
                   c.email, c.phone_mobile,
                   (SELECT created_at FROM interactions i WHERE i.project_id = p.id ORDER BY created_at DESC LIMIT 1) as last_interaction_date,
                   (SELECT content FROM interactions i WHERE i.project_id = p.id ORDER BY created_at DESC LIMIT 1) as last_interaction_notes,
                   (SELECT performance_score FROM psi_results WHERE project_id = p.id AND strategy = 'mobile' ORDER BY fetch_timestamp DESC LIMIT 1) as psi_mobile_score,
                   IFNULL(p.token_created_at, '') as token_created_at,
                   IFNULL(p.token_used_at, '') as token_used_at,
                   (SELECT name FROM project_contacts WHERE project_id = p.id AND is_default = 1 LIMIT 1) as default_contact_name,
                   (SELECT MAX(CASE WHEN report_quick_json IS NOT NULL AND report_quick_json <> '' THEN 1 ELSE 0 END) FROM psi_results WHERE project_id = p.id) AS has_quick,
                   (SELECT MAX(CASE WHEN report_deep IS NOT NULL AND report_deep <> '' THEN 1 ELSE 0 END) FROM psi_results WHERE project_id = p.id) AS has_deep
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            ORDER BY p.updated_at DESC
        ";
        try {
            $stmt = $db->query($query);
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Berechne Phase-Status für jeden Lead
            foreach ($leads as &$lead) {
                $phaseStatus = calculatePhaseStatus($lead['id'], $db);
                $lead['current_phase'] = $phaseStatus['phase'];
                $lead['phase_color'] = $phaseStatus['color'];
                $lead['phase_timeout_seconds'] = $phaseStatus['secondsRemaining'];
            }

            echo json_encode(['success' => true, 'data' => $leads]);
        } catch (Exception $e) {
            // Fallback to simpler query if psi_results table doesn't exist yet
            $fallbackQuery = "
                SELECT p.id, p.customer_name, p.target_url, p.tunnel, p.alert_level, p.last_score, p.updated_at,
                       p.channel,
                       p.phase_1_initiated_at, p.phase_2_evaluated_at, p.phase_3_contacted_at,
                       p.phase_4_engaged_at, p.phase_5_implemented_at, p.phase_6_closed_at,
                       c.email, c.phone_mobile,
                       (SELECT created_at FROM interactions i WHERE i.project_id = p.id ORDER BY created_at DESC LIMIT 1) as last_interaction_date,
                       (SELECT content FROM interactions i WHERE i.project_id = p.id ORDER BY created_at DESC LIMIT 1) as last_interaction_notes,
                       NULL as psi_mobile_score,
                       IFNULL(p.token_created_at, '') as token_created_at,
                       IFNULL(p.token_used_at, '') as token_used_at,
                       (SELECT name FROM project_contacts WHERE project_id = p.id AND is_default = 1 LIMIT 1) as default_contact_name,
                       0 AS has_quick,
                       0 AS has_deep
                FROM projects p
                LEFT JOIN customers c ON p.customer_id = c.id
                ORDER BY p.updated_at DESC
            ";
            $stmt = $db->query($fallbackQuery);
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Berechne Phase-Status für jeden Lead (Fallback)
            foreach ($leads as &$lead) {
                $phaseStatus = calculatePhaseStatus($lead['id'], $db);
                $lead['current_phase'] = $phaseStatus['phase'];
                $lead['phase_color'] = $phaseStatus['color'];
                $lead['phase_timeout_seconds'] = $phaseStatus['secondsRemaining'];
            }

            echo json_encode(['success' => true, 'data' => $leads]);
        }
        exit;
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'save_customer') {
        try {
            $id = $input['id'] ?? null;
            $name = $input['customer_name'] ?? '';
            $email = $input['email'] ?? '';
            $phone = $input['phone'] ?? '';
            $address = $input['address'] ?? '';
            $city = $input['city'] ?? '';
            $postal = $input['postal_code'] ?? '';
            $lat = $input['latitude'] ?? null;
            $lon = $input['longitude'] ?? null;

            // Formatiere Telefonnummer vor dem Speichern
            $phone = formatPhoneNumberAPI($phone);

            if ($id) {
                $stmt = $db->prepare("UPDATE customers SET customer_name=?, email=?, phone_mobile=?, address=?, city=?, postal_code=?, latitude=?, longitude=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $address, $city, $postal, $lat, $lon, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO customers (customer_name, email, phone_mobile, address, city, postal_code, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $address, $city, $postal, $lat, $lon]);
                $id = $db->lastInsertId();
            }

            $requestDuration = (microtime(true) - $requestStart) * 1000;
            Logger::logRequest('save_customer', true, $requestDuration, ['customer_id' => $id]);
            echo json_encode(['success' => true, 'data' => ['id' => $id]]);
        } catch (Exception $e) {
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            Logger::logRequest('save_customer', false, $requestDuration, ['error' => $e->getMessage()]);
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'save') {
        try {
            $id = $input['id'] ?? null;
            $cid = $input['customer_id'] ?? null;
            $cname = $input['customer_name'] ?? '';
            $url = $input['target_url'] ?? '';
            $tunnel = $input['tunnel'] ?? 'anfrage';
            $channel = $input['channel'] ?? 'maps';
            $alert_level = $input['alert_level'] ?? 'normal';
            $notiz = $input['notiz'] ?? '';

            if (empty($url)) {
                $tunnel = 'url_fehlt';
            } elseif ($tunnel === 'url_fehlt') {
                $tunnel = 'anfrage';
            }

            $db->beginTransaction();

            if ($id) {
                $stmt = $db->prepare("UPDATE projects SET customer_id=?, customer_name=?, target_url=?, tunnel=?, channel=?, alert_level=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$cid, $cname, $url, $tunnel, $channel, $alert_level, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, channel, alert_level, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$cid, $cname, $url, $tunnel, $channel, $alert_level]);
                $id = $db->lastInsertId();
            }

            if (!empty($notiz)) {
                $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, 'Notiz', ?)");
                $stmt->execute([$id, $notiz]);
            }

            $db->commit();

            $requestDuration = (microtime(true) - $requestStart) * 1000;
            Logger::logRequest('save', true, $requestDuration, ['project_id' => $id, 'tunnel' => $tunnel]);
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            Logger::logRequest('save', false, $requestDuration, ['error' => $e->getMessage()]);
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'save_maps_lead') {
        try {
            $db->beginTransaction();

            $customer_id = $input['customer_id'] ?? null;
            $cname = $input['customer_name'] ?? '';
            $email = $input['email'] ?? '';
            $phone = $input['phone'] ?? '';
            $address = $input['address'] ?? '';
            $city = $input['city'] ?? '';
            $postal = $input['postal_code'] ?? '';
            $lat = $input['latitude'] ?? null;
            $lon = $input['longitude'] ?? null;
            $target_url = $input['target_url'] ?? '';
            $channel = $input['channel'] ?? 'maps';
            $notiz = $input['notiz'] ?? '';

            $phone = formatPhoneNumberAPI($phone);

            if (!$customer_id) {
                $stmt = $db->prepare("SELECT id FROM customers WHERE customer_name = ? AND postal_code = ? LIMIT 1");
                $stmt->execute([$cname, $postal]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $customer_id = $existing['id'];
                } else {
                    $stmt = $db->prepare("INSERT INTO customers (customer_name, email, phone_mobile, address, city, postal_code, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$cname, $email, $phone, $address, $city, $postal, $lat, $lon]);
                    $customer_id = $db->lastInsertId();
                }
            }

            $secret_token = bin2hex(random_bytes(16));
            $tunnel = empty($target_url) ? 'url_fehlt' : 'anfrage';

            $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, channel, tunnel, secret_token, token_created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute([$customer_id, $cname, $target_url, $channel, $tunnel, $secret_token]);
            $project_id = $db->lastInsertId();

            if (!empty($notiz)) {
                $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, 'Notiz', ?)");
                $stmt->execute([$project_id, $notiz]);
            }

            $db->commit();
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            Logger::logRequest('save_maps_lead', true, $requestDuration, ['project_id' => $project_id]);
            echo json_encode(['success' => true, 'data' => ['id' => $project_id]]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            Logger::logRequest('save_maps_lead', false, $requestDuration, ['error' => $e->getMessage()]);
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'send_token_email') {
        try {
            $projectId = $input['project_id'] ?? null;
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT c.id, c.customer_name, c.email, p.target_url FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden', 'debug' => $projectId]);
                exit;
            }

            if (!$project['email']) {
                echo json_encode(['success' => false, 'error' => 'Kunden-Email fehlt', 'debug' => $project]);
                exit;
            }

            // Neuen Token am Projekt generieren
            $newToken = generate_secret_token();
            $stmt = $db->prepare("UPDATE projects SET secret_token = ?, token_created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newToken, $projectId]);

            // Email versenden
            $tokenLink = rtrim(dirname($_SERVER['HTTP_HOST'] ? 'http://' . $_SERVER['HTTP_HOST'] : ''), '/') . '/update.php?token=' . $newToken;
            if (!empty($_SERVER['HTTP_HOST'])) {
                $tokenLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/update.php?token=' . $newToken;
            }

            $subject = "REVISION100(TM) – Ihr Zugangslink";
            $body = "Liebe/r " . htmlspecialchars($project['customer_name']) . ",\n\n";
            $body .= "hier ist Ihr Zugangslink zur Aktualisierung:\n";
            $body .= $tokenLink . "\n\n";
            $body .= "Einfach öffnen, Daten überprüfen und speichern. Fertig!\n\n";
            $body .= "Viele Grüße,\n";
            $body .= getenv('ADMIN_EMAIL') ?: 'REVISION100 Team';

            $mailSent = sendEmail($project['email'], $subject, $body);

            if ($mailSent === true) {
                echo json_encode(['success' => true, 'token' => $newToken, 'email_sent' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Email konnte nicht versendet werden (mail() returned false)', 'token' => $newToken, 'mail_result' => $mailSent, 'to' => $project['email']]);
            }
        } catch (Exception $e) {
            error_log('Exception: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'send_pdf_email') {
        try {
            $projectId = $input['project_id'] ?? null;
            $pdfBase64 = $input['pdf_base64'] ?? null;

            if (!$projectId || !$pdfBase64) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID und PDF erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT c.customer_name, c.email, p.target_url FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }

            if (!$project['email']) {
                echo json_encode(['success' => false, 'error' => 'Kunden-Email fehlt']);
                exit;
            }

            // URL-Kurzform extrahieren
            $urlParts = parse_url($project['target_url']);
            $urlShort = $urlParts['host'] ?? $project['target_url'];

            $subject = "REVISION100(TM) Status Paper (" . $urlShort . ")";
            $body = "Liebe/r " . htmlspecialchars($project['customer_name']) . ",\n\n";
            $body .= "anbei Ihr aktuelles Status Paper.\n\n";
            $body .= "Viele Grüße,\n";
            $body .= getenv('ADMIN_EMAIL') ?: 'REVISION100 Team';

            $mailSent = sendEmail($project['email'], $subject, $body, null, $pdfBase64);

            if ($mailSent) {
                echo json_encode(['success' => true, 'email_sent' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Email konnte nicht versendet werden']);
            }
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'generate_token') {
        try {
            $projectId = $input['project_id'] ?? null;
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT id FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }

            $newToken = generate_secret_token();
            $stmt = $db->prepare("UPDATE projects SET secret_token = ?, token_created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newToken, $projectId]);

            echo json_encode(['success' => true, 'token' => $newToken]);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'regenerate_token') {
        try {
            $projectId = $input['project_id'] ?? null;
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT id FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }

            $newToken = generate_secret_token();
            $stmt = $db->prepare("UPDATE projects SET secret_token = ?, token_created_at = CURRENT_TIMESTAMP, token_used_at = NULL WHERE id = ?");
            $stmt->execute([$newToken, $projectId]);

            echo json_encode(['success' => true, 'token' => $newToken]);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'run_psi_now') {
        try {
            $projectId = $input['project_id'] ?? null;
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT p.id, p.target_url FROM projects p WHERE p.id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }

            $apiKey = getenv('GOOGLE_PSI_API_KEY');
            if (!$apiKey) {
                echo json_encode(['success' => false, 'error' => 'Google PSI API Key nicht konfiguriert']);
                exit;
            }

            $psiResults = fetchPageSpeedInsights($project['target_url'], $apiKey, $db);

            $resultData = [];
            foreach ($psiResults as $strategy => $result) {
                if (isset($result['error'])) {
                    $rawResponse = $result['raw'] ?? null;
                    $stmt = $db->prepare("INSERT INTO psi_results (project_id, strategy, error_message, raw_response, fetch_timestamp) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
                    $stmt->execute([$projectId, $strategy, $result['error'], $rawResponse]);
                    $resultData[$strategy] = ['error' => $result['error']];

                    // Telegram alert
                    $telegramToken = getenv('TELEGRAM_TOKEN');
                    if ($telegramToken) {
                        $telegramChatId = getenv('TELEGRAM_CHAT_ID');
                        if ($telegramChatId) {
                            $msg = "⚠️ PSI Fehler für Projekt {$projectId}: {$strategy} - {$result['error']}";
                            $tgUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
                            $opts = ['http' => ['method' => 'POST', 'content' => http_build_query(['chat_id' => $telegramChatId, 'text' => $msg]), 'timeout' => 2]];
                            @file_get_contents($tgUrl, false, stream_context_create($opts));
                        }
                    }
                } else {
                    $stmt = $db->prepare("INSERT INTO psi_results (project_id, strategy, performance_score, accessibility_score, best_practices_score, seo_score, raw_response, fetch_timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                    $stmt->execute([$projectId, $strategy, $result['score'], $result['accessibility'], $result['best_practices'], $result['seo'], $result['raw']]);
                    $resultData[$strategy] = ['success' => true, 'score' => $result['score']];
                }
            }

            $requestDuration = (microtime(true) - $requestStart) * 1000;
            Logger::logRequest('run_psi_now', true, $requestDuration, ['project_id' => $projectId, 'results_count' => count($resultData)]);
            echo json_encode(['success' => true, 'results' => $resultData]);
        } catch (Exception $e) {
            $requestDuration = (microtime(true) - $requestStart) * 1000;
            Logger::logRequest('run_psi_now', false, $requestDuration, ['error' => $e->getMessage()]);
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'send_custom_email') {
        try {
            $projectId = $input['project_id'] ?? null;
            $subject = $input['subject'] ?? 'REVISION100™ Nachricht';
            $body = $input['body'] ?? '';

            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT c.customer_name, c.email FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }

            if (!$project['email']) {
                echo json_encode(['success' => false, 'error' => 'Kunden-Email fehlt']);
                exit;
            }

            $mailSent = sendEmail($project['email'], $subject, $body);

            if ($mailSent === true) {
                echo json_encode(['success' => true, 'email_sent' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Email konnte nicht versendet werden']);
            }
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'save_email_template') {
        try {
            $projectId = $input['project_id'] ?? null;
            $name = $input['name'] ?? null;
            $content = $input['content'] ?? null;

            if (!$projectId || !$name || !$content) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID, Name und Inhalt erforderlich']);
                exit;
            }

            $stmt = $db->prepare("INSERT OR REPLACE INTO email_templates (project_id, name, content) VALUES (?, ?, ?)");
            $stmt->execute([$projectId, $name, $content]);

            echo json_encode(['success' => true, 'message' => 'Template gespeichert']);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'list_email_templates') {
        try {
            $projectId = $input['project_id'] ?? null;

            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT id, name, created_at FROM email_templates WHERE project_id = ? ORDER BY name ASC");
            $stmt->execute([$projectId]);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $templates]);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'load_email_template') {
        try {
            $templateId = $input['template_id'] ?? null;

            if (!$templateId) {
                echo json_encode(['success' => false, 'error' => 'Template ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT content FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                echo json_encode(['success' => false, 'error' => 'Template nicht gefunden']);
                exit;
            }

            echo json_encode(['success' => true, 'content' => $template['content']]);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'delete_email_template') {
        try {
            $templateId = $input['template_id'] ?? null;

            if (!$templateId) {
                echo json_encode(['success' => false, 'error' => 'Template ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);

            echo json_encode(['success' => true, 'message' => 'Template gelöscht']);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'save_psi_scores') {
        try {
            $projectId = $input['project_id'] ?? null;
            $performance = isset($input['performance']) ? (int)$input['performance'] : null;
            $accessibility = isset($input['accessibility']) ? (int)$input['accessibility'] : null;
            $best_practices = isset($input['best_practices']) ? (int)$input['best_practices'] : null;
            $seo = isset($input['seo']) ? (int)$input['seo'] : null;

            if (!$projectId || $performance === null) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID und Performance erforderlich']);
                exit;
            }

            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO psi_results (project_id, strategy, performance_score, accessibility_score, best_practices_score, seo_score, fetch_timestamp) VALUES (?, 'mobile', ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$projectId, $performance, $accessibility, $best_practices, $seo]);

            $stmt = $db->prepare("UPDATE projects SET last_score = ? WHERE id = ?");
            $stmt->execute([$performance, $projectId]);
            $db->commit();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'save_project_data') {
        try {
            $projectId = $input['project_id'] ?? null;
            $customerName = $input['customer_name'] ?? null;
            $targetUrl = $input['target_url'] ?? null;
            $address = $input['address'] ?? null;
            $city = $input['city'] ?? null;
            $postalCode = $input['postal_code'] ?? null;

            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("UPDATE projects SET customer_name = ?, target_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$customerName, $targetUrl, $projectId]);

            if ($customerName || $address || $city || $postalCode) {
                $stmt = $db->prepare("SELECT customer_id FROM projects WHERE id = ?");
                $stmt->execute([$projectId]);
                $proj = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($proj && $proj['customer_id']) {
                    $stmt2 = $db->prepare("UPDATE customers SET customer_name = ?, address = ?, city = ?, postal_code = ? WHERE id = ?");
                    $stmt2->execute([$customerName, $address, $city, $postalCode, $proj['customer_id']]);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Projektdaten gespeichert']);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'add_project_contact') {
        try {
            $projectId = $input['project_id'] ?? null;
            $name = $input['name'] ?? null;
            $email = $input['email'] ?? null;
            $phoneMobile = $input['phone_mobile'] ?? null;

            if (!$projectId || !$name) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID und Name erforderlich']);
                exit;
            }

            // Formatiere Telefonnummer vor dem Speichern
            $phoneMobile = formatPhoneNumberAPI($phoneMobile);

            $stmt = $db->prepare("INSERT INTO project_contacts (project_id, name, email, phone_mobile, is_default) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$projectId, $name, $email, $phoneMobile]);
            $contactId = $db->lastInsertId();

            echo json_encode(['success' => true, 'contact_id' => $contactId, 'message' => 'Kontakt hinzugefügt']);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'update_project_contact') {
        try {
            $contactId = $input['contact_id'] ?? null;
            $name = $input['name'] ?? null;
            $email = $input['email'] ?? null;
            $phoneMobile = $input['phone_mobile'] ?? null;

            if (!$contactId || !$name) {
                echo json_encode(['success' => false, 'error' => 'Kontakt ID und Name erforderlich']);
                exit;
            }

            // Formatiere Telefonnummer vor dem Speichern
            $phoneMobile = formatPhoneNumberAPI($phoneMobile);

            $stmt = $db->prepare("UPDATE project_contacts SET name = ?, email = ?, phone_mobile = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phoneMobile, $contactId]);

            echo json_encode(['success' => true, 'message' => 'Kontakt aktualisiert']);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'delete_project_contact') {
        try {
            $contactId = $input['contact_id'] ?? null;

            if (!$contactId) {
                echo json_encode(['success' => false, 'error' => 'Kontakt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM project_contacts WHERE id = ?");
            $stmt->execute([$contactId]);

            echo json_encode(['success' => true, 'message' => 'Kontakt gelöscht']);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'set_default_contact') {
        try {
            $projectId = $input['project_id'] ?? null;
            $contactId = $input['contact_id'] ?? null;

            if (!$projectId || !$contactId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID und Kontakt ID erforderlich']);
                exit;
            }

            $db->prepare("UPDATE project_contacts SET is_default = 0 WHERE project_id = ?")->execute([$projectId]);
            $stmt = $db->prepare("UPDATE project_contacts SET is_default = 1 WHERE id = ? AND project_id = ?");
            $stmt->execute([$contactId, $projectId]);

            echo json_encode(['success' => true, 'message' => 'Default-Kontakt gesetzt']);
        } catch (Exception $e) {
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }

    if ($action === 'save_project_contacts') {
        try {
            $projectId = $input['project_id'] ?? null;
            $contacts = $input['contacts'] ?? [];
            $deletedIds = $input['deleted_ids'] ?? [];
            $defaultContactId = $input['default_contact_id'] ?? null;

            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $db->beginTransaction();

            // 1. Lösche markierte Kontakte
            if (!empty($deletedIds)) {
                $placeholders = implode(',', array_fill(0, count($deletedIds), '?'));
                $db->prepare("DELETE FROM project_contacts WHERE id IN ({$placeholders}) AND project_id = ?")->execute(array_merge($deletedIds, [$projectId]));
            }

            // 2. Update bestehende Kontakte und insert neue
            $newContactIds = [];
            foreach ($contacts as $id => $contact) {
                $name = trim($contact['name'] ?? '');
                $role = trim($contact['role'] ?? '');
                $email = trim($contact['email'] ?? '');
                $phone = formatPhoneNumberAPI(trim($contact['phone'] ?? ''));

                // Mindestens Name oder Email erforderlich
                if (empty($name) && empty($email)) {
                    continue;
                }

                // Überprüfe, ob es eine existierende ID oder eine neue ist
                if (is_numeric($id) && $id > 0) {
                    // UPDATE bestehender Kontakt
                    $stmt = $db->prepare("UPDATE project_contacts SET name = ?, role = ?, email = ?, phone_mobile = ? WHERE id = ? AND project_id = ?");
                    $stmt->execute([$name, $role, $email, $phone, $id, $projectId]);
                } else {
                    // INSERT neuer Kontakt
                    $stmt = $db->prepare("INSERT INTO project_contacts (project_id, name, role, email, phone_mobile, is_default) VALUES (?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$projectId, $name, $role, $email, $phone]);
                    $newContactIds[$id] = $db->lastInsertId();
                }
            }

            // 3. Setze Default-Kontakt
            if ($defaultContactId) {
                // Setze zuerst alle auf is_default = 0
                $db->prepare("UPDATE project_contacts SET is_default = 0 WHERE project_id = ?")->execute([$projectId]);

                // Wenn die ID mit "new_" anfängt, suche die eingefügte ID
                if (strpos($defaultContactId, 'new_') === 0) {
                    $actualId = $newContactIds[$defaultContactId] ?? null;
                    if ($actualId) {
                        $db->prepare("UPDATE project_contacts SET is_default = 1 WHERE id = ? AND project_id = ?")->execute([$actualId, $projectId]);
                    }
                } else {
                    // Normale ID
                    $db->prepare("UPDATE project_contacts SET is_default = 1 WHERE id = ? AND project_id = ?")->execute([$defaultContactId, $projectId]);
                }
            }

            $db->commit();

            echo json_encode(['success' => true, 'message' => 'Kontakte gespeichert']);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log($e->getMessage()); echo json_encode(['success' => false, 'error' => 'Integritätsfehler.']);
        }
        exit;
    }
}

$requestDuration = (microtime(true) - $requestStart) * 1000;
Logger::logRequest($action, false, $requestDuration, ['reason' => 'invalid_action']);
echo json_encode(['success' => false, 'error' => 'Ungültige Aktion.']);
?>
