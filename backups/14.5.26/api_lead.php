<?php
/**
 * Lead-API: Öffentlicher Endpoint für Website-Anfragen
 * POST api_lead.php → erstellt Projekt + Kunde + Kontakt + Stufe 1
 */

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new PDO('sqlite:' . __DIR__ . '/data/rockets.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validierung - nur Name und URL erforderlich
        $contact_name = trim($input['contact_name'] ?? '');
        $company = trim($input['company'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? '');
        $url = trim($input['url'] ?? '');
        $message = trim($input['message'] ?? '');

        if (!$contact_name || !$url) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Name und Website-URL erforderlich']);
            exit;
        }

        // 1. Neuen Kunden erstellen
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTR(knr, 5) AS INTEGER)) as max_num FROM customers WHERE knr IS NOT NULL AND LENGTH(knr) > 4");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_num = ($result['max_num'] ?? 0) + 1;
        $knr = 'KNR-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);

        // Use company name, fallback to contact name or URL domain
        $customer_name = $company ?: ($contact_name ?: parse_url($url, PHP_URL_HOST));
        $stmt = $db->prepare("INSERT INTO customers (knr, customer_name, email, phone, country) VALUES (?, ?, ?, ?, 'DE')");
        $stmt->execute([$knr, $customer_name, $email, $phone]);
        $customer_id = (int)$db->lastInsertId();

        // 2. Neues Projekt erstellen mit Stufe 1 (sondierung + offen)
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTR(pnr, 5) AS INTEGER)) as max_num FROM projects WHERE customer_id = ? AND pnr IS NOT NULL AND LENGTH(pnr) > 4");
        $stmt->execute([$customer_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_num = ($result['max_num'] ?? 0) + 1;
        $pnr = 'PNR-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("INSERT INTO projects (
            customer_id, pnr, customer_name, email, target_url,
            tunnel, project_status, prioritaet, notiz, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))");

        $stmt->execute([
            $customer_id,
            $pnr,
            $company,
            $email,
            $url,
            'anfrage',         // Stufe 1
            'offen',
            'normal',
            $message ?: 'Anfrage vom öffentlichen Formular'
        ]);
        $project_id = (int)$db->lastInsertId();

        // 3. Neuen Kontakt (die Person, die das Formular ausgefüllt hat)
        $stmt = $db->prepare("INSERT INTO contacts (customer_id, name, phone, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$customer_id, $contact_name, $phone, $email]);
        $contact_id = (int)$db->lastInsertId();

        // 4. Initiale Interaktion erstellen
        $stmt = $db->prepare("INSERT INTO interactions (project_id, type, notes) VALUES (?, ?, ?)");
        $stmt->execute([$project_id, 'email', 'Anfrage vom öffentlichen Lead-Formular eingegangen']);

        // Erfolgreiche Antwort
        echo json_encode([
            'success' => true,
            'data' => [
                'customer_id' => $customer_id,
                'knr' => $knr,
                'project_id' => $project_id,
                'pnr' => $pnr,
                'contact_id' => $contact_id
            ]
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Speichern: ' . $e->getMessage()
    ]);
}
