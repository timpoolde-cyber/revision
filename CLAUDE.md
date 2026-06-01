# REVISION100™ // SYSTEM DIRECTIVE & DEPLOYMENT MANIFEST // VERSION 1.3

Du operierst lokal im Kern-System von Revision100™ auf dem MacBook Air des Entwicklers (`/Users/timpoolair/revision100`). Jede Modifikation muss sich bedingungslos an das funktionale, visuelle und logische Fundament dieses Manifests halten.

## 1. INTEGRALE ENTWICKLUNGS- & CODE-REGELN (DIKTAT)
* **Keine Code-Kürzungen (Lazy AI Ban):** Es ist strengstens untersagt, funktionierenden Code, HTML-Strukturen, SVGs, CSS-Regeln oder Validierungen durch Platzhalter (wie `// ... restlicher Code bleibt gleich ...`, `// [Hier CSS einfügen]`) zu ersetzen. Dateien müssen immer VOLLSTÄNDIG ausgegeben werden.
* **Keine Blockade-Fragen:** Führe beauftragte Änderungen an Dateien direkt, autonom und vollständig aus. Warte nicht auf Bestätigungen für Routine-Eingriffe.
* **Divergenzfreie Pfad-Architektur:** Absolute Pfade (wie `/Users/...`) sind im ausführbaren PHP-Code strikt verboten. Alle Dateipfade zur SQLite-Datenbank (`rockets.db`) müssen dynamisch über `dirname(__DIR__)` oder `__DIR__` relativ berechnet werden, damit die Codebasis ohne manuelle Modifikationen im Live-Produktivmodus bei one.com und lokal identisch operiert.
* **SQL-Mandat (`/data/rockets.db`):** Die Passwort-Spalte in der SQLite-Tabelle `users` heißt zwingend `password_hash`. Nutze bei Authentifizierungs-Abfragen (z.B. in `index.php` oder `crm.php`) immer den Spalten-Alias `SELECT password_hash AS password`, um die Kompatibilität mit PHP `password_verify` zu wahren.
* **Routing-Mandat:** Formular-Actions müssen auf `$_SERVER['REQUEST_URI']` laufen, damit die dynamischen SEO-Slugs bei POST-Requests nicht auf die Root-URL zurückgeworfen werden.
* **Namens-Harmonisierung:** Das System nutzt durchgehend die englische Schreibweise für Projekt-Dateien. Die zentrale Datei heißt `project.php`, das dazugehörige Template im Ordner `views/` zwingend `project.tpl.php` (mit "c"). Die deutsche Schreibweise "projekt" ist im gesamten Dateisystem verboten.

## 2. GOOGLE MAPS API STANDARD (AB MÄRZ 2025)
* **Asynchroner Lade-Zwang:** Das direkte, synchrone Laden der Google Maps API im `<head>` ist verboten. 
* **Lade-Muster:** Die API darf ausschließlich asynchron und verzögert geladen werden. Das entsprechende `<script>`-Tag gehört an das absolute Ende der Datei direkt vor den schließenden `</body>`-Tag und muss zwingend die Parameter `loading=async` und `defer` enthalten:
  `<script src="https://maps.googleapis.com/maps/api/js?key=<?= $googleMapsKey ?>&libraries=places&loading=async" defer></script>`
* **Autocomplete-Hülle:** Für Adress-Eingabefelder darf nicht mehr das veraltete JavaScript-Objekt (`new google.maps.places.Autocomplete`) initialisiert werden. Es ist die native HTML Web Component Hülle `<gmpx-place-autocomplete>` um das `<input>`-Feld herum zu verwenden.

## 3. CRM-STRUKTUR & RECHNUNGS-ZENTRALE (INV)
* **ActiveControl Navigationsleiste:** Die Steuerung zwischen den CRM-Teilen erfolgt über die zentrale `nav.tpl.php`. Die Tabs sind starr definiert als: `Projekt` (crm.php), `History` (project.php), `Data` (edit_data.php), `Mail` (mail.php) und `INV` (invoice.php).
* **Projektwert-Sichtbarkeit:** Der in der Rechnungs-Zentrale (`invoice.php`) eingegebene Projektwert wird in der Tabelle `projects` in der Spalte `budget` gesichert. Auf der Startseite (`crm.php`) muss dieser Wert innerhalb der JavaScript-Funktion `renderCard(l)` als formatierte Währung direkt unter dem Performance-Score ausgegeben werden.

## 4. DIE VISUELLE BIBEL (STRIKTER HfG-ULM STANDARD)
* **Logo-Marke:** Reiner Text-Schriftzug "Revision100™". Keine Boxen, keine Rahmen, kein Suffix. 
* **Typografie-Split:**
  * **System-Rahmen:** (Logo, Anzeigen, Indikatoren, Tabellen-Messwerte, VisionControl-Balken) -> Strikter Monospace-Zwang (`font-family: ui-monospace, monospace !important;`).
  * **Inhalts-Bereich:** (Fließtexte, Formular-Labels, Inputs) -> Neutrale, hochgradig lesbare Serifenlose (`font-family: -apple-system, BlinkMacSystemFont, Arial, sans-serif !important;`).
* **Hintergrund-Welten:**
  * **Öffentlich (index.php):** Strikter Dunkelmodus (`background: #111111 !important; color: #ffffff !important;`).
  * **Intern (Werkbänke & Portal):** Hellmodus (`background: #fff !important;` innerhalb der Page-Wrapper, grauer Body `#f0f0f0`).
* **Der Trenn-Strich:** `<header>` wird nach unten durch eine scharfe Linie abgetrennt (`1px solid #000000 !important;`).
* **Symmetrie-Polsterung (Desktop):** `padding: 45px 32px 35px 32px !important; margin-bottom: 40px !important;` (Verhindert Aufsitzen der Buchstaben auf der Trennlinie).

## 5. INFRASTRUKTUR-, LOGIK- & STATUS-SCHUTZ
* **DOM-Infrastruktur:** Die JavaScript-IDs `statusLed`, `statusSquares`, `lhLed` und `tokenLed` sind funktionale Infrastruktur. Sie dürfen niemals gelöscht, umbenannt oder im DOM verschoben werden.
* **Alterungs-Logik (VisionControl):** Die Funktion `renderPhaseSquares()` und das zugehörige Farb-Array steuern die mathematische Alterungs-Berechnung der Projekte (Days ≥ 13 → gray, ≥ 12 → red, ≥ 7 → orange, sonst green). Diese Logik muss auf allen internen Werkbänken identisch bleiben.
* **Verbotene Begriffe:** Der Begriff "Maschinenraum" bleibt im gesamten System gelöscht und durch "System", "Infobox" oder "Data" ersetzt.
* **Sicherer Git-Airbag:** Claude darf NIEMALS eigenständig `git commit` ausführen. Nach erfolgreichem Abschluss generiert Claude im Chat den exakten, einzeiligen Terminal-Befehl für den Entwickler zur manuellen Ausführung.