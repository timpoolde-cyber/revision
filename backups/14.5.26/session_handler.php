<?php
/**
 * REVISION 100 — Session Handler
 * Include at the top of every protected file.
 * Starts a secure session. Does NOT redirect — callers decide the response.
 */

if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,          // session cookie — expires on browser close
        'path'     => '/',
        'secure'   => $secure,    // HTTPS-only when available
        'httponly' => true,        // no JS access to session cookie
        'samesite' => 'Strict',   // CSRF mitigation
    ]);
    session_start();
}

function isAuthenticated(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function requireAuthApi(): void {
    if (!isAuthenticated()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'success'  => false,
            'error'    => 'Unauthorized',
            'redirect' => 'login.php',
        ]);
        exit;
    }
}

function requireAuthPage(): void {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}
