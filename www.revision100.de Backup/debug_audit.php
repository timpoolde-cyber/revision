<?php
require_once __DIR__ . '/session_handler.php';
check_auth();

header('Content-Type: text/plain; charset=utf-8');

$id = $_GET['id'] ?? 6;
$strategy = $_GET['strategy'] ?? 'mobile';

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);

$stmt = $db->prepare("
    SELECT raw_response, fetch_timestamp, error_message
    FROM psi_results
    WHERE project_id = ? AND strategy = ?
    ORDER BY fetch_timestamp DESC
    LIMIT 1
");
$stmt->execute([$id, $strategy]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Debug Audit für Projekt $id - $strategy\n";
echo "==========================================\n\n";

if (!$record) {
    echo "Keine PSI-Messung gefunden\n";
    exit;
}

echo "Timestamp: " . $record['fetch_timestamp'] . "\n";
echo "Error Message: " . ($record['error_message'] ?? 'None') . "\n";
echo "Raw Response Length: " . strlen($record['raw_response'] ?? '') . " bytes\n\n";

if (!$record['raw_response']) {
    echo "⚠️ Raw Response ist LEER!\n";
    exit;
}

$data = json_decode($record['raw_response'], true);

if (!$data) {
    echo "⚠️ Raw Response ist kein gültiges JSON!\n";
    echo "Raw Response (first 500 chars):\n";
    echo substr($record['raw_response'], 0, 500) . "\n";
    exit;
}

echo "✅ JSON ist gültig\n\n";

// Check lighthouseResult
if (!isset($data['lighthouseResult'])) {
    echo "⚠️ lighthouseResult nicht gefunden!\n";
    echo "Top-level keys: " . implode(', ', array_keys($data)) . "\n";
    exit;
}

$lr = $data['lighthouseResult'];

echo "✅ lighthouseResult existiert\n";
echo "Keys in lighthouseResult: " . implode(', ', array_keys($lr)) . "\n\n";

// Check audits
if (!isset($lr['audits'])) {
    echo "⚠️ audits nicht gefunden!\n";
    exit;
}

echo "✅ audits existiert\n";
echo "Anzahl Audits: " . count($lr['audits']) . "\n\n";

// Show first 3 audits
echo "Erste 3 Audits:\n";
$count = 0;
foreach ($lr['audits'] as $key => $audit) {
    if ($count >= 3) break;
    echo "  - $key: " . ($audit['title'] ?? 'No title') . "\n";
    $count++;
}
?>
