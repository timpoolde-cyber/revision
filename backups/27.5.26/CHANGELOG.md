# Änderungsprotokoll

**Datum:** 16. Mai 2026, 15:30 UTC  
**Projekt:** REVISION100™ — Client Update & CRM Modul  
**Bearbeiter:** Claude Code

---

## Übersicht der Änderungen

Implementierung von Status-Indikator für Formular-Speicherung und Token-Verwaltungsfunktionalität in Admin-Ansicht (project.php). Entfernung redundanter Token-Funktionen aus Kundenansicht (update.php).

---

## Detaillierte Änderungen

### 1. Status-Indikator für Formularspeicherung (update.php)

**Was:** Visueller Indikator (rot/grün) für Speicherstatus des Kundenformulars  
**Wo:** `update.php`  
**Dateien geändert:** `update.php`

**Änderungen:**
- CSS-Styles hinzugefügt für `.save-status` und `.status-dot` (grün = gesichert, rot = ungesichert)
- HTML-Element hinzugefügt über "Stammdaten verifizieren" Formularsektion
- JavaScript-Logik: 
  - Bei Page-Load: grüner Punkt (Daten vom Server)
  - Bei Input-Änderung: roter Punkt (ungesichert)
  - Bei Form-Submit: grüner Punkt (Speicherung)
- Event-Listener auf alle Eingabefelder (text, email, tel)

**Zeilen:** CSS 196-219, HTML 325-328, JS 374-385

---

### 2. Token-Erneuerungsfunktion (api.php)

**Was:** Neue API-Aktion zum Erneuern eines Kunden-Tokens  
**Wo:** `api.php`  
**Dateien geändert:** `api.php`

**Änderungen:**
- Neue POST-Action `renew_token` implementiert
- Logik:
  1. Nimmt aktuellen Token als Parameter
  2. Validiert Token gegen Datenbankeinträge
  3. Generiert neuen Token mit `generate_secret_token()`
  4. Überschreibt alten Token in `customers.secret_token`
  5. Gibt neuen Token zurück
- Fehlerbehandlung für ungültige/fehlende Tokens

**Zeilen:** 114-152

**Verhalten:**
- Alter Token wird sofort ungültig (überschrieben)
- Pro Kunde nur ein aktiver Token
- Keine Ablaufdaten (unbegrenzte Gültigkeit)

---

### 3. Token-Verwaltung in Admin-Ansicht (project.php)

**Was:** UI und Funktionalität zum Token-Erneuern in Admin-Projekt-Ansicht  
**Wo:** `project.php`  
**Dateien geändert:** `project.php`

**Änderungen:**

**CSS (Zeilen 52-53):**
- `.input-with-button`: Flexbox-Layout für Input + Button nebeneinander
- `.btn-square`: Quadratischer Button (48×48px) mit Reload-Icon

**HTML (Zeilen 111-124):**
- "Kunden-Token" Box mit Input-Feld und Renew-Button
- Button mit SVG Reload-Icon
- Benachrichtigungselement für "Kopiert!" Status

**JavaScript (Zeilen 189-220):**
- Event-Listener auf Renew-Button
- Extrahiert aktuellen Token aus URL
- Ruft `renew_token` API auf
- Aktualisiert Token-URL-Feld mit neuer URL
- Kopiert neue URL automatisch in Zwischenablage
- Zeigt "Kopiert!" Benachrichtigung für 1,5 Sekunden an

---

### 4. Bereinigung Kundenansicht (update.php)

**Was:** Entfernung von Token-Verwaltungsfunktionen aus Kundenformular  
**Wo:** `update.php`  
**Dateien geändert:** `update.php`

**Änderungen:**
- Token-Anzeige-Feld entfernt (zurück zu `type="hidden"`)
- Renew-Button neben Adress-Feld entfernt
- CSS für `.input-with-button` und `.btn-square` entfernt
- Token-Erneuerungs-JavaScript komplett entfernt
- Status-Indikator bleibt (für Formularspeicherung)

**Begründung:** Token-Verwaltung gehört in Admin-Ansicht (project.php), nicht in Kundenansicht

---

### 5. URL-Struktur (Token-Distribution)

**Was:** Direkter Link zu update.php statt über Popup-Opener  
**Wo:** `project.php`  
**Dateien geändert:** `project.php`

**Änderung:**
- Token-URL geändert von `https://revision100.de/opener.php?token=...` zu `https://revision100.de/update.php?token=...`
- JavaScript aktualisiert (Zeile 206)
- `opener.php` wird nicht mehr verwendet (bleibt aber für Rückwärtskompatibilität)

**Begründung:** Popups werden von modernen Browsern blockiert. Direkter Link ist zuverlässiger.

---

## Probleme & Lösungen

### Problem 1: Benachrichtigung "Kopiert!" nicht sichtbar
**Symptom:** Nach Token-Erneuerung wurde "Kopiert!" nicht angezeigt  
**Ursache:** CSS `position: absolute; top: 52px;` platzierte Element außerhalb des sichtbaren Bereichs  
**Lösung:** Position auf `top: -22px` geändert (direkt über dem Eingabefeld)  
**Zeile:** 124

### Problem 2: Popup-Blockierung durch opener.php
**Symptom:** `window.open()` wurde vom Browser blockiert  
**Ursache:** Popups sind standardmäßig blockiert, außer bei direktem User-Klick  
**Lösung:** Direkter Link zu update.php ohne Popup-Umweg  
**Betroffene Datei:** project.php

### Problem 3: Token-Management-Duplikation
**Symptom:** Token-Verwaltung in zwei Ansichten (update.php + project.php)  
**Ursache:** Unklare Zuständigkeit  
**Lösung:** Tokens nur in Admin-Ansicht (project.php) verwalten, Kundenansicht nur konsumieren  
**Dateien:** update.php bereinigt, project.php erweitert

---

## Datenbankänderungen

**Keine direkten Schema-Änderungen**

Die bestehende Struktur wird genutzt:
- `customers.secret_token` — wird überschrieben bei Erneuerung
- Keine neuen Spalten oder Tabellen erforderlich

---

## Getestete Funktionalität

✅ Status-Indikator (rot/grün) bei Formulareingaben  
✅ Token-Erneuerung in project.php  
✅ "Kopiert!" Benachrichtigung anzeigen  
✅ Neue Token-URL in Zwischenablage kopieren  
✅ Token-URL öffnet korrekt update.php  
✅ Update.php Formular mit erneuerten Token funktioniert  

---

## Dateien betroffen

| Datei | Änderungen | Zeilen |
|-------|-----------|--------|
| `api.php` | Neue `renew_token` Aktion | 114-152 |
| `update.php` | Status-Indikator, Token-Cleanup | 196-219, 325-328, 374-385 |
| `project.php` | Token-Verwaltung UI & JS | 52-53, 111-124, 189-220 |

---

## Notizen für zukünftige Entwicklung

1. **opener.php** kann irgendwann archiviert/gelöscht werden (wird nicht mehr benutzt)
2. **Token-Ablaufdatum:** Aktuell keine Gültigkeitsdauer. Falls später implementiert, müsste in `customers` Tabelle `token_expires_at` hinzugefügt werden
3. **Token-Audit-Log:** Optional: Speichern, wann Tokens erneuert wurden (für Sicherheit)
4. **Mobile-Browser:** Status-Indikator und Benachrichtigungen sollten auf mobilen Geräten getestet werden

---

**Status:** ✅ Abgeschlossen und getestet  
**Nächste Schritte:** Optional weitere Verbesserungen (Token-Audit, Ablaufdaten, etc.)
