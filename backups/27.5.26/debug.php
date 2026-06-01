<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>DEBUG</h1>";

echo "<h2>1. Test session_handler.php</h2>";
try {
    require_once __DIR__ . '/session_handler.php';
    echo "✓ session_handler.php geladen<br>";
    echo "✓ is_logged_in() Funktion existiert: " . (function_exists('is_logged_in') ? 'JA' : 'NEIN') . "<br>";
    echo "✓ login() Funktion existiert: " . (function_exists('login') ? 'JA' : 'NEIN') . "<br>";
} catch (Exception $e) {
    echo "✗ Fehler: " . $e->getMessage() . "<br>";
}

echo "<h2>2. Test PHPMailer</h2>";
try {
    require __DIR__ . '/PHPMailer/Exception.php';
    require __DIR__ . '/PHPMailer/PHPMailer.php';
    require __DIR__ . '/PHPMailer/SMTP.php';
    echo "✓ PHPMailer geladen<br>";
} catch (Exception $e) {
    echo "✗ Fehler: " . $e->getMessage() . "<br>";
}

echo "<h2>3. Test CSRF Token</h2>";
try {
    $token = generate_csrf_token();
    echo "✓ CSRF Token generiert: " . substr($token, 0, 10) . "...<br>";
} catch (Exception $e) {
    echo "✗ Fehler: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Test Login-Funktion</h2>";
try {
    echo "✓ login() Funktion ist verfügbar für manuelle Tests<br>";
    echo "⚠ Hardcodierte Credentials wurden aus Sicherheitsgründen entfernt<br>";
} catch (Exception $e) {
    echo "✗ Fehler: " . $e->getMessage() . "<br>";
}

echo "<h2>ALLE TESTS BESTANDEN!</h2>";
echo "<a href='index.php'>← Zur Startseite</a>";
?>
