# IMPLEMENTIERUNGSPROTOKOLL: CRM-Authentifizierung & Benutzerverwaltung

**Projekt:** REVISION100™ CRM-System  
**Zeitraum:** 2026-05-22 bis 2026-05-26  
**Status:** ✅ ABGESCHLOSSEN  
**Version:** 1.0 PRODUKTIV

---

## 1. AUSGANGSSITUATION

Das REVISION100™ CRM-System hatte eine **unsichere Authentifizierung**:
- ❌ Hardcodiertes Admin-Passwort ("1234ß") in Konfigurationsdatei
- ❌ Keine Benutzerverwaltung
- ❌ Keine Passwort-Hashing
- ❌ Keine CSRF-Protection in Login-Form
- ❌ Keine Multi-User-Unterstützung

**Ziel:** Sichere, datenbank-gestützte Authentifizierung mit Benutzerverwaltung implementieren.

---

## 2. IMPLEMENTIERTE KOMPONENTEN

### 2.1 Datenbank-Schema (`init_db.php`)
**Neue Tabelle: `users`**
```sql
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  is_admin INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

**Seeded mit:**
- Admin-User: `admin` / `admin123` (bcrypt-gehashed)

### 2.2 Session-Management (`session_handler.php`)
**Funktionen implementiert:**
- `check_auth()` - Authentifizierung überprüfen
- `login($username, $password)` - Login mit DB-Validierung
- `logout()` - Session beenden
- `generate_csrf_token()` / `verify_csrf_token()` - CSRF-Schutz
- `is_logged_in()` - Status überprüfen
- `get_crm_user()` - Benutzer-Daten laden (⚠️ umbenenannt von `get_current_user()`)

**Sicherheitsfeatures:**
- Password-Hashing mit bcrypt (`password_hash()`)
- CSRF-Token-Generierung (32-Byte random)
- Session-Status-Check vor `session_start()` (verhindert Mehrfachaufrufe)

### 2.3 Login-Form (`index.php`)
**Änderungen:**
- CSRF-Token in Login-Modal eingefügt (hidden input)
- Feldnamen: `crm_user/crm_pass` → `username/password`
- CSRF-Validierung vor Login-Verarbeitung
- Besseres Error-Handling

### 2.4 Benutzerverwaltung (`user_management.php`)
**Features:**
- ✅ Passwort ändern (mit Validierung)
- ✅ Benutzer-Auflistung (Tabelle)
- ✅ Rollenanzeige (Admin/User)
- ✅ Erstellungsdatum

**Geplant (noch zu implementieren):**
- ⏳ Neuen Benutzer anlegen
- ⏳ Benutzer löschen (mit Safeguard)

### 2.5 URL-Rewriting (`.htaccess`)
```apache
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^(.*)$ index.php [L,QSA]
```

---

## 3. BEHOBENE FEHLER

### 3.1 Parse Error: `use` Statements
**Problem:** PHPMailer `use` Statements waren nicht am Anfang der Datei  
**Lösung:** Zu Zeile 5-6 in `index.php` verschoben (nach `<?php`, vor anderen Code)

### 3.2 Fatal Error: Function Redeclaration
**Problem:** `get_current_user()` und andere Funktionen wurden mehrfach deklariert  
**Lösung:** Guards hinzugefügt: `if (!function_exists('function_name')) { ... }`

### 3.3 Session-Persistierungs-Issue
**Problem:** Verschiedene Seiten erhielten unterschiedliche Session-IDs  
**Root Cause:** `session_start()` wurde mehrmals aufgerufen (new session each time)  
**Lösung:** Session-Status-Check vor `session_start()`:
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### 3.4 Type-Mismatch in `check_auth()`
**Problem:** Strikte Gleichheit (`=== true`) schlug fehl bei Integer `1`  
**Lösung:** Zu loose comparison `!$_SESSION['authenticated']` geändert  
**Betroffene Funktionen:** `check_auth()`, `is_logged_in()`, `get_crm_user()`

### 3.5 **KRITISCH: Namenskonflikt mit nativer PHP-Funktion**
**Problem:** 
- Eigene Funktion `get_current_user()` kollidierte mit nativer PHP-Funktion
- Native Funktion gibt System-Benutzer zurück: `"cnrptnfk9"` (Server-User)
- Eigene Funktion wurde ignoriert/überschattet

**Lösung:** 
- Funktion umbenannt: `get_current_user()` → `get_crm_user()`
- `user_management.php` nutzt jetzt direkte DB-Abfrage

**Lehre:** Niemals native PHP-Funktionsnamen verwenden!

---

## 4. TESTING-RESULTS

| Komponente | Test | Status |
|-----------|------|--------|
| Login (index.php) | Anmelden mit admin/admin123 | ✅ PASS |
| Session-Persistierung | Session bleibt zwischen Seiten erhalten | ✅ PASS |
| CRM (crm.php) | Nach Login sichtbar | ✅ PASS |
| User Management | Benutzer-Profil anzeigen | ✅ PASS |
| Passwort ändern | Form funktioniert, DB aktualisiert | ✅ PASS |
| CSRF-Protection | Token validiert sich korrekt | ✅ PASS |
| Password-Hashing | Bcrypt wird verwendet | ✅ PASS |
| Mehrere Benutzer | DB unterstützt mehrere User | ✅ PASS |

---

## 5. SICHERHEITSPRÜFUNG

✅ **Implementiert:**
- Passwort-Hashing (bcrypt)
- CSRF-Token-Schutz
- Session-basierte Authentifizierung
- SQL-Injection-Prävention (Prepared Statements)
- Input-Filterung (`filter_input()`)
- HTTP-Header-basierter Auth-Check

⚠️ **Zu beachten:**
- HTTPS muss auf Produktionsserver aktiviert sein
- Session-Cookies sollten als `HttpOnly` markiert sein (default)
- Passwort-Reset-Flow nicht implementiert

---

## 6. AKTUELLER STATUS

### ✅ Produktiv
- Login-System
- Session-Management
- Passwort-Hashing
- CSRF-Protection
- Benutzerverwaltung (Anzeige + Passwort ändern)

### ⏳ Geplant (nächste Phase)
- Neuen Benutzer anlegen
- Benutzer löschen
- Passwort-Reset
- Admin-Rollenprüfung für sensitive Operationen

---

## 7. DATEIEN - ÄNDERUNGEN ÜBERSICHT

| Datei | Status | Änderungen |
|------|--------|-----------|
| `init_db.php` | ✅ Modifiziert | Neue `users` Tabelle + Seeding |
| `session_handler.php` | ✅ Modifiziert | Auth-Funktionen, Guards, Funktionsumbennung |
| `index.php` | ✅ Modifiziert | CSRF-Token, Login-Logik, `use` Statements |
| `user_management.php` | ✅ Neu erstellt | Benutzerverwaltung UI |
| `.htaccess` | ✅ Modifiziert | URL-Rewriting |
| `crm.php` | ✅ Unverändert | - |

### Test-Dateien (gelöscht)
- `error_test.php`
- `test_minimal.php`
- `simple_session_test.php`
- `session_check.php`
- `test_get_user.php`
- `test_pdo.php`
- `test_session_function.php`
- `test_user.php`
- `verify_session.php`

---

## 8. NÄCHSTE AUFGABEN

**Priorität HOCH:**
1. ⏳ Neue Benutzer anlegen (Form + DB-Insert)
2. ⏳ Benutzer löschen (mit Bestätigung)

**Priorität MITTEL:**
3. Admin-Rollenprüfung für sensitive Operationen
4. Passwort-Reset-Workflow
5. Audit-Logging für User-Operationen

**Priorität NIEDRIG:**
6. 2FA-Authentifizierung
7. Benutzer-Deaktivierung statt Löschung

---

## 9. DEPLOYMENT-NOTES

**Voraussetzungen:**
- PHP 8.0+ (getestet mit 8.3)
- SQLite3
- `.htaccess` Support (Apache)

**One.com Spezifika:**
- Session-Speicherort: auto-konfiguriert auf `/tmp`
- Keine manuel Konfiguration erforderlich
- CloudStorage-Sync: Änderungen sollten automatisch synchronisiert werden

---

---

## 10. PHASE 3: PROJECT-LEVEL CONTACTS HEADER CASCADE (2026-05-26)

### Problem
Headers in mail.php, project.php, pdf_generator.php, und edit_data.php zeigten nur Kundendaten aus der `customers` Tabelle. Project-level Kontakte aus der `project_contacts` Tabelle mit `is_default=1` wurden ignoriert.

### Lösung: Contact Data Cascade
**Implementierungslogik:**
```php
// Load default contact
$defaultContact = null;
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? AND is_default = 1 LIMIT 1");
$stmt->execute([$id]);
$defaultContact = $stmt->fetch(PDO::FETCH_ASSOC);

// Cascade default contact data if available, otherwise use customer data
if ($defaultContact) {
    $project['contact_name'] = $defaultContact['name'];
    $project['email'] = $defaultContact['email'] ?: $project['email'];
    $project['phone_mobile'] = $defaultContact['phone_mobile'] ?: $project['phone_mobile'];
}
```

**Cascade-Hierarchy:**
1. Wenn `project_contacts.is_default=1` existiert: Nutze Kontakt-Daten (name, email, phone_mobile)
2. Fallback: Nutze customer-Daten aus LEFT JOIN
3. Address: Bleibt immer von `customers.address` (nicht in project_contacts vorhanden)

### Modifizierte Dateien
| Datei | Änderung | Status |
|------|----------|--------|
| `mail.php` | Cascade-Logik hinzugefügt (Zeile 39-44) | ✅ |
| `project.php` | Cascade-Logik hinzugefügt (Zeile 41-45) | ✅ |
| `pdf.php` | Cascade-Logik hinzugefügt (Zeile 40-44) | ✅ |
| `edit_data.php` | Cascade-Logik hinzugefügt (Zeile 67-71) | ✅ |
| `api.php` | Unverändert (is_default-Constraint bereits korrekt) | ✓ |

### is_default Constraint
Die API (`api.php`, Zeile 957-959) implementiert korrekt:
```php
$db->prepare("UPDATE project_contacts SET is_default = 0 WHERE project_id = ?")->execute([$projectId]);
$stmt = $db->prepare("UPDATE project_contacts SET is_default = 1 WHERE id = ? AND project_id = ?");
$stmt->execute([$contactId, $projectId]);
```
**Effekt:** Nur ein Kontakt pro Projekt kann `is_default=1` sein.

### Verifizierung
**Test-Szenario:**
1. In edit_data.php einen neuen Kontakt hinzufügen
2. Als "Default" markieren (Radio-Button)
3. In mail.php/project.php/pdf_generator.php überprüfen: 
   - Header zeigt den Kontakt-Namen (nicht Kundennamen)
   - Email und Telefon vom Kontakt (nicht von Kunde)
   - Adresse bleibt von Kunde

**Terminal-Validierung:**
```bash
sqlite3 data/rockets.db "SELECT project_id, name, is_default FROM project_contacts WHERE is_default=1 LIMIT 3;"
```

### Status
✅ Phase 3 ABGESCHLOSSEN
- Contact-Daten werden korrekt kaskadiert
- is_default-Constraint ist garantiert
- Alle 4 Header-Dateien verwenden konsistente Logik
- Fallback-Hierarchie funktioniert

### Bekannte Limitationen
- Address-Feld existiert noch nicht in `project_contacts` (future enhancement möglich)
- PDF-Export zeigt Adresse weiterhin von Kunde (by design)

---

## 11. OPERATIVER EINGRIFF: DATEN-KASKADE REFACTORING (2026-05-26)

### Ziel
Ersetzung des ad-hoc Cascade-Patterns durch ein strukturiertes Weichen-Variablen-System mit expliziter Fallback-Logik zur Vermeidung von Array-Index-Fehlern.

### Implementierung

**Neue Logik (einheitlich in allen 5 Dateien):**
```php
// Load default contact
$defaultContact = null;
$stmt = $db->prepare("SELECT * FROM project_contacts WHERE project_id = ? AND is_default = 1 LIMIT 1");
$stmt->execute([$id]);
$defaultContact = $stmt->fetch(PDO::FETCH_ASSOC);

// Data cascade: Define active variables with fallback logic
$active_name = $defaultContact['name'] ?? $project['customer_name'];
$active_email = $defaultContact['email'] ?? $project['email'];
$active_phone = $defaultContact['phone_mobile'] ?? $project['phone_mobile'];
```

**Frontend-Anpassung (HTML-Header):**
- `$project['email']` → `$active_email ?? ''`
- `$project['phone_mobile']` → `$active_phone ?? ''`
- `$defaultContact['name']` → `$active_name` (mit Fallback)

### Modifizierte Dateien
| Datei | PHP-Logik | HTML-Header | Status |
|------|-----------|-------------|--------|
| mail.php | ✅ Zeile 39-41 | ✅ Zeile 145-147 | produktiv |
| project.php | ✅ Zeile 41-43 | ✅ Zeile 222-223 | produktiv |
| pdf.php | ✅ Zeile 40-42 | ✅ Zeile 119-121 | produktiv |
| pdf_generator.php | ✅ Zeile 34-36 | - (no header) | produktiv |
| edit_data.php | ✅ Zeile 67-69 | ✅ Zeile 175-176 | produktiv |

### Absicherung gegen Array-Index-Fehler
✅ Alle `htmlspecialchars()` Ausgaben mit Null-Coalescing (`?? ''`)  
✅ `$active_*` Variablen immer definiert (PHP 7.0+ Syntax: `??`)  
✅ Kein direkter Zugriff auf `$defaultContact[]` im HTML  

### is_default Constraint
✅ Bleibt in api.php bestehen (set_default_contact Handler):
- UPDATE project_contacts SET is_default = 0 WHERE project_id = ?
- UPDATE project_contacts SET is_default = 1 WHERE id = ? AND project_id = ?

### Qualitätssicherung
**Keine strukturellen Änderungen an:**
- CSS-Styling
- HTML-Layout
- JavaScript-Funktionalität
- Datenbankschema

**Nur PHP-Logik und Variable ersetzt.**

---

**Sanierung abgeschlossen:** 2026-05-26  
**Refactoring Status:** ✅ PRODUKTIV  
**Nächstes Review:** Produktiv-Test mit Benutzerdaten
