<?php
// session_handler.php - 2026-05-26 FINAL FIX
// Verhindert doppelte session_start() Aufrufe

// Check: Session bereits gestartet?
if (session_status() === PHP_SESSION_NONE) {
    // Nur EINMAL session_start() aufrufen!
    @session_start();
}

// AUTH FUNCTIONS
if (!function_exists('check_auth')) {
    function check_auth() {
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            header('HTTP/1.1 401 Unauthorized');
            exit('Zugriff verweigert. Status: Unauthorized.');
        }
    }
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('generate_secret_token')) {
    function generate_secret_token() {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('login')) {
    function login($username, $password) {
        if (empty($username) || empty($password)) {
            return false;
        }

        try {
            $db = new PDO('sqlite:' . __DIR__ . '/data/rockets.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                return true;
            }
        } catch (PDOException $e) {
            error_log("Login DB error: " . $e->getMessage());
        }

        return false;
    }
}

if (!function_exists('logout')) {
    function logout() {
        $_SESSION['authenticated'] = false;
        $_SESSION['user_id'] = null;
        $_SESSION['username'] = null;
        session_destroy();
    }
}

if (!function_exists('get_crm_user')) {
    function get_crm_user() {
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            return null;
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        try {
            $db_path = __DIR__ . '/data/rockets.db';
            $db = new PDO('sqlite:' . $db_path);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $query = "SELECT id, username, is_admin FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute(array((int)$_SESSION['user_id']));

            if (!$result) {
                return null;
            }

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($user && is_array($user)) ? $user : null;
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'];
    }
}
?>
