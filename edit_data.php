<?php
// /Users/timpoolair/revision100/edit_data.php

// 1. Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// 2. Sicherheits- und Session-Validierung
require_once __DIR__ . '/session_handler.php';
check_auth();

// 3. Datenbank-Verbindung aufbauen
$dbPath = __DIR__ . '/data/rockets.db';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Sicherstellen, dass die Kontakttabelle existiert
try {
    $db->exec("CREATE TABLE IF NOT EXISTS project_contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        email TEXT,
        phone_mobile TEXT,
        is_default INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_contacts_project ON project_contacts(project_id, is_default)");
} catch (Exception $e) {
    error_log("Database table creation error: " . $e->getMessage());
}

// 4. Projekt-ID validieren
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: crm.php');
    exit;
}
$currentProjectId = (int)$id; // Bereitstellung für die nav.tpl.php

// 5. Projektdaten laden
$stmt = $db->prepare("SELECT p.*, c.email, c.phone_mobile, c.address, c.city, c.postal_code FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
$stmt->execute([$currentProjectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("Projekt nicht gefunden.");
}

// 6. Standard-Ansprechpartner laden
$defaultContact = null;
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? AND is_default = 1 LIMIT 1");
$stmt->execute([$currentProjectId]);
$defaultContact = $stmt->fetch(PDO::FETCH_ASSOC);

// Daten-Kaskade mit Fallback-Logik
$active_name = $defaultContact['name'] ?? $project['customer_name'];
$active_email = $defaultContact['email'] ?? $project['email'];
$active_phone = $defaultContact['phone_mobile'] ?? $project['phone_mobile'];

// 7. Alle Kontakte des Projekts laden
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? ORDER BY is_default DESC, name ASC");
$stmt->execute([$currentProjectId]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Falls leer, ersten Kontakt automatisch anlegen
if (empty($contacts) && $project['customer_id']) {
    $stmt = $db->prepare("SELECT customer_name, email, phone_mobile FROM customers WHERE id = ?");
    $stmt->execute([$project['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        $stmt = $db->prepare("INSERT INTO project_contacts (project_id, name, email, phone_mobile, is_default) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$currentProjectId, $customer['customer_name'], $customer['email'], $customer['phone_mobile']]);

        // Kontakte neu einlesen
        $stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? ORDER BY is_default DESC, name ASC");
        $stmt->execute([$currentProjectId]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 8. Konfiguration für die Navigationsleiste & API-Schlüssel
$activeControl = 'Data';
require_once __DIR__ . '/functions.php';

// Google Maps Key einlesen (mit Fallback aus vorigem String, falls nicht in .env)
$googleMapsKey = getenv('GOOGLE_MAPS_KEY');
if (empty($googleMapsKey)) {
    $googleMapsKey = 'AIzaSyDd-5mfiEfL-myp5hHT9B4IXWpMTxk7sqM'; 
}

// 9. Das isolierte Design-Template laden
require_once __DIR__ . '/views/edit_data.tpl.php';