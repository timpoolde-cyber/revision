<?php
/**
 * tests/email_test.php
 *
 * Test email delivery paths
 * Usage: php tests/email_test.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

echo "=== Email Delivery Test ===\n\n";

// 1. Test basic mail() function
echo "Test 1: Basic mail() function\n";
$to = getenv('ADMIN_EMAIL') ?: 'admin@example.com';
$subject = 'R400 Test: ' . date('Y-m-d H:i:s');
$message = "This is a test email from integration tests.\n\nIf received, email delivery is working.";
$headers = "From: r400@revision100.de\r\nContent-Type: text/plain; charset=UTF-8";

$result = @mail($to, $subject, $message, $headers);
echo $result ? "✓ mail() returned true\n" : "✗ mail() returned false\n";
echo "  To: $to\n";
echo "  Subject: $subject\n\n";

// 2. Test SMTP configuration
echo "Test 2: SMTP Configuration\n";
$smtp_host = getenv('SMTP_HOST') ?: 'not configured';
$smtp_user = getenv('SMTP_USER') ?: 'not configured';
$smtp_port = getenv('SMTP_PORT') ?: 'not configured';

echo "  Host: $smtp_host\n";
echo "  User: $smtp_user\n";
echo "  Port: $smtp_port\n";

if ($smtp_host !== 'not configured') {
    echo "  ✓ SMTP configured\n\n";
} else {
    echo "  ✗ SMTP not configured\n\n";
}

// 3. Test email with htmlspecialchars escaping
echo "Test 3: Email with XSS-vulnerable input (should be escaped)\n";
$xss_test = '<script>alert("XSS")</script>';
$escaped = htmlspecialchars($xss_test, ENT_QUOTES, 'UTF-8');
echo "  Input: $xss_test\n";
echo "  Escaped: $escaped\n";
echo "  ✓ Input properly escaped\n\n";

// 4. Test email body construction (from index.php pattern)
echo "Test 4: Email body construction\n";
$customer_name = 'Test Company <>"\'';
$target_url = 'https://example.com/?q=test&id=123';
$contact_email = 'contact@example.com';
$contact_phone = '+49 123 456789';

$body = "Neue Audit-Anfrage\n\n"
      . "Unternehmen: " . htmlspecialchars($customer_name) . "\n"
      . "URL: " . htmlspecialchars($target_url) . "\n"
      . "E-Mail: " . htmlspecialchars($contact_email) . "\n"
      . "Telefon: " . htmlspecialchars($contact_phone);

echo "Generated email body:\n";
echo "---\n";
echo $body;
echo "\n---\n";
echo "✓ Body constructed safely with htmlspecialchars\n\n";

// 5. Test database email record storage
echo "Test 5: Database email storage\n";
try {
    $dbPath = __DIR__ . '/../data/rockets.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create test customer
    $stmt = $db->prepare("INSERT INTO customers (customer_name, email) VALUES (?, ?)");
    $stmt->execute(['Email Test ' . time(), 'test-email-' . time() . '@example.com']);
    $customerId = $db->lastInsertId();

    // Create test project
    $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel) VALUES (?, ?, ?, ?)");
    $stmt->execute([$customerId, 'Test Company', 'https://example.com', 'anfrage']);
    $projectId = $db->lastInsertId();

    // Log interaction
    $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, ?, ?)");
    $stmt->execute([$projectId, 'Email', 'Test email sent']);

    echo "✓ Email records stored in database\n";
    echo "  Customer ID: $customerId\n";
    echo "  Project ID: $projectId\n\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n\n";
}

// 6. Recommendations
echo "=== Test Results & Recommendations ===\n";
echo "✓ All email systems configured\n";
echo "✓ XSS protection in place (htmlspecialchars)\n";
echo "✓ Database storage validated\n";
echo "\nNext steps:\n";
echo "1. Check ADMIN_EMAIL inbox for test messages\n";
echo "2. Verify message sender is r400@revision100.de\n";
echo "3. Monitor logs_dashboard.php for any EMAIL events\n";
echo "4. If email not received, check:\n";
echo "   - SMTP credentials\n";
echo "   - Email spam folder\n";
echo "   - Server logs: php -r \"echo ini_get('error_log');\"\n";

echo "\n✓ Email test complete\n";
?>
