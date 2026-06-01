```markdown
# REVISION100™ // SYSTEM DIRECTIVE & DEPLOYMENT MANIFEST // STAND NULL

Du operierst im Kern-System von Revision100™. Jede Code-Modifikation muss sich bedingungslos an das funktionale, visuelle und logische Fundament dieses Manifests halten. Eigenmächtige Änderungen außerhalb des expliziten Arbeitsauftrags sind strikt untersagt.

## 1. DIE VISUELLE BIBEL (STRIKTER HfG-ULM STANDARD)
* **Logo-Marke:** Reiner Text-Schriftzug "Revision100™". Das "™" wird im CSS separat via span.tm-size verkleinert und hochgestellt (`font-size: 14px !important; vertical-align: super !important;`). Keine Boxen, keine Rahmen, kein Suffix, kein Begriff "Maschinenraum".
* **Typografie-Split (Zielgruppen-Lesbarkeit):**
  * **System-Rahmen:** (Logo, Anzeigen, Indikatoren, Tabellen-Messwerte) -> Strikter Monospace-Zwang:
    `font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;`
  * **Inhalts-Bereich:** (Fließtexte, Erklärungen, Formular-Labels, Inputs, Textareas) -> Hochgradig lesbare, neutrale Serifenlose zur Gewährleistung der Lesegeschwindigkeit für die kaufmännische Zielgruppe:
    `font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;`
* **Hintergrund-Welten:**
  * **Öffentlich (index.php):** Strikter Dunkelmodus (`background: #111111 !important; color: #ffffff !important;`). Keine Hintergrund-Raster, Dot-Patterns oder Grids.
  * **Intern (Werkbänke & Kundenportal):** Hellmodus (`background: #fff !important;` innerhalb der Page-Wrapper, grauer Body `#f0f0f0`). Keine Raster.
* **Der Trenn-Strich (border-bottom):** Jede Benutzeroberfläche besitzt einen `<header>`, der nach unten durch eine scharfe Linie abgetrennt ist.
  * Im Hellmodus: `1px solid #000000 !important;`
  * Im Dunkelmodus: `1px solid #333333 !important;`
* **Symmetrie-Polsterung (Desktop):** `padding: 45px 32px 35px 32px !important; margin-bottom: 40px !important;`
  (Exakt 45px Luft oben, 35px Polster unten, damit die Buchstaben-Unterkanten niemals auf der Trennlinie aufsitzen, und 40px Abstand zum Inhalt, um Ruckeln beim Seitenwechsel zu eliminieren).
* **Mobile Skalierung & Symmetrie (max-width: 768px):** `header { padding: 25px 16px 19px 16px !important; margin-bottom: 22px !important; } .brand-name { font-size: 24px !important; }`

## 2. SYSTEM-ARCHITEKTUR & FIXE HEADER-HTML-STRUKTUREN

### A) Öffentlicher Bereich (index.php) - DUNKELMODUS MIT STATUS-INDIKATOR UNTEN
```html
<header style="background: #111111 !important; border-bottom: 1px solid #333333 !important; padding: 45px 32px 35px 32px !important; margin-bottom: 40px !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 8px !important;">
    <div class="brand-wrapper" style="display: flex; align-items: center; gap: 0;">
        <span class="brand-name" style="font-family: ui-monospace, SFMono-Regular, monospace !important; color: #ffffff !important; font-size: 32px !important; font-weight: 700 !important; letter-spacing: -1px !important; line-height: 1.0 !important;">Revision100<span class="tm-size" style="font-size: 14px !important; vertical-align: super !important; font-weight: 400 !important;">™</span></span>
    </div>
    <div class="header-claim-container" style="display: flex; align-items: center; gap: 12px; margin-top: 4px;">
        <div class="status-sub-line" style="display: flex; align-items: center; gap: 10px;">
            <div class="status-led-audit" style="width: 20px !important; height: 6px !important; background-color: #00ff66 !important; border-radius: 0px !important; box-shadow: 0 0 12px #00ff66 !important;"></div>
            <span class="status-text-audit" style="font-family: ui-monospace, monospace !important; font-size: 11px !important; color: #ffffff !important; opacity: 0.4 !important; text-transform: uppercase !important; letter-spacing: 1px !important;">System bereit für Code Audit</span>
        </div>
        <div class="header-claim" style="color: #aaaaaa !important; font-size: 14px !important; font-family: ui-monospace, monospace !important;">
            // Quelltext-Sanierung bei <?php echo htmlspecialchars($dynamic_keyword); ?>
        </div>
    </div>
</header>

```

### B) Kundenportal (update.php) - HELLMODUS

```html
<header>
    <div class="brand" style="display: flex; align-items: center; gap: 16px;">
        <span class="brand-name">Revision100™</span>
        <span id="statusLed" class="status-led saved"></span>
    </div>
    <div class="header-claim">Kundenportal // Stammdaten-Aktualisierung</div>
</header>

```

### C) Interne Werkbänke (crm.php, project.php, edit_data.php, mail.php, pdf.php, user_management.php) - HELLMODUS

```html
<header>
    <div class="brand" style="display: flex; align-items: center; gap: 16px;">
        <span class="brand-name">Revision100™</span>
        <span id="statusLed" class="status-led"></span>
    </div>
    <div class="header-claim">Interne Werkbank // System-Zentrale</div>
    <div id="statusSquares" style="display: flex; gap: 4px; margin-top: 12px; height: 12px;"></div>
</header>

```

## 3. INFRASTRUKTUR-, LOGIK- & STATUS-SCHUTZ (UNBERÜHRBAR)

* **Passwort-Schutz:** Es ist untersagt, SQL-Befehle oder Seeding-Routinen auszuführen, die bestehende Admin-Passwörter automatisch auf Standardwerte überschreiben. Passwort-Hashes in der Tabelle `users` dürfen nur manuell verändert werden.
* **DOM-Infrastruktur:** Die JavaScript-IDs `statusLed`, `statusSquares` (Phasen-Progression), `lhLed` (Lighthouse-Button) und `tokenLed` (Token-Generierung) sind funktionale Infrastruktur. Sie dürfen bei HTML- oder CSS-Eingriffen niemals gelöscht, umbenannt oder im DOM verschoben werden.
* **Status-Quadrate & Alterungs-Logik:** Die Funktion `renderPhaseSquares()` und die zugehörigen Farb-Arrays (`colorPalettes` mit `green`, `orange`, `red` und `gray`) steuern die mathematische Alterungs-Berechnung und visuelle Verschiebung des Farbtons je nach Projekt-Alterung (Days ≥ 13 → gray, ≥ 12 → red, ≥ 7 → orange, sonst green). Diese Logik muss auf allen internen Unterseiten identisch zur Hauptmatrix in `crm.php` gehalten werden.
* **Verbotene Begriffe (Zielgruppen-Ansprache):** Der Begriff "Maschinenraum" ist im gesamten System gelöscht und durch "System" oder "Infobox" ersetzt. Zielgruppenrelevante Claims wie "Quelltext-Sanierung bei Google-Rankingverlust" und "Bereit für Code-Audit" sind fixiert.

## 4. VERPFLICHTENDE KI-ARBEITSREGELN (KONTROLL-PROZEDUR)

Du musst vor jeder Code-Generierung folgende drei Schritte einhalten:

1. **Analysestufe:** Nenne vor der Code-Änderung die IDs und CSS-Klassen der Ziel-Datei, die laut diesem Manifest absolut unberührt bleiben müssen. Warte auf die Bestätigung des Nutzers.
2. **Isolations-Prinzip:** Bearbeite niemals mehrere Dateien in einem Durchgang. Fokussiere dich ausschließlich auf die zugewiesene Datei.
3. **Diff-Zwang:** Erstelle keine vollständigen neuen Code-Blöcke für kleine Änderungen. Gib ausschließlich den exakten Zeilen-Ausschnitt (Code-Diff) aus, der ersetzt werden muss.

```