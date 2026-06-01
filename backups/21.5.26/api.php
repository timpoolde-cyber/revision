<?php
// api.php
require_once __DIR__ . '/session_handler.php';

// Allow debug endpoints without auth
$method = $_SERVER['REQUEST_METHOD'];
$action = ($method === 'GET') ? ($_GET['action'] ?? '') : (json_decode(file_get_contents('php://input'), true)['action'] ?? '');
$debugEndpoints = ['psi_debug', 'test_psi_api'];

if (!in_array($action, $debugEndpoints)) {
    check_auth();
}

// Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

header('Content-Type: application/json');

$dbPath = __DIR__ . '/data/rockets.db';
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
    exit;
}

// Hilfsfunktion: Email versendet
function sendEmail($to, $subject, $body, $from = null, $attachment = null) {
    if (!$from) {
        $from = getenv('ADMIN_EMAIL') ?: 'noreply@revision100.de';
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
            $results[$strategy] = ['error' => $e->getMessage(), 'raw' => isset($response) ? $response : 'No response captured'];
        }
    }

    return $results;
}

// Ensure required tables and columns exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS psi_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        strategy TEXT NOT NULL,
        performance_score INTEGER,
        accessibility_score INTEGER,
        best_practices_score INTEGER,
        seo_score INTEGER,
        raw_response LONGTEXT,
        error_message TEXT,
        fetch_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id)
    )");
} catch (Exception $e) {}

// Add token columns to customers if they don't exist
try {
    $db->exec("ALTER TABLE customers ADD COLUMN token_created_at DATETIME");
} catch (Exception $e) {}

try {
    $db->exec("ALTER TABLE customers ADD COLUMN token_used_at DATETIME");
} catch (Exception $e) {}

// Add last_interaction_date to projects if needed (virtual column via query)
// No ALTER needed - we calculate it via subquery

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
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
        }
        exit;
    }

    if ($action === 'run_psi_async') {
        $projectId = $_GET['project_id'] ?? null;
        if ($projectId) {
            try {
                $stmt = $db->prepare("SELECT p.id, p.target_url FROM projects p WHERE p.id = ?");
                $stmt->execute([$projectId]);
                $project = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($project) {
                    $apiKey = getenv('GOOGLE_PSI_API_KEY');
                    if (!$apiKey) {
                        error_log("PSI Error: GOOGLE_PSI_API_KEY not set");
                        exit;
                    }

                    $psiResults = fetchPageSpeedInsights($project['target_url'], $apiKey, $db);

                    foreach ($psiResults as $strategy => $result) {
                        if (isset($result['error'])) {
                            $rawResponse = $result['raw'] ?? null;
                            $stmt = $db->prepare("INSERT INTO psi_results (project_id, strategy, error_message, raw_response, fetch_timestamp) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
                            $stmt->execute([$projectId, $strategy, $result['error'], $rawResponse]);

                            // Telegram alert bei Fehler
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
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("PSI Exception: " . $e->getMessage());
            }
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

    if ($action === 'psi_debug') {
        header('Content-Type: text/plain');
        $projectId = $_GET['id'] ?? 6;
        try {
            $stmt = $db->prepare("SELECT id, project_id, strategy, performance_score, error_message, raw_response, fetch_timestamp FROM psi_results WHERE project_id = ? ORDER BY fetch_timestamp DESC LIMIT 5");
            $stmt->execute([$projectId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "PSI Debug für Projekt $projectId (letzte 5):\n";
            echo "===========================================\n\n";

            // Zeige auch die API-Key Info
            $apiKey = getenv('GOOGLE_PSI_API_KEY');
            echo "API Key Status: " . ($apiKey ? "SET (first 10 chars: " . substr($apiKey, 0, 10) . "...)" : "NOT SET") . "\n\n";

            foreach ($records as $r) {
                echo "ID: {$r['id']}\n";
                echo "Strategy: {$r['strategy']}\n";
                echo "Performance Score: " . ($r['performance_score'] ?? 'NULL') . "\n";
                echo "Error: " . ($r['error_message'] ?? 'None') . "\n";
                echo "Raw Response Length: " . strlen($r['raw_response'] ?? '') . " bytes\n";
                if ($r['raw_response']) {
                    echo "Raw Response (first 500 chars):\n";
                    echo substr($r['raw_response'], 0, 500) . "\n";
                }
                echo "Timestamp: {$r['fetch_timestamp']}\n";
                echo "---\n\n";
            }
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage();
        }
        exit;
    }

    if ($action === 'test_psi_api') {
        header('Content-Type: text/plain; charset=utf-8');
        ini_set('display_errors', 1);

        $testUrl = $_GET['url'] ?? 'https://example.com';
        $apiKey = getenv('GOOGLE_PSI_API_KEY');

        if (!$apiKey) {
            echo "ERROR: GOOGLE_PSI_API_KEY not set\n";
            exit;
        }

        echo "Test PSI API call\n";
        echo "=================\n\n";
        echo "Target URL: $testUrl\n";
        echo "API Key (first 10 chars): " . substr($apiKey, 0, 10) . "...\n\n";

        $baseApiUrl = 'https://pagespeedonline.googleapis.com/v5/pagespeedapi/runPagespeed';
        $categories = ['performance', 'accessibility', 'best-practices', 'seo'];
        $categoryParts = array();
        foreach ($categories as $cat) {
            $categoryParts[] = "category=" . urlencode($cat);
        }
        $categoryParams = implode('&', $categoryParts);
        $strategy = 'mobile';

        $apiUrl = "{$baseApiUrl}?url=" . urlencode($testUrl) . "&key={$apiKey}&{$categoryParams}&strategy={$strategy}";

        echo "Full API URL:\n";
        echo $apiUrl . "\n\n";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 60,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        echo "Response Status:\n";
        if ($response === false) {
            echo "Failed to fetch (no data returned)\n";
        } else {
            echo "Success - " . strlen($response) . " bytes\n\n";
            echo "Response (first 1000 chars):\n";
            echo substr($response, 0, 1000) . "\n";
        }
        exit;
    }

    if ($action === 'get_customers') {
        $stmt = $db->query("SELECT id, customer_name, email, phone_mobile, address, city, postal_code, latitude, longitude FROM customers ORDER BY customer_name ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'get_leads') {
        try {
            $query = "
                SELECT p.id, p.customer_name, p.target_url, p.tunnel, p.alert_level, p.last_score, p.updated_at,
                       (SELECT created_at FROM interactions i WHERE i.project_id = p.id ORDER BY created_at DESC LIMIT 1) as last_interaction_date,
                       (SELECT content FROM interactions i WHERE i.project_id = p.id ORDER BY created_at DESC LIMIT 1) as last_interaction_notes,
                       NULL as psi_mobile_score,
                       '' as token_created_at,
                       '' as token_used_at,
                       NULL as default_contact_name
                FROM projects p
                ORDER BY p.updated_at DESC
            ";
            $stmt = $db->query($query);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

            if ($id) {
                $stmt = $db->prepare("UPDATE customers SET customer_name=?, email=?, phone_mobile=?, address=?, city=?, postal_code=?, latitude=?, longitude=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $address, $city, $postal, $lat, $lon, $id]);
            } else {
                $token = generate_secret_token();
                $stmt = $db->prepare("INSERT INTO customers (customer_name, email, phone_mobile, address, city, postal_code, latitude, longitude, secret_token, token_created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$name, $email, $phone, $address, $city, $postal, $lat, $lon, $token]);
                $id = $db->lastInsertId();
            }

            $stmt = $db->prepare("SELECT secret_token FROM customers WHERE id=?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => ['id' => $id, 'token' => $customer['secret_token']]]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            $alert_level = $input['alert_level'] ?? 'normal';
            $notiz = $input['notiz'] ?? '';
            $net_value = $input['net_value'] ?? null;

            $db->beginTransaction();

            if ($id) {
                $stmt = $db->prepare("UPDATE projects SET customer_id=?, customer_name=?, target_url=?, tunnel=?, alert_level=?, net_value=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$cid, $cname, $url, $tunnel, $alert_level, $net_value, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, alert_level, net_value, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$cid, $cname, $url, $tunnel, $alert_level, $net_value]);
                $id = $db->lastInsertId();
            }

            if (!empty($notiz)) {
                $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, 'Notiz', ?)");
                $stmt->execute([$id, $notiz]);
            }

            $db->commit();

            // Trigger PSI async (non-blocking)
            if (!empty($url)) {
                $asyncUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api.php?action=run_psi_async&project_id=' . $id;
                $opts = ['http' => ['method' => 'GET', 'timeout' => 1]];
                @file_get_contents($asyncUrl, false, stream_context_create($opts));
            }

            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

            // Neuen Token generieren
            $newToken = generate_secret_token();
            $stmt = $db->prepare("UPDATE customers SET secret_token = ?, token_created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newToken, $project['id']]);

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
            echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

            $stmt = $db->prepare("SELECT c.id FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
            $stmt->execute([$projectId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || !$result['id']) {
                echo json_encode(['success' => false, 'error' => 'Projekt oder Kunde nicht gefunden']);
                exit;
            }

            $customerId = $result['id'];
            $newToken = generate_secret_token();
            $stmt = $db->prepare("UPDATE customers SET secret_token = ?, token_created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newToken, $customerId]);

            echo json_encode(['success' => true, 'token' => $newToken]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'renew_token') {
        try {
            $token = $input['token'] ?? '';
            if (!$token) {
                echo json_encode(['success' => false, 'error' => 'Token erforderlich']);
                exit;
            }

            $stmt = $db->prepare("SELECT id FROM customers WHERE secret_token = ?");
            $stmt->execute([$token]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                echo json_encode(['success' => false, 'error' => 'Ungültiger Token']);
                exit;
            }

            $newToken = generate_secret_token();
            $stmt = $db->prepare("UPDATE customers SET secret_token = ?, token_created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newToken, $customer['id']]);

            echo json_encode(['success' => true, 'token' => $newToken]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'run_psi_now') {
        try {
            // Ensure psi_results table exists
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS psi_results (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_id INTEGER NOT NULL,
                    strategy TEXT NOT NULL,
                    performance_score INTEGER,
                    accessibility_score INTEGER,
                    best_practices_score INTEGER,
                    seo_score INTEGER,
                    raw_response LONGTEXT,
                    error_message TEXT,
                    fetch_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(project_id) REFERENCES projects(id)
                )");
            } catch (Exception $e) {
                // Table might already exist, continue
            }

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

            echo json_encode(['success' => true, 'results' => $resultData]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            $netValue = $input['net_value'] ?? null;

            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'Projekt ID erforderlich']);
                exit;
            }

            $stmt = $db->prepare("UPDATE projects SET customer_name = ?, target_url = ?, net_value = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$customerName, $targetUrl, $netValue, $projectId]);

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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

            $stmt = $db->prepare("INSERT INTO project_contacts (project_id, name, email, phone_mobile, is_default) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$projectId, $name, $email, $phoneMobile]);
            $contactId = $db->lastInsertId();

            echo json_encode(['success' => true, 'contact_id' => $contactId, 'message' => 'Kontakt hinzugefügt']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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

            $stmt = $db->prepare("UPDATE project_contacts SET name = ?, email = ?, phone_mobile = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phoneMobile, $contactId]);

            echo json_encode(['success' => true, 'message' => 'Kontakt aktualisiert']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Ungültige Aktion.']);
?>