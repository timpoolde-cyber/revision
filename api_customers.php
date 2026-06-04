<?php
// /Users/timpoolair/R100-CRM/api_customers.php
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

require_once __DIR__ . '/session_handler.php';
check_auth();

header('Content-Type: application/json; charset=utf-8');

try {
    $dbFile = __DIR__ . '/data/rockets.db';
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');

    // Automatisches Schema-Update
    $columns = $pdo->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $missing = ['phone_mobile', 'phone_fixed', 'city', 'postal_code'];
    foreach ($missing as $col) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN $col TEXT DEFAULT ''");
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $pMobile = $data['phone_mobile'] ?? ($data['phone'] ?? '');
        $pFixed  = $data['phone_fixed'] ?? '';
        $name    = $data['customer_name'] ?? '';
        $email   = $data['email'] ?? '';
        $addr    = $data['address'] ?? '';
        $city    = $data['city'] ?? '';
        $plz     = $data['postal_code'] ?? '';

        if (!$name) throw new Exception("Name fehlt.");

        if (isset($data['id']) && !empty($data['id'])) {
            $sql = "UPDATE customers SET
                    customer_name = ?, email = ?, phone_mobile = ?,
                    phone_fixed = ?, address = ?, city = ?, postal_code = ?,
                    updated_at = datetime('now','localtime')
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $email, $pMobile, $pFixed, $addr, $city, $plz, $data['id']]);
            $savedId = $data['id'];
        } else {
            $maxId = (int)$pdo->query("SELECT MAX(id) FROM customers")->fetchColumn();
            $knr = 'KNR-' . str_pad($maxId + 1, 3, '0', STR_PAD_LEFT);
            $sql = "INSERT INTO customers (knr, customer_name, email, phone_mobile, phone_fixed, address, city, postal_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$knr, $name, $email, $pMobile, $pFixed, $addr, $city, $plz]);
            $savedId = $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$savedId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM customers ORDER BY customer_name ASC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
