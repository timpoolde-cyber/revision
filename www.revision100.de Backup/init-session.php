<?php
// init-session.php
require_once 'session_handler.php';

header('Content-Type: application/json');
echo json_encode(['csrf_token' => generate_csrf_token()]);
?>
