# REVISION100™ // SYSTEM-WORKFLOWS & ARCHITEKTUR

Dieses Dokument ist die Single Source of Truth für alle Akquisitions- und Datenströme. Aktualisierungen bei Änderungen der Logik sind zwingend hier zu dokumentieren.

---

## 1. WORKFLOW: "VIP" (Visitenkarte / Exklusive Kontakte)
- **Einstieg:** Scan des QR-Codes auf der Visitenkarte -> führt zu `r400.de/vip` (Unterverzeichnis `/VIP/index.php`).
- **Branding-Bezug:** r400 repräsentiert das mathematische Ziel von 4x100 Punkten im Lighthouse-Audit (Tempo, Sichtbar, KI-lesbar, Struktur).
- **Tonalität:** Striktes, persönliches "Du".
- **Logik & Latenz:**
  1. Nutzer gibt URL ein -> Daten gehen per POST an `?action=check`.
  2. Frontend simuliert eine künstliche Latenz (Qualität braucht Zeit).
  3. Parallel läuft die PageSpeed- & Anthropic-Analyse im Hintergrund.
  4. Datensatz wird in `leads.sqlite` mit Status `pending` angelegt.
- **Daten-Anreicherung (Elias-Szenario):**
  - Gibt der Nutzer *nur* die URL ein, sucht das System im Hintergrund nach Impressumsdaten/GF-Namen.
  - Manuelle Vervollständigung des Kontakts im CRM-Backend durch Timo.
- **Output & Token:**
  - Bei Angabe einer Mobilnummer: Automatischer SMS-Versand eines individualisierten Direkt-Links via Gateway: `https://r400.de/vip/?token=XYZ123`.
  - Der Link (Token) authentifiziert den Nutzer dauerhaft auf seiner persönlichen Seite, zeigt den Befund und den direkten Button zur Beauftragung der Revision.

---

## 2. WORKFLOW: "LEAD" (Inbound / Haupt-Website)
- **Einstieg:** Organischer Traffic / Direktaufrufe auf `revision100.de` (bzw. `/R100-CRM/index.php`).
- **Tonalität:** Formales, professionelles "Sie".
- **Logik:**
  1. Nutzer fordert kostenfreie Analyse über das Hauptformular an.
  2. Direktes Schreiben des Leads in `leads.sqlite`.
  3. Sofortiger E-Mail-Versand der Formulardaten via PHPMailer an das interne System.
- **Instandhaltung:** 
  - Server-Side A/B-Testing für Headlines ist hier aktiv (50:50 Split via PHP-Session).

---

## 3. WORKFLOW: "MAPS" (Outbound / Postkarte & Kaltakquise)
- **Einstieg:** Physisches Anschreiben/Postkarte mit vorab berechneten Lighthouse-Scores an ausgewählte Unternehmen im Zielgebiet.
- **Tonalität:** Formales "Sie", direkt an die Geschäftsführung gerichtet.
- **Logik & Live-Tracking:**
  1. Lokales CLI-Skript auf dem M1 Mac testet vorab 10–20 Adressen und wirft CSV aus.
  2. Postkarte enthält einen extrem kurzen, individualisierten Tracking-QR-Code: `https://r400.de/vip/?campaign=maps_lued_01&id=14`.
  3. Scan des QR-Codes triggert unbemerkt einen stillen Update-Query in der Datenbank (`last_seen = TIMESTAMP`).
  4. System sendet sofort Alarm-Mail an Timo: "GF von Firma X prüft gerade das Angebot."

---

## TO-DO LISTE // NÄCHSTE SYSTEM-INTEGRATIONEN

### Für VIP-Workflow:
- [ ] Einbau des "Über mich"-Buttons mit Link auf `https://timpool.de`.
- [ ] Erstellung der Datenbank-Erweiterung für `token`, `status`, `gf_name` und `analysis_json`.
- [ ] SMS-Gateway (Twilio/Sipgate) API-Anbindung in PHP implementieren.

### Für Maps-Workflow:
- [ ] CLI-Scraper für den M1 Mac schreiben.
- [ ] Tracking-Detektion für `$_GET['campaign']` in der index.php verbauen.