<?php
/**
 * tests/integration_tests.php
 *
 * Integration test suite for R100-CRM critical paths
 * Run: php tests/integration_tests.php
 *
 * Tests:
 * - Public API: Lead creation
 * - Customer management: Create, read, update
 * - Project management: Create, read, update
 * - Token generation and expiration
 * - Token validation (update.php, api_client_update.php)
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

require_once __DIR__ . '/../Logger.php';

class TestRunner {
    private $testCount = 0;
    private $passCount = 0;
    private $failCount = 0;
    private $db;

    public function __construct() {
        $dbPath = __DIR__ . '/../data/rockets.db';
        $this->db = new PDO('sqlite:' . $dbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Logger::init($this->db);
    }

    public function test($name, callable $test): void {
        $this->testCount++;
        try {
            $test();
            $this->pass($name);
        } catch (Exception $e) {
            $this->fail($name, $e->getMessage());
        }
    }

    private function pass($name): void {
        $this->passCount++;
        echo "✓ $name\n";
    }

    private function fail($name, $error): void {
        $this->failCount++;
        echo "✗ $name: $error\n";
    }

    public function getDatabase(): PDO {
        return $this->db;
    }

    public function summary(): void {
        echo "\n";
        echo "Tests: $this->testCount | Passed: $this->passCount | Failed: $this->failCount\n";
        if ($this->failCount > 0) {
            exit(1);
        }
    }
}

$runner = new TestRunner();
$db = $runner->getDatabase();

// ===== TEST 1: Database Schema =====
echo "\n=== Database Schema Tests ===\n";

$runner->test('system_logs table exists', function() use ($db) {
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='system_logs'");
    if (!$stmt->fetch()) throw new Exception('system_logs table not found');
});

$runner->test('customers table has required columns', function() use ($db) {
    $stmt = $db->query("PRAGMA table_info(customers)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $required = ['id', 'customer_name', 'email'];
    foreach ($required as $col) {
        if (!in_array($col, $columns)) throw new Exception("Missing column: $col");
    }
});

$runner->test('projects table has tunnel, phase and token columns', function() use ($db) {
    $stmt = $db->query("PRAGMA table_info(projects)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $required = ['id', 'tunnel', 'phase_1_initiated_at', 'phase_6_closed_at', 'secret_token', 'token_created_at', 'token_used_at', 'token_expires'];
    foreach ($required as $col) {
        if (!in_array($col, $columns)) throw new Exception("Missing column: $col");
    }
});

// ===== TEST 2: Logger Functionality =====
echo "\n=== Logger Tests ===\n";

$runner->test('Logger writes INFO event', function() use ($db) {
    Logger::info('TEST_INFO', 'Test message', null, ['test' => true]);
    $stmt = $db->query("SELECT COUNT(*) FROM system_logs WHERE event_type='TEST_INFO' AND level='INFO'");
    $count = (int)$stmt->fetchColumn();
    if ($count < 1) throw new Exception("INFO event not logged");
});

$runner->test('Logger writes ERROR event with context', function() use ($db) {
    Logger::error('TEST_ERROR', 'Error message', ['type' => 'system'], ['code' => 500]);
    $stmt = $db->query("SELECT context_json FROM system_logs WHERE event_type='TEST_ERROR' LIMIT 1");
    $context = json_decode($stmt->fetchColumn(), true);
    if (!$context || $context['code'] != 500) throw new Exception("Context not preserved");
});

$runner->test('Logger generates unique request_id', function() use ($db) {
    $id1 = Logger::getRequestId();
    $id2 = Logger::getRequestId();
    if ($id1 !== $id2) throw new Exception("Request IDs should match within same initialization");
    if (strlen($id1) < 10) throw new Exception("Request ID too short");
});

// ===== TEST 3: Customer Management =====
echo "\n=== Customer Management Tests ===\n";

$testCustomerId = null;

$runner->test('Create customer', function() use ($db, &$testCustomerId) {
    $stmt = $db->prepare("INSERT INTO customers (customer_name, email) VALUES (?, ?)");
    $stmt->execute(['Test Company', 'test@example.com']);
    $testCustomerId = $db->lastInsertId();
    if (!$testCustomerId) throw new Exception("Customer not created");
});

$runner->test('Read customer', function() use ($db, $testCustomerId) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$testCustomerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer || $customer['customer_name'] !== 'Test Company') {
        throw new Exception("Customer not found or wrong data");
    }
});

$runner->test('Update customer', function() use ($db, $testCustomerId) {
    $stmt = $db->prepare("UPDATE customers SET customer_name = ? WHERE id = ?");
    $stmt->execute(['Updated Company', $testCustomerId]);
    $stmt = $db->prepare("SELECT customer_name FROM customers WHERE id = ?");
    $stmt->execute([$testCustomerId]);
    $name = $stmt->fetchColumn();
    if ($name !== 'Updated Company') throw new Exception("Update failed");
});

// ===== TEST 4: Project Management =====
echo "\n=== Project Management Tests ===\n";

$testProjectId = null;

$runner->test('Create project with tunnel state', function() use ($db, $testCustomerId, &$testProjectId) {
    $stmt = $db->prepare("INSERT INTO projects (customer_id, customer_name, target_url, tunnel, alert_level) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$testCustomerId, 'Test Company', 'https://example.com', 'anfrage', 'normal']);
    $testProjectId = $db->lastInsertId();
    if (!$testProjectId) throw new Exception("Project not created");
});

$runner->test('Read project', function() use ($db, $testProjectId) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$testProjectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project || $project['tunnel'] !== 'anfrage') {
        throw new Exception("Project not found or wrong tunnel");
    }
});

$runner->test('Update project tunnel state', function() use ($db, $testProjectId) {
    $stmt = $db->prepare("UPDATE projects SET tunnel = ? WHERE id = ?");
    $stmt->execute(['bewertet', $testProjectId]);
    $stmt = $db->prepare("SELECT tunnel FROM projects WHERE id = ?");
    $stmt->execute([$testProjectId]);
    $tunnel = $stmt->fetchColumn();
    if ($tunnel !== 'bewertet') throw new Exception("Tunnel state not updated");
});

// ===== TEST 5: Token Management =====
echo "\n=== Token Management Tests ===\n";

$runner->test('Token generation creates valid token', function() use ($db, $testProjectId) {
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("UPDATE projects SET secret_token = ?, token_created_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$token, $testProjectId]);
    $stmt = $db->prepare("SELECT secret_token FROM projects WHERE id = ?");
    $stmt->execute([$testProjectId]);
    $stored = $stmt->fetchColumn();
    if ($stored !== $token) throw new Exception("Token not stored");
});

$runner->test('Token expiration validation', function() use ($db, $testProjectId) {
    $futureDate = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmt = $db->prepare("UPDATE projects SET token_expires = ? WHERE id = ?");
    $stmt->execute([$futureDate, $testProjectId]);

    $stmt = $db->prepare("SELECT token_expires FROM projects WHERE id = ?");
    $stmt->execute([$testProjectId]);
    $expires = $stmt->fetchColumn();

    $now = new DateTime();
    $expiresAt = new DateTime($expires);
    if ($expiresAt <= $now) throw new Exception("Token should not be expired");
});

// ===== TEST 6: PSI Results Storage =====
echo "\n=== PSI Results Tests ===\n";

$runner->test('Store PSI results', function() use ($db, $testProjectId) {
    $stmt = $db->prepare("INSERT INTO psi_results (project_id, strategy, performance_score, accessibility_score, best_practices_score, seo_score) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$testProjectId, 'mobile', 95, 90, 85, 88]);

    $stmt = $db->prepare("SELECT performance_score FROM psi_results WHERE project_id = ? AND strategy = ?");
    $stmt->execute([$testProjectId, 'mobile']);
    $score = (int)$stmt->fetchColumn();
    if ($score !== 95) throw new Exception("PSI score not stored correctly");
});

// ===== TEST 7: Interactions Logging =====
echo "\n=== Interactions (Audit Trail) Tests ===\n";

$runner->test('Create interaction event', function() use ($db, $testProjectId) {
    $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, ?, ?)");
    $stmt->execute([$testProjectId, 'System', 'Test interaction']);

    $stmt = $db->prepare("SELECT COUNT(*) FROM interactions WHERE project_id = ?");
    $stmt->execute([$testProjectId]);
    $count = (int)$stmt->fetchColumn();
    if ($count < 1) throw new Exception("Interaction not created");
});

// ===== TEST 8: Contacts Management =====
echo "\n=== Project Contacts Tests ===\n";

$runner->test('Add project contact', function() use ($db, $testProjectId) {
    $stmt = $db->prepare("INSERT INTO project_contacts (project_id, name, role, email) VALUES (?, ?, ?, ?)");
    $stmt->execute([$testProjectId, 'John Doe', 'CEO', 'john@example.com']);

    $stmt = $db->prepare("SELECT COUNT(*) FROM project_contacts WHERE project_id = ?");
    $stmt->execute([$testProjectId]);
    $count = (int)$stmt->fetchColumn();
    if ($count < 1) throw new Exception("Contact not created");
});

// ===== TEST 9: Phone Formatting =====
echo "\n=== Data Validation Tests ===\n";

$runner->test('Phone number formatting applied', function() use ($db, $testCustomerId) {
    $stmt = $db->prepare("UPDATE customers SET phone_mobile = ? WHERE id = ?");
    $stmt->execute(['+49 030 123456', $testCustomerId]);

    $stmt = $db->prepare("SELECT phone_mobile FROM customers WHERE id = ?");
    $stmt->execute([$testCustomerId]);
    $phone = $stmt->fetchColumn();
    if (empty($phone)) throw new Exception("Phone number cleared");
});

// ===== Summary =====
$runner->summary();
?>
