<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($csrf_token) || !preg_match('/^[a-f0-9]{64}$/', $csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF-Token ungültig']);
        exit;
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = $csrf_token;
    } elseif (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF-Token ungültig']);
        exit;
    }

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }

    $now = time();
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($t) use ($now) {
        return $t > ($now - 60);
    });

    if (count($_SESSION['login_attempts']) >= 5) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Zu viele Versuche. Bitte später erneut versuchen.']);
        exit;
    }

    if ($password === 'Rockets2026!') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['login_attempts'] = [];
        echo json_encode(['success' => true]);
    } else {
        $_SESSION['login_attempts'][] = $now;
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Passwort falsch']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'POST erforderlich']);
}
?>
