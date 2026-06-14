# CLAUDE.md — R400™ CRM · Projektkontext & Leitplanken

Diese Datei ist **dauerhafter Kontext + Verhaltensregeln**. Die konkrete Aufgabe steht immer im
jeweiligen Prompt. Diese Datei gibt nie selbst Aufträge — sie setzt nur Rahmen und Fakten.

---

## 0. VERHALTEN (wichtigster Teil)
- **Nur das tun, was im aktuellen Prompt steht.** Keine ungefragten Änderungen, kein
  „bei der Gelegenheit"-Refactoring, keine eigenmächtigen Verbesserungen außerhalb des Auftrags.
- Fällt dir außerhalb des Auftrags etwas Kaputtes auf: **benennen, nicht beheben.** Als Notiz ans Ende,
  nicht als stillen Eingriff.
- **Bei Unklarheit fragen**, nicht raten. Eine Rückfrage ist billiger als ein falscher Umbau.
- Diffs minimal und chirurgisch halten. Bestehende Handler/Funktionen ergänzen, nicht ersetzen,
  außer der Auftrag verlangt es ausdrücklich.
- Lange Aufträge in der vorgegebenen Reihenfolge abarbeiten und dort stoppen, wo der Prompt es sagt.
  Nicht vorauseilen.

## 1. HARTE TABUS (nur ändern, wenn der Prompt es EXPLIZIT verlangt)
- `session_handler.php`, `auth.php`, `check_auth()`, Login- und Stealth-Logik.
- Die Audit-Logik in `flow/vip/worker_psi.php`.
- Die Cockpit-Komponente: `r400_status.php`, `r400-status.css`, `r400-status.js` (kanonisch — nur
  austauschen, wenn ausdrücklich gesagt).
- DB-Schema: nur die im Prompt genannten Migrationen, sonst nichts.
- Bestehende PSI-Fetch- und Token-Send-Handler: nur ergänzen, nie entfernen.

## 2. STACK & UMGEBUNG
- PHP + SQLite (`data/rockets.db`), **kein Composer**, Shared Hosting (One.com).
- API-Calls per rohem `curl` (kein SDK). PHPMailer liegt lokal bei.
- Aufbau: Controller im Root (`crm.php`, `project.php`, `mail.php`, `invoice.php`, `edit_data.php`),
  Templates in `views/`. Globaler Header der Innenseiten: `views/header.tpl.php` (rendert das Cockpit).
- `crm.php` rendert die Lead-Liste **per JS** (`crm-functions.js`); Innenseiten rendern **per PHP**.
- Secrets liegen in `.env`; sensible Endungen (`.env`, `.db`, `.log`, `.md`) sind via `.htaccess`
  geschützt. Das ist Bestand, kein Auftrag.

## 3. DESIGN
- CRM-Innenseiten: monochrom schwarz/weiß, Monospace. Kundenportal (`flow/vip/`): dunkel, Monospace.
- **Cockpit-Zustände (aktuell, vier + blink):**
  `grau` #bdbdbd = offen · `schwarz` #111 = läuft · `grün` #16c784 = erledigt/positiv ·
  `rot` #e5544b = Handlungsbedarf · `faellig` = rot + blinkt (Anruf fällig).
  Das alte Neon `#00FF66` und das Zwei-Zustands-Schema sind **überholt** — nicht mehr verwenden.
- HfG-Minimalismus: Funktion vor Dekoration, kein Marketing-Geschwurbel. Texte knapp, Du-Form,
  im Portal kleingeschrieben.

## 4. ARCHITEKTUR (Kurzfassung, damit Kontext nicht verloren geht)
- **Drei Eingänge, eine Strecke.** Lead (Web-Formular), VIP (Karte → `flow/vip/`), Maps (manuell im +P)
  laufen auf dieselbe 5-stufige Meilenstein-Strecke: **in · quick · psi · anruf · faktura**.
- Der Kanal (`lead`/`maps`/`vip`) hängt am **Projekt** (`projects.channel`), nicht am Kunden.
- **Token vs. Kurz-URL:** Der lange `secret_token` ist der interne Geheimschlüssel (Portal-Auth).
  Eine Kurz-URL ist die öffentliche Hülle — gebraucht **nur für den Maps-Brief** (gedruckt). VIP/Lead
  nutzen den langen Token in digitalen Links.
- **VIP asynchron:** Das Formular legt nur an (`tunnel='anfrage'`) + bestätigt; den Audit macht
  `worker_psi.php` per Cron (`anfrage` → `bewertet`). Benachrichtigungen über `cron_sms_r400.php`
  (SMS bevorzugt, Mail als Fallback).
- **Ein Stopp**, kein Mute: optout → `tunnel='abgeschaltet'` beendet den Prozess (kein Interesse,
  später reaktivierbar). `cron_sms` überspringt `abgeschaltet`.

## 5. CODE-KONVENTIONEN
- PDO ausschließlich mit Prepared Statements.
- **Keine Fehlerleaks ans Frontend:** Exceptions/Stacktraces in `error_log()`, dem Client nur
  generische Statusmeldungen.
- Keine Redundanz: gemeinsame Funktionen einmal in `functions.php`.
- Null-sichere Defaults (`?? ''`, `?? 'lead'` etc.), keine PHP-Notices.