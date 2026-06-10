# CLAUDE.md — SYSTEM-ARCHITEKTUR & ENTWICKLUNGS-RICHTLINIEN

## 🚨 EXPRESS-ERLAUBNIS FÜR SICHERHEITS-REFACTORING (2026-06-10)
- **AUTH & SESSIONS UNLOCKED:** Die Dateien `session_handler.php` und `auth.php` sind explizit für strukturelle Sicherheitsänderungen FREIGEGEBEN. 
- **BACKDOOR REMOVAL:** Das Entfernen von hartcodierten Passwörtern, Test-Authentifizierungen und globalen Dev-Mode-Bypässen in der Live-Umgebung ist oberste Priorität.
- **SECRET MANAGEMENT:** Die Migration von Klartext-Secrets (Telegram, Google, SMTP) in eine zentrale `.env`-Struktur und deren Absicherung via `.htaccess` ist ausdrücklich autorisiert und im Scope.

---

## 1. SYSTEM-ARCHITEKTUR & REIHENFOLGE
Das System wird von historischem Ballast befreit und auf ein professionelles, gehärtetes Produktions-Niveau angehoben.

### Stufe 1: Kritische Härtung (Sofort)
1. `.env`-Infrastruktur im Web-Root etablieren.
2. Root-`.htaccess` zum Schutz aller sensiblen Endungen (`.env`, `.db`, `.log`, `.md`) einrichten.
3. `LOCAL_DEV_MODE` in `session_handler.php` zwingend an `getenv('APP_ENV') === 'local'` koppeln.
4. Hardcodiertes Passwort in `auth.php` vernichten und komplett auf DB-basiertes `login()` umstellen.
5. Secrets aus `functions.php` und `config/config.php` extrahieren.

### Stufe 2: Datenlecks & Aufräumarbeiten (Folgend)
1. Debug-Endpunkte (`psi_debug`, `test_psi_api`) aus `api.php` entfernen oder hinter `check_auth()` zwingen.
2. Rohe Fehlermeldungen (`$e->getMessage()`, Stacktraces) für den Client maskieren; durchgängig `error_log()` nutzen.
3. Alten, redundanten VIP-Funnel-Ordner `/vip/` inklusive `leads.sqlite` restlos löschen. Nur `/flow/vip/` bleibt aktiv.
4. Token-Ablaufprüfung (`token_expires`) konsistent in `update.php` und `api_client_update.php` erzwingen.

---

## 2. DESIGN-SPRINZIP (HfG-STIL)
- **Radikaler industrialistischer Minimalismus:** Keine dekorativen Elemente, keine Floskeln, kein Marketing-Geschwurbel.
- **Farbschema:** Dunkel, fokussiert. Signal-Grün (`#00FF66`) exklusiv für aktive Zustände, Grau (`#888`) für inaktive/beendete Prozesse.
- **Typografie:** Stringenter Fokus auf Monospace-Schriften, Noun-Heavy (substantivlastig), präzise.

---

## 3. CODE-KONVENTIONEN
- **Keine Redundanz:** Funktionen wie `formatPhoneNumber` gehören einmalig zentralisiert in `functions.php`.
- **Datenbank:** Konsequente Nutzung von PDO mit Prepared Statements zur vollständigen Prävention von SQL-Injections.
- **Error-Handling:** Keine Fehlerleaks an das Frontend. Fehler stumm in die Server-Logs schreiben, dem Client nur generische Fehlercodes oder Statusmeldungen liefern.
- **Keine Modifikations-Sperren:** Alle Architekturebenen dürfen zur Erreichung von Code-Sauberkeit und Sicherheit refactored werden.