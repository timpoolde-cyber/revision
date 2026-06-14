<?php
// /Users/timpoolair/revision100/mail.php

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

// 4. Projekt-ID validieren
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: crm.php');
    exit;
}
$currentProjectId = (int)$id; // Einheitliche Bereitstellung für das System und die nav.tpl.php

// 5. Projektdaten laden
$stmt = $db->prepare("SELECT p.*, c.email, c.phone_mobile, c.address, c.city, c.postal_code FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
$stmt->execute([$currentProjectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("Projekt nicht gefunden.");
}

// 6. Standard-Ansprechpartner laden falls verfügbar
$defaultContact = null;
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? AND is_default = 1 LIMIT 1");
$stmt->execute([$currentProjectId]);
$defaultContact = $stmt->fetch(PDO::FETCH_ASSOC);

// Daten-Kaskade mit Fallback-Logik
$active_name = $defaultContact['name'] ?? $project['customer_name'];
$active_email = $defaultContact['email'] ?? $project['email'];
$active_phone = $defaultContact['phone_mobile'] ?? $project['phone_mobile'];

// 7. Konfiguration für das System-Branding & Navigation
$activeControl = 'Mail';
require_once __DIR__ . '/functions.php';

// Hilfsfunktion zur Rufnummernbereinigung (wird im Template verwendet)
if (!function_exists('formatPhoneNumber')) {
    function formatPhoneNumber($phone) {
        $phone = trim($phone);
        if (empty($phone)) { return ''; }
        if (strpos($phone, '+49') === 0) { return $phone; }
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($cleaned, '0049') === 0) { $cleaned = substr($cleaned, 4); } 
        elseif (strpos($cleaned, '49') === 0) { $cleaned = substr($cleaned, 2); } 
        elseif (strpos($cleaned, '0') === 0) { $cleaned = substr($cleaned, 1); }
        if (strlen($cleaned) < 3) { return $phone; }
        $vorwahl = substr($cleaned, 0, 3);
        $rest = substr($cleaned, 3);
        return '+49 ' . $vorwahl . ' ' . $rest;
    }
}

// 8. Das isolierte Design-Template laden
require_once __DIR__ . '/views/mail.tpl.php';