<?php
// init_db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/data/rockets.db';
$dir = dirname($dbPath);

// Stelle sicher, dass das Verzeichnis existiert
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
    if (!is_dir($dir)) {
        die("FEHLER: Kann Verzeichnis nicht erstellen: " . $dir);
    }
}

// Überprüfe Schreibberechtigung
if (!is_writable($dir)) {
    die("FEHLER: Verzeichnis hat keine Schreibberechtigung: " . $dir);
}

try {
    // Löschen der alten Datenbank für Neuinitialisierung (optional)
    // if (file_exists($dbPath)) { unlink($dbPath); }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabelle: customers
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company TEXT,
        customer_name TEXT,
        email TEXT,
        phone_mobile TEXT,
        address TEXT,
        city TEXT,
        postal_code TEXT,
        latitude TEXT,
        longitude TEXT,
        secret_token TEXT,
        token_expires DATETIME,
        token_created_at DATETIME DEFAULT NULL,
        token_used_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabelle: projects
    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER,
        customer_name TEXT,
        target_url TEXT,
        tunnel TEXT DEFAULT 'anfrage',
        alert_level TEXT,
        next_steps TEXT,
        last_score INTEGER,
        updated_at DATETIME,
        secret_token TEXT,
        budget REAL DEFAULT 0.0,
        phase_1_initiated_at DATETIME,
        phase_2_evaluated_at DATETIME,
        phase_3_contacted_at DATETIME,
        phase_4_engaged_at DATETIME,
        phase_5_implemented_at DATETIME,
        phase_6_closed_at DATETIME,
        FOREIGN KEY(customer_id) REFERENCES customers(id)
    )");

    // Tabelle: interactions
    $db->exec("CREATE TABLE IF NOT EXISTS interactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        type TEXT,
        content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id)
    )");

    // Tabelle: psi_results (PageSpeed Insights API responses)
    $db->exec("CREATE TABLE IF NOT EXISTS psi_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        strategy TEXT NOT NULL,
        performance_score INTEGER,
        accessibility_score INTEGER,
        best_practices_score INTEGER,
        seo_score INTEGER,
        raw_response LONGTEXT,
        error_message TEXT,
        report_quick_json TEXT,
        report_deep TEXT,
        fetch_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id)
    )");

    // Tabelle: project_contacts (Mehrere Ansprechpartner pro Projekt)
    $db->exec("CREATE TABLE IF NOT EXISTS project_contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        role TEXT,
        email TEXT,
        phone_mobile TEXT,
        is_default INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id)
    )");

    // Tabelle: email_templates
    $db->exec("CREATE TABLE IF NOT EXISTS email_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id)
    )");

    // Index für Performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_contacts_project ON project_contacts(project_id, is_default)");

    // Tabelle: users (Sichere Benutzerverwaltung)
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        is_admin INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // HINWEIS: Automatisches Seeding deaktiviert.
    // Admin-Benutzer müssen manuell über user_management.php angelegt werden.
    // Dies verhindert unbeabsichtigte Passwort-Resets bei Datenbankinitialisierungen.

    echo "SYSTEM-MELDUNG: Datenbank rockets.db (CRM 2.8) erfolgreich initialisiert und Schema validiert.";

} catch (PDOException $e) {
    die("KRITISCHER FEHLER BEI DB-INITIALISIERUNG: " . $e->getMessage());
}
?>