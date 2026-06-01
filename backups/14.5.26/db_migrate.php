<?php
/**
 * Datenbank-Migration: Neues Kunden/Projekt-Modell
 * - Neue customers Tabelle (KNR)
 * - Projekte erhalten PNR
 */

try {
    $db = new PDO('sqlite:' . __DIR__ . '/data/rockets.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── 1. CUSTOMERS Tabelle erstellen
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        knr             TEXT UNIQUE,
        customer_name   TEXT NOT NULL,
        email           TEXT DEFAULT '',
        phone           TEXT DEFAULT '',
        address         TEXT DEFAULT '',
        city            TEXT DEFAULT '',
        postal_code     TEXT DEFAULT '',
        country         TEXT DEFAULT 'DE',
        created_at      TEXT DEFAULT (datetime('now','localtime')),
        updated_at      TEXT DEFAULT (datetime('now','localtime'))
    )");

    // ── 2. PROJECTS Tabelle migrieren
    $db->exec("ALTER TABLE projects ADD COLUMN pnr TEXT UNIQUE");
    $db->exec("ALTER TABLE projects ADD COLUMN customer_id INTEGER");

    // ── 3. KNR-Generierung für bestehende Kunden
    $stmt = $db->prepare("SELECT id FROM projects ORDER BY created_at");
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed_names = [];
    $customer_count = 0;

    foreach ($projects as $proj) {
        $proj_id = $proj['id'];
        $stmt = $db->prepare("SELECT customer_name, email FROM projects WHERE id = ?");
        $stmt->execute([$proj_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!isset($processed_names[$data['customer_name']])) {
            // Neuer Kunde
            $customer_count++;
            $knr = 'KNR-' . str_pad($customer_count, 3, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO customers (knr, customer_name, email) VALUES (?, ?, ?)");
            $stmt->execute([$knr, $data['customer_name'], $data['email']]);
            $customer_id = $db->lastInsertId();
            $processed_names[$data['customer_name']] = $customer_id;
        } else {
            $customer_id = $processed_names[$data['customer_name']];
        }

        // PNR zuweisen
        $pnr = 'PNR-' . str_pad($proj_id, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("UPDATE projects SET pnr = ?, customer_id = ? WHERE id = ?");
        $stmt->execute([$pnr, $customer_id, $proj_id]);
    }

    echo json_encode(['success' => true, 'customers' => $customer_count, 'projects' => count($projects)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
