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
    
    // Setup nur ausführen, wenn die DB leer oder neu ist
    if ($setupRequired) {
        $db->exec("CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT,
            email TEXT,
            phone_mobile TEXT,
            address TEXT,
            city TEXT,
            postal_code TEXT,
            latitude TEXT,
            longitude TEXT,
            secret_token TEXT,
            token_expires DATETIME
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER,
            customer_name TEXT,
            target_url TEXT,
            tunnel TEXT DEFAULT 'anfrage',
            alert_level TEXT,
            next_steps TEXT,
            last_score INTEGER,
            updated_at DATETIME,
            FOREIGN KEY(customer_id) REFERENCES customers(id)
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS interactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER,
            type TEXT,
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(project_id) REFERENCES projects(id)
        )");

        // Fünf Test-Datensätze injizieren
        $db->beginTransaction();
        $dummies = [
            ['name' => 'Weber Maschinenbau', 'url' => 'weber-maschinen.de', 'tunnel' => 'anfrage', 'alert' => 'normal'],
            ['name' => 'Kanzlei Schmidt', 'url' => 'kanzlei-schmidt.de', 'tunnel' => 'analyse', 'alert' => 'kritisch'],
            ['name' => 'Logistik Nord', 'url' => 'logistik-nord.de', 'tunnel' => 'kontakt', 'alert' => 'normal'],
            ['name' => 'Bäckerei Meister', 'url' => 'baeckerei-meister.de', 'tunnel' => 'beauftragung', 'alert' => 'eskalation'],
            ['name' => 'TechStart Berlin', 'url' => 'techstart.berlin', 'tunnel' => 'umsetzung', 'alert' => 'normal']
        ];
        foreach ($dummies as $d) {
            $stmt = $db->prepare("INSERT INTO customers (customer_name, email) VALUES (?, ?)");
            $stmt->execute([$d['name'], 'info@' . $d['url']]);
            $cid = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, alert_level) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$cid, $d['name'], 'https://' . $d['url'], $d['tunnel'], $d['alert']]);
            $pid = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, 'System', 'Initialer Test-Datensatz generiert.')");
            $stmt->execute([$pid]);
        }
        $db->commit();
    }

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

        $mailTo = 'kontakt@revision100.de';
        $mailSubject = 'REVISION100 - Neuer Lead: ' . $name;
        $mailHeaders = "From: system@revision100.de\r\nReply-To: " . $email . "\r\nContent-Type: text/plain; charset=UTF-8";
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
