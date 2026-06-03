# 📋 HEADER-ANALYSE: Alle PHP-Dateien vs. Standard (edit_data.php)

## STANDARD-FORMAT (edit_data.php)
```php
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
```

---

## ANALYSE ALLER DATEIEN

### ✅ STANDARD-KONFORM

**1. edit_data.php** 
- ✓ Vollständiger Dateipfad-Comment
- ✓ .env laden
- ✓ Session-Validierung
- ✓ DB-Verbindung

**2. mail.php** 
- ✓ Vollständiger Dateipfad-Comment: `// /Users/timpoolair/revision100/mail.php`
- ✓ .env laden
- ✓ Session-Validierung
- ✓ DB-Verbindung

**3. project.php** 
- ✓ Vollständiger Dateipfad-Comment: `// /Users/timpoolair/revision100/project.php`
- ✓ .env laden
- ✓ Session-Validierung
- ✓ DB-Verbindung

**4. functions.php** 
- ✓ Vollständiger Dateipfad-Comment: `// /Users/timpoolair/revision100/functions.php`
- ⚠️ Keine .env/Session (ist eine Utility-Library, nicht selbstständige Page)
- ⚠️ Keine DB-Verbindung (ist Library)
- 📌 **STATUS**: OK - Zweck ist Hilfsfunktion, daher logisch korrekt

---

### ⚠️ ABWEICHUNGEN - MITTELSCHWER

**5. crm.php** 
- ❌ KEIN Dateipfad-Comment (nur `<?php` + blank line)
- ✓ .env laden
- ✓ Session-Validierung
- ❌ KEINE DB-Verbindung (lädt nur Google Maps Key + functions.php)
- 📌 **BEGRÜNDUNG**: Lädt nur Design-Template (crm.tpl.php), nicht Datenbank
- **ÄNDERUNG**: Dateipfad-Comment hinzufügen

**6. invoice.php** 
- ❌ KEIN Dateipfad-Comment (nur `<?php`)
- ✓ .env laden
- ✓ Session-Validierung
- ✓ DB-Verbindung
- **ÄNDERUNG**: Zeile 2 → `// /Users/timpoolair/revision100/invoice.php`

**7. session_handler.php** 
- ❌ Abweichender Comment: `// session_handler.php - 2026-05-28 MANIFEST v1.2 ROUTING FIX`
- ⚠️ Ist UTILITY-Library (keine eigenständige Page)
- 📌 **STATUS**: OK für Library

**8. init_db.php** 
- ❌ KEIN Dateipfad-Comment (nur `// init_db.php`)
- ❌ Keine Session-Validierung (ist Setup-Script)
- ✓ DB-Initialisierung vorhanden
- 📌 **STATUS**: OK - Setup-Script, keine Auth nötig

**9. user_management.php** 
- ❌ KEIN Dateipfad-Comment (nur `// Cache Buster: 2026-05-26...`)
- ✓ Session-Validierung
- ✓ DB-Verbindung
- **ÄNDERUNG**: Comment zu `// /Users/timpoolair/revision100/user_management.php`

**10. update.php** 
- ❌ KEIN Dateipfad-Comment (nur `// update.php`)
- ✓ .env laden
- ❌ Keine Session-Validierung (PUBLIC External Update-Script)
- ✓ DB-Verbindung
- 📌 **BEGRÜNDUNG**: Extern-zugänglich via Token-Link, daher Auth intentional weg
- **ÄNDERUNG**: Dateipfad-Comment hinzufügen

**11. logout.php** 
- ❌ KEIN Dateipfad-Comment (nur `<?php`)
- ❌ Keine .env laden (nicht nötig)
- ✓ Session-Validierung (destroys session)
- ❌ Keine DB-Verbindung (nicht nötig)
- 📌 **STATUS**: Minimalistische Struktur OK
- **ÄNDERUNG**: Zeile 2 → `// /Users/timpoolair/revision100/logout.php`

---

### 🔴 KRITISCHE ABWEICHUNGEN - MUSS KORRIGIERT WERDEN

**12. auth.php** ⚠️⚠️⚠️
- ❌ Comment nur `// auth.php` (KEIN Dateipfad)
- 🔴 **KRITISCH**: `require_once 'session_handler.php'` (RELATIVE PATH!)
  - **PROBLEM**: Bei Pfadänderungen könnte die Datei nicht gefunden werden
  - **LÖSUNG**: Zu `require_once __DIR__ . '/session_handler.php'` ändern
- ❌ header('Content-Type: application/json') AM ANFANG (zu früh)
  - **PROBLEM**: Sollte nach Session-Validierung sein
- ❌ Keine .env laden
- **ÄNDERUNG NÖTIG**: Zeile 1-3 umstrukturieren

**13. api.php** ⚠️
- ❌ Comment nur `// api.php` (KEIN Dateipfad)
- ⚠️ **REIHENFOLGE FALSCH**: Session-Validierung VOR .env laden
  - Zeile 3: `require_once __DIR__ . '/session_handler.php';`
  - Zeile 14-22: `.env laden` (sollte VOR Session sein!)
- ✓ check_auth() mit Bedingung für Debug-Endpoints OK
- **ÄNDERUNG**: .env-Block VOR session_handler require verschieben

**14. api_customers.php** ⚠️
- ❌ KEIN Comment am Anfang
- 🔴 **PROBLEM**: `header('Content-Type: application/json; charset=utf-8');` in Zeile 2 (VOR Auth!)
  - **SOLUTION**: Nach `check_auth();` verschieben
- ✓ Session-Validierung vorhanden
- ⚠️ Keine .env laden
- **ÄNDERUNG**: header() nach Auth verschieben + Comment + .env

**15. init-session.php** ⚠️
- ❌ Comment nur `// init-session.php`
- 🔴 **KRITISCH**: `require_once 'session_handler.php'` (RELATIVE PATH!)
  - **LÖSUNG**: Zu `require_once __DIR__ . '/session_handler.php'` ändern
- ❌ header() am Anfang (OK für Mini-Script, aber sollte Konsistenz geben)
- **ÄNDERUNG**: Relative Path zu __DIR__ + Dateipfad-Comment

---

### 🟡 PROBLEMATISCH ABER ABSICHTLICH UNTERSCHIEDLICH

**16. index.php**
- ❌ Großes Docblock-Comment statt einfachem Pfad-Comment
- ❌ Keine check_auth() (PUBLIC FRONTEND!)
- ✓ session_start() inline (nicht via handler - bei Public-Pages OK)
- 📌 **STATUS**: ABSICHTLICH ANDERS - Das ist Public Landing Page, keine CRM-Page
- **ÄNDERUNG**: Nicht notwendig, aber wenn Harmonisierung gewünscht: Comment anpassen

**17. api_client_update.php**
- ❌ Comment nur `// api_client_update.php`
- ❌ Keine .env laden
- ❌ Keine Session-Validierung (TOKEN-basiert, nicht Session!)
- 📌 **STATUS**: Absichtlich anders (externe API mit Token)
- **ÄNDERUNG**: Optional - Dateipfad-Comment hinzufügen

**18. pdf_generator.php**
- ❌ Comment nur `// pdf_generator.php`
- ✓ Session-Validierung
- ❌ **FEHLEND**: .env laden (nicht nötig, aber Konsistenz)
- ✓ DB-Verbindung
- **ÄNDERUNG**: .env-Block hinzufügen + Dateipfad-Comment

**19. psi_history_pdf_generator.php**
- ❌ Comment nur `// psi_history_pdf_generator.php`
- ✓ Session-Validierung
- ❌ **FEHLEND**: .env laden
- ✓ DB-Verbindung
- **ÄNDERUNG**: .env-Block hinzufügen + Dateipfad-Comment

**20. psi_audit_pdf_generator.php**
- ❌ Comment nur `// psi_audit_pdf_generator.php`
- ✓ Session-Validierung
- ❌ **FEHLEND**: .env laden
- ✓ DB-Verbindung
- **ÄNDERUNG**: .env-Block hinzufügen + Dateipfad-Comment

**21. public_api.php**
- ❌ Comment nur `// public_api.php`
- ✓ .env laden
- ❌ Keine check_auth() (ist PUBLIC API - intentional!)
- 📌 **STATUS**: OK - Public-facing API ohne Auth
- **ÄNDERUNG**: Optional - Dateipfad-Comment hinzufügen

**22. hiob.php**
- 🔴 **ANOMALIE**: IST HTML, NICHT PHP!
- ❌ DOCTYPE html statt PHP Header
- ❌ `.php` Datei-Extension aber reiner HTML-Inhalt
- 📌 **FRAGE**: Sollte das `hiob.html` sein? Oder ist das absichtlich .php?
- **EMPFEHLUNG**: Wenn nur HTML → zu `.html` umbenennen

**23. api_interactions.php**
- ✓ Session-Validierung vorhanden
- ✓ DB-Verbindung
- ❌ KEIN Dateipfad-Comment
- ❌ FEHLEND: .env laden
- **ÄNDERUNG**: Dateipfad-Comment + .env-Block

---

## 🎯 CHANGE-PROPOSAL - PRIORISIERT

### PRIORITY 1 - KRITISCH (SICHERHEIT)
Muss sofort korrigiert werden:

| Datei | Problem | Änderung |
|-------|---------|----------|
| **auth.php** | Relative Path `'session_handler.php'` | → `__DIR__ . '/session_handler.php'` |
| **init-session.php** | Relative Path `'session_handler.php'` | → `__DIR__ . '/session_handler.php'` |
| **api_customers.php** | header() VOR check_auth() | Zeilen verschieben: Auth → header() |

### PRIORITY 2 - HOCHBEDEUTSAM (KONSISTENZ)
Sollte bei nächster Überarbeitung gemacht werden:

| Datei | Änderung |
|-------|----------|
| **crm.php** | Zeile 2 einfügen: `// /Users/timpoolair/revision100/crm.php` |
| **invoice.php** | Zeile 2 einfügen: `// /Users/timpoolair/revision100/invoice.php` |
| **user_management.php** | Zeile 2 ersetzen mit: `// /Users/timpoolair/revision100/user_management.php` |
| **logout.php** | Zeile 2 einfügen: `// /Users/timpoolair/revision100/logout.php` |
| **update.php** | Zeile 2 ersetzen mit: `// /Users/timpoolair/revision100/update.php` |
| **pdf_generator.php** | Zeile 2 ersetzen + .env-Block VOR session_handler |
| **psi_history_pdf_generator.php** | Zeile 2 ersetzen + .env-Block VOR session_handler |
| **psi_audit_pdf_generator.php** | Zeile 2 ersetzen + .env-Block VOR session_handler |
| **api_interactions.php** | Zeile 2 hinzufügen + .env-Block |

### PRIORITY 3 - OPTIONAL
Kann offen bleiben:

| Datei | Grund |
|-------|-------|
| index.php | Public Frontend - unterschiedliche Struktur ist OK |
| api_client_update.php | Token-basierte API - kein Session-Auth intentional |
| public_api.php | Public API - kein Auth intentional |
| session_handler.php | Utility Library - andere Struktur OK |
| functions.php | Utility Library - andere Struktur OK |
| **hiob.php** | ❓ Sollte das .html sein statt .php? → FRAGE AN USER |

---

## DETAILLIERTE CHANGE-ANWEISUNGEN

### 1️⃣ auth.php - KRITISCH
```php
// AKTUELL (FALSCH):
<?php
// auth.php
require_once 'session_handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        ...

// SOLLTE SEIN:
<?php
// /Users/timpoolair/revision100/auth.php

// 1. Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// 2. Session-Validierung
require_once __DIR__ . '/session_handler.php';

// 3. JSON Response
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ...
```

### 2️⃣ init-session.php - KRITISCH
```php
// AKTUELL (FALSCH):
<?php
// init-session.php
require_once 'session_handler.php';

header('Content-Type: application/json');
echo json_encode(['csrf_token' => generate_csrf_token()]);
?>

// SOLLTE SEIN:
<?php
// /Users/timpoolair/revision100/init-session.php

// 1. Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// 2. Session-Validierung
require_once __DIR__ . '/session_handler.php';

// 3. JSON Response
header('Content-Type: application/json');
echo json_encode(['csrf_token' => generate_csrf_token()]);
?>
```

### 3️⃣ api_customers.php - KRITISCH
```php
// AKTUELL (FALSCH):
<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/session_handler.php';

try {
    // DB-Code...

// SOLLTE SEIN:
<?php
// /Users/timpoolair/revision100/api_customers.php

// 1. Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// 2. Session-Validierung ZUERST
require_once __DIR__ . '/session_handler.php';
check_auth();

// 3. DANN Header setzen
header('Content-Type: application/json; charset=utf-8');

try {
    // DB-Code...
```

### 4️⃣ api.php - ReihenfolgeANDERUNG
```php
// AKTUELL (WRONG ORDER):
<?php
// api.php
require_once __DIR__ . '/session_handler.php';

// Allow debug endpoints without auth
$method = $_SERVER['REQUEST_METHOD'];
...

if (!in_array($action, $debugEndpoints)) {
    check_auth();
}

// Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    ...

// SOLLTE SEIN:
<?php
// /Users/timpoolair/revision100/api.php

// 1. Load .env ZUERST
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// 2. Session-Validierung
require_once __DIR__ . '/session_handler.php';

// Allow debug endpoints without auth
$method = $_SERVER['REQUEST_METHOD'];
...

if (!in_array($action, $debugEndpoints)) {
    check_auth();
}
```

---

## ✅ SUMMARY

**Dateien mit KRITISCHEN Sicherheitsproblemen:**
- auth.php (Relative Path)
- init-session.php (Relative Path)
- api_customers.php (header() vor Auth)

**Dateien mit Konsistenz-Abweichungen:**
- 8 Dateien ohne Dateipfad-Comment
- 3 PDF-Generator ohne .env

**Absichtlich unterschiedlich (OK):**
- index.php (Public Page)
- public_api.php (Public API)
- api_client_update.php (Token-API)
- session_handler.php (Library)
- functions.php (Library)

**Anomalie:**
- hiob.php (HTML in .php Datei - sollte überprüft werden)
