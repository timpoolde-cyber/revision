# PROBLEMBERICHT: Session-Persistierungs-Issue

**Datum:** 2026-05-25  
**Status:** ⚠️ UNGELÖST - Erfordert Server-Konfiguration  
**Severity:** HIGH - User Management funktioniert nicht

---

## Symptome

1. **crm.php funktioniert** ✓
   - Nutzer kann sich einloggen
   - Session ist authentifiziert
   - Navigation funktioniert

2. **user_management.php funktioniert NICHT** ✗
   - Zeigt: "Zugriff verweigert. Status: Unauthorized."
   - `check_auth()` schlägt fehl
   - Session ist NICHT authentifiziert

3. **session_debug.php zeigt Widerspruch** ⚠️
   - Zeigt: `[authenticated] => 1` (TRUE)
   - user_management.php sagt: authenticated = FALSE
   - **→ Unterschiedliche Session-IDs pro Seite!**

---

## Root Cause

**SESSION-PERSISTIERUNGS-PROBLEM:**

Verschiedene PHP-Dateien erhalten verschiedene Session-IDs:
- Wenn Nutzer `session_debug.php` aufruft → Session-ID: ABC123 → authenticated = TRUE
- Wenn Nutzer `user_management.php` aufruft → Session-ID: XYZ789 → authenticated = FALSE

Das deutet auf ein **SERVER-KONFIGURATIONSPROBLEM** hin, nicht auf einen Code-Fehler.

**Server-Informationen:**
```
Session Handler: files
Session Save Path: (EMPTY!)
Session Name: PHPSESSID
Session Cookie Path: /
Session Cookie Domain: (EMPTY)
```

⚠️ **Problem:** `Session Save Path` ist LEER!

---

## Technische Analyse

### Was funktioniert:
- ✅ Login-Logik (`login()` Funktion)
- ✅ Password-Hashing (BCRYPT)
- ✅ CSRF-Protection
- ✅ Datenbank (users-Tabelle existiert)
- ✅ session_handler.php Funktionen laden

### Was nicht funktioniert:
- ❌ Session-Persistierung zwischen Seiten
- ❌ Session-ID bleibt nicht konsistent
- ❌ `check_auth()` kann Session nicht auf anderen Seiten finden

---

## Implementierungs-Status

| Komponente | Status | Problem |
|-----------|--------|---------|
| `init_db.php` | ✅ OK | - |
| `session_handler.php` | ✅ Code OK | Session nicht persistent |
| `index.php` | ✅ Login OK | - |
| `user_management.php` | ✅ Code OK | Kann Session nicht lesen |
| `.htaccess` | ✅ OK | - |
| **Server-Konfiguration** | ❌ PROBLEM | Session Path nicht gesetzt |

---

## Lösungsoptionen

### Option A: Session Path konfigurieren (EMPFOHLEN)
**Wer:** Server-Admin / Hosting-Support  
**Wie:** 
- Setze `session.save_path` in PHP-Konfiguration
- Typisch: `/tmp/php_sessions` oder ähnlich
- Stelle sicher, dass das Verzeichnis existiert und Schreibberechtigungen hat

**Vorteile:**
- Sessions funktionieren global
- Keine Code-Änderungen nötig
- Standard-Lösung

### Option B: Sessions in Datenbank speichern
**Wer:** Entwickler  
**Wie:**
- Session-Handler in `session_handler.php` anpassen
- SQLite-Tabelle für Sessions erstellen
- `session_set_save_handler()` konfigurieren

**Vorteile:**
- Unabhängig von Dateisystem
- Skalierbar
- Sicher

**Nachteil:**
- Erfordert Code-Änderungen

### Option C: Alternative Auth (Cookie-basiert)
**Wer:** Entwickler  
**Wie:**
- Login-Token in Cookie speichern (statt Session)
- Bei jedem Request Cookie validieren
- Token in Datenbank speichern

**Vorteile:**
- Funktioniert ohne Session-Konfiguration
- Moderne Lösung

**Nachteil:**
- Erfordert Umstrukturierung der Auth-Logik

---

## Was der Nutzer später machen sollte

1. **Mit Server-Admin kontakt aufnehmen:**
   - "Die PHP Session Save Path ist nicht konfiguriert"
   - "Bitte setze session.save_path auf einen gültigen Pfad"
   - "Sessions funktionieren momentan nicht zwischen Seiten"

2. **Oder:** Eine der Code-Lösungen (Option B oder C) implementieren

3. **Testen:**
   - Nach Konfiguration: `php init_db.php` nochmal aufrufen
   - Login zu `user_management.php` versuchen
   - Sollte funktionieren

---

## Dateien-Status

✅ **Fertig:**
- `init_db.php` - Datenbank mit users-Tabelle
- `session_handler.php` - Login/Logout Funktionen
- `index.php` - Corrected Login-Form mit CSRF
- `user_management.php` - User Management Interface
- `.htaccess` - URL Rewriting

❌ **Blockiert:**
- `user_management.php` kann nicht verwendet werden (Session-Problem)

---

## Session Debug Info (2026-05-25 14:06)

```
Session ID: 0d36cd34b07c40c80067e572dabbfea2
Session Handler: files
Session Save Path: [EMPTY]
Session Data:
  - csrf_token: 4d8f81e79ff86f2e850516b8c9c4f56b914c3748dbca8473d905381469df7fb6
  - authenticated: 1
  - user_id: 1
  - username: admin
```

⚠️ Aber auf anderen Seiten: authenticated = 0 oder nicht vorhanden

---

**Nächste Schritte:** Server-Admin kontaktieren für session.save_path Konfiguration
