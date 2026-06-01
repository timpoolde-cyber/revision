<?php
$db = new PDO('sqlite:' . __DIR__ . '/data/rockets.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$testCustomers = [
    ['name' => 'Mustermann GmbH', 'email' => 'kontakt@mustermann.de', 'phone' => '+49 30 123456'],
    ['name' => 'Schmidt & Partner', 'email' => 'info@schmidt-partner.de', 'phone' => '+49 40 654321'],
    ['name' => 'Berlin Digital AG', 'email' => 'hello@berlin-digital.de', 'phone' => '+49 89 987654'],
    ['name' => 'Tech Solutions KG', 'email' => 'support@tech-solutions.de', 'phone' => '+49 69 555555'],
    ['name' => 'Hamburg Media House', 'email' => 'contact@hamburg-media.de', 'phone' => '+49 172 444444'],
];

foreach ($testCustomers as $customer) {
    $stmt = $db->prepare("SELECT MAX(CAST(SUBSTR(knr, 5) AS INTEGER)) as max_num FROM customers WHERE knr LIKE 'KNR-%'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_num = ($result['max_num'] ?? 0) + 1;
    $knr = 'KNR-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("INSERT INTO customers (knr, customer_name, email, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute([$knr, $customer['name'], $customer['email'], $customer['phone']]);
    $customer_id = $db->lastInsertId();

    echo "✓ Kunde erstellt: $knr - {$customer['name']}\n";
}

echo "\n5 Testkunden erfolgreich erstellt!\n";
