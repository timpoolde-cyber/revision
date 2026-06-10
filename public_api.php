<?php
// public_api.php

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
$dir = dirname($dbPath);

// Performance-Check: Muss das Setup ausgeführt werden?
$setupRequired = !file_exists($dbPath) || filesize($dbPath) === 0;

if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Dateirechte: data/ nicht beschreibbar']);
        exit;
    }
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Schema initialization moved to init_db.php
    // Run: php init_db.php before deploying this API

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler bei Initialisierung: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'diagnose') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $url = trim($_POST['url'] ?? '');

    if (empty($name) || empty($email) || empty($url)) {
        echo json_encode(['success' => false, 'error' => 'Alle Felder sind erforderlich.']);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO customers (customer_name, email) VALUES (:name, :email)");
        $stmt->execute([':name' => $name, ':email' => $email]);
        $customerId = $db->lastInsertId();

        $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, alert_level) VALUES (:cid, :cname, :url, 'anfrage', 'normal')");
        $stmt->execute([':cid' => $customerId, ':cname' => $name, ':url' => $url]);
        $projectId = $db->lastInsertId();

        $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (:pid, 'System', 'Neue Diagnose-Anfrage eingegangen')");
        $stmt->execute([':pid' => $projectId]);

        $db->commit();

        $telegramToken = getenv('TELEGRAM_TOKEN');
        $telegramChatId = '6498470414';
        $message = "🚨 REVISION100 LEAD\n\nName: $name\nURL: $url\nEmail: $email";
        
        if ($telegramToken !== 'TELEGRAM_BOT_TOKEN_HIER_EINTRAGEN') {
            $tgUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
            $tgData = [
                'chat_id' => $telegramChatId,
                'text' => $message
            ];
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($tgData),
                    'timeout' => 2 // Blockiert den Request nicht länger als 2 Sekunden
                ]
            ];
            $context  = stream_context_create($options);
            @file_get_contents($tgUrl, false, $context);
        }

        $mailTo = 'r400@revision100.de';
        $mailSubject = 'R400™ - Neuer Lead: ' . $name;
        $mailHeaders = "From: r400@revision100.de\r\nReply-To: " . $email . "\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($mailTo, $mailSubject, $message, $mailHeaders);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Speicherfehler: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Ungültige Aktion.']);
}
?>
