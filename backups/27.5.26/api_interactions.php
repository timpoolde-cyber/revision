<?php
// api_interactions.php
require_once __DIR__ . '/session_handler.php';
check_auth();

header('Content-Type: application/json');

$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['action']) && $input['action'] === 'save_score') {
    $stmt = $db->prepare("UPDATE projects SET last_score = ? WHERE id = ?");
    $stmt->execute([$input['score'], $input['project_id']]);
    echo json_encode(['success' => true]);
    exit;
}

if (isset($input['project_id']) && isset($input['content'])) {
    $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, ?, ?)");
    $stmt->execute([$input['project_id'], $input['type'], $input['content']]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
?>