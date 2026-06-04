<?php
// /Users/timpoolair/R100-CRM/invoice.php

// 1. Umgebungsvariablen laden (.env)
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
$currentProjectId = (int)$id;

// 5. POST-Aktion: Projektwert speichern falls gesendet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_project_value') {
    $raw_value = isset($_POST['project_value']) ? trim($_POST['project_value']) : '0';
    
    // Entfernt deutsche Tausender-Punkte, ersetzt Komma durch Datenbank-Punkt
    $cleaned_value = str_replace('.', '', $raw_value);
    $cleaned_value = str_replace(',', '.', $cleaned_value);
    $projectValue = (float)$cleaned_value;
    
    // Update in der Tabelle projects
    $stmt = $db->prepare("UPDATE projects SET budget = ? WHERE id = ?");
    $stmt->execute([$projectValue, $currentProjectId]);
    
    // Interaktion loggen
    $stmt = $db->prepare("INSERT INTO interactions (project_id, type, content) VALUES (?, 'System', ?)");
    $time = (new DateTime())->format('H:i');
    $stmt->execute([$currentProjectId, "$time — Projektwert aktualisiert auf " . number_format($projectValue, 2, ',', '.') . " €"]);
    
    header("Location: invoice.php?id=" . $currentProjectId . "&saved=1");
    exit;
}

// 6. Projektdaten laden (Inklusive des gespeicherten Projektwerts)
$stmt = $db->prepare("SELECT p.*, c.customer_name, c.email, c.address, c.city, c.postal_code FROM projects p LEFT JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
$stmt->execute([$currentProjectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("Projekt nicht gefunden.");
}

// 7. Verfügbare Ansprechpartner für das Dropdown laden
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? ORDER BY is_default DESC, name ASC");
$stmt->execute([$currentProjectId]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Konfiguration für Header & Navigation
$activeControl = 'INV';
require_once __DIR__ . '/functions.php';

// Template laden
// RICHTIG (ermittelt das Verzeichnis automatisch und absolut fehlerfrei):
require_once __DIR__ . '/views/invoice.tpl.php';