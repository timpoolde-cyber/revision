<?php
// api_client_update.php
$dbPath = __DIR__ . '/data/rockets.db';
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

if (empty($token)) {
    die("Fehler: Kein oder ungültiger Token.");
}

try {
    $db->beginTransaction();

    // 1. Aktualisiere Kunde
    $stmt = $db->prepare("UPDATE customers SET address = ?, latitude = ?, longitude = ? WHERE secret_token = ?");
    $stmt->execute([$address, $lat, $lng, $token]);
    
    // 2. Suche zugehöriges Projekt für das Logbuch
    $stmt2 = $db->prepare("SELECT id FROM projects WHERE customer_id = (SELECT id FROM customers WHERE secret_token = ? LIMIT 1)");
    $stmt2->execute([$token]);
    $project = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    // 3. Logge System-Interaktion
    if ($project) {
        $stmt3 = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, 'System', 'Kunde hat Stammdaten & GPS-Koordinaten via Token verifiziert.')");
        $stmt3->execute([$project['id']]);
    }
    
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