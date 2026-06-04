<?php
// /Users/timpoolair/R100-CRM/auth.php
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

require_once __DIR__ . '/session_handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        echo json_encode(['success' => false, 'error' => 'Sicherheits-Token ungültig (CSRF)']);
        exit;
    }

    if ($password === '1234ß') {
        $_SESSION['authenticated'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Zugriff verweigert']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Ungültige Anforderung']);
}
?>
