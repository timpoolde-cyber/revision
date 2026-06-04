<?php
// /Users/timpoolair/R100-CRM/init-session.php
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

require_once __DIR__ . '/session_handler.php';

header('Content-Type: application/json');
echo json_encode(['csrf_token' => generate_csrf_token()]);
?>
