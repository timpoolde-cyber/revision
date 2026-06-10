<?php
// api_client_update.php
require_once __DIR__ . '/Logger.php';

$dbPath = __DIR__ . '/data/rockets.db';
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    Logger::init($db);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Ungültige Anforderung.");
}

$token = $_POST['token'] ?? '';
$address = $_POST['address'] ?? '';
$lat = $_POST['latitude'] ?? '';
$lng = $_POST['longitude'] ?? '';
$newContacts = $_POST['new_contacts'] ?? [];

if (empty($token)) {
    die("Fehler: Kein oder ungültiger Token.");
}

$now = new DateTime();
$stmt = $db->prepare("SELECT token_expires FROM customers c JOIN projects p ON c.id = p.customer_id WHERE p.secret_token = ?");
$stmt->execute([$token]);
$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tokenData && isset($tokenData['token_expires']) && !empty($tokenData['token_expires'])) {
    $expiresAt = new DateTime($tokenData['token_expires']);
    if ($now > $expiresAt) {
        Logger::logTokenValidation('unknown', false);
        die(json_encode(['success' => false, 'error' => 'Zugriff abgelaufen.']));
    }
}

Logger::logTokenValidation('unknown', true);

function formatPhoneNumber($phone) {
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

try {
    $db->beginTransaction();

    // 1. Suche Projekt nach Token
    $stmt = $db->prepare("SELECT p.id, p.customer_id FROM projects p WHERE p.secret_token = ?");
    $stmt->execute([$token]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception("Projekt nicht gefunden.");
    }

    // 2. Aktualisiere Kundenstandort
    $stmt = $db->prepare("UPDATE customers SET address = ?, latitude = ?, longitude = ? WHERE id = ?");
    $stmt->execute([$address, $lat, $lng, $project['customer_id']]);

    // 3. Speichere neue Kontakte (falls vorhanden)
    if (is_array($newContacts)) {
        foreach ($newContacts as $contact) {
            $name = trim($contact['name'] ?? '');
            $role = trim($contact['role'] ?? '');
            $email = trim($contact['email'] ?? '');
            $phone = trim($contact['phone'] ?? '');

            // Mindestens Name oder E-Mail erforderlich
            if (empty($name) && empty($email)) {
                continue;
            }

            // Formatiere Telefon
            if (!empty($phone)) {
                $phone = formatPhoneNumber($phone);
            }

            $stmt = $db->prepare("INSERT INTO project_contacts (project_id, name, email, phone_mobile, is_default) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$project['id'], $name, $email, $phone]);
        }
    }

    // 4. Logge System-Interaktion
    $logMessage = 'Kunde hat Stammdaten & GPS-Koordinaten via Token verifiziert.';
    if (count($newContacts) > 0) {
        $logMessage .= ' ' . count($newContacts) . ' neue Kontakt(e) hinzugefügt.';
    }

    $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, 'System', ?)");
    $stmt->execute([$project['id'], $logMessage]);

    $db->commit();

    // Zurück zum Interface leiten
    echo "<script>alert('Ihre Daten wurden erfolgreich verifiziert und gespeichert.'); window.location.href='update.php?token=" . htmlspecialchars($token) . "';</script>";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Speicherfehler: " . $e->getMessage());
}
?>