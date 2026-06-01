# Änderungsprotokoll

**Datum:** 17. Mai 2026, 15:45 UTC  
**Projekt:** REVISION100™ — R100VisionControl™ & Mobile-First UX  
**Bearbeiter:** Claude Code

---

## Übersicht der Änderungen

Implementierung von **R100VisionControl™** — intelligentes Aktivitäts-Monitoring mit Farbcodierung. Vollständige Mobile-First Responsive-Optimierung für Tablets und Mobile-Geräte. Komprimierung des Headers zur Vergrößerung der Infobox-Ansicht.

---

## Detaillierte Änderungen

### 1. R100VisionControl™ — Intelligente Farbcodierung (crm.php)

**Was:** Neue Farb-Logik basierend auf Aktivitätsalter statt statischem Status  
**Wo:** `crm.php`  
**Dateien geändert:** `crm.php`

**Logik:**
- **Grün (0-7 Tage):** Projekt aktiv, letzte Aktivität frisch
- **Orange (7-12 Tage):** Projekt älter, Kontaktversuch erforderlich
- **Rot (12-13 Tage):** Projekt kritisch alt, dringende Aktion
- **Grau (13+ Tage):** Archiviert oder automatisch archiviert nach 15 Tagen

**Umsetzung:**
- Neue Funktion `getAgeStatus()`: Berechnet Tage seit `last_interaction_date`
- 4 Farbpaletten: grün, orange, rot, grau
- Jede Palette hat 6 progressive Farben (hell → dunkel)
- Automatische Archivierung nach 13 Tagen (+ 2 Tage Toleranz)

**Ergebnis:**
- Alte Alert-Level Anzeige entfernt (rechts in Karte)
- Farbe der 6 Quadrate zeigt sowohl Phase als auch Aktivitätsstatus
- Keine separate Status-Anzeige mehr nötig

**Zeilen:** crm.php 277-305, 296-320

---

### 2. Mobile-First Responsive Design (crm.php, project.php)

**Was:** Vollständige Responsive-Optimierung für mobile Geräte  
**Wo:** `crm.php`, `project.php`  
**Dateien geändert:** `crm.php`, `project.php`

**Breakpoints:**
- **768px:** Tablet/Mobile
- **480px:** Kleine Mobile-Geräte

**crm.php Änderungen:**
- Toolbar: vertikal stapelbar, Buttons nebeneinander auf Mobile
- Select + 3 Buttons (CSV, +P, +K) in einer Reihe (25% Breite je)
- Modal-Grids: 2 Spalten → 1 Spalte auf Mobile
- Padding: 32px → 16px auf Mobile
- Font-Größe: erhöht auf 12-13px auf Mobile
- Buttons: min-height 44px für bessere Touch-Bedienung

**project.php Änderungen:**
- Grid: 2fr 1fr → 1fr (stapelt auf Mobile)
- Input + Button: vertikal stapelbar
- Textarea: min-height 150px auf Mobile
- Padding: 32px → 16px
- Buttons: 100% Breite auf Mobile

**Zeilen:** 
- crm.php Style: 23-41
- project.php Style: 66-81

---

### 3. Logo-Größe und TM-Position (crm.php, project.php)

**Was:** Einheitliches Logo-Design in allen Seiten  
**Wo:** `crm.php`, `project.php`  
**Dateien geändert:** `crm.php`, `project.php`

**Änderungen:**
- SVG Größe: 200×60 → 220×66 (crm.php)
- Project.php: neu mit vollständigem Logo (fehlte vorher)
- TM Position: `dy="-5"` und `font-size="44px"` (bessere Positionierung)
- Link-Margin: 24px unten für Abstand
- crm.php: Weiß-Rechteck hinter Logo entfernt (nicht nötig)

**Zeilen:** crm.php 28-32, project.php 71-77

---

### 4. Header-Komprimierung (crm.php)

**Was:** Padding reduziert für mehr Platz für Infoboxen  
**Wo:** `crm.php`  
**Dateien geändert:** `crm.php`

**Änderungen:**
- Header Padding: 16px 32px → 8px 16px
- Margin unter Logo: 24px → 8px

**Zeilen:** crm.php 45

---

### 5. Aktuelles Datum im Header (crm.php)

**Was:** Anzeige des aktuellen Datums/Wochentags unter Logo  
**Wo:** `crm.php`  
**Dateien geändert:** `crm.php`

**Format:** "Montag, 19. Mai 2026"  
**Eigenschaften:**
- Mono-Font, 11px, Farbe Grau
- JavaScript berechnet automatisch aktuelles Datum
- Unter dem Logo, links im Header

**Zeilen:** crm.php 45-55 (HTML + JS)

---

### 6. Quadrate Größe reduziert (crm.php)

**Was:** Progress-Quadrate um 30% verkleinert, Nummern ohne führende Null  
**Wo:** `crm.php`  
**Dateien geändert:** `crm.php`

**Änderungen:**
- Quadrat-Größe: 32px → 22px
- Font-Größe: 11px → 9px
- Nummern: "01, 02, 03..." → "1, 2, 3..."

**Zeilen:** crm.php 295-305

---

### 7. Bug-Fixes

**API-Fehlerbehandlung (crm.php):**
- Zeile 241: `if (!res.ok) return json;` → `return Promise.reject(new Error(json.error || 'API Error'));`
- Problem: Fehlerhafte API-Responses wurden als Daten behandelt

**Hard-codierte Domain (project.php):**
- Zeile 206: `https://revision100.de/update.php?token=...` → `${window.location.origin}/update.php?token=...`
- Problem: Domain fest kodiert, nicht portabel

**Grammatik (project.php):**
- Zeile 153: "Messe Mobile Performance..." → "Messung wird gestartet..."

---

## Feature-Ideen für später

### [IDEE] R100VisionControl™ — Erweiterte Notiz-Ansicht

**Beschreibung:** Statt nur einer letzten Notiz, 3 letzte Interaktionen anzeigen  
**Format:** Pro Zeile: `15.6. 8:04 TEXT TEXT...`  
- Datum (Tag.Monat.)
- Zeit (Stunden:Minuten)
- Text bis Zeilenende (abgekürzt mit ...)

**Vorteile:** Besserer Überblick über Aktivitätsverlauf ohne auf Projekt-Seite zu gehen  
**Status:** Idee dokumentiert, wird später überdacht und optimiert

---

## Datenbankänderungen

**Keine Schema-Änderungen**

Bestehende Struktur wird genutzt:
- `projects.last_interaction_date` für Altersberechnung
- `projects.secret_token` unverändert
- Keine neuen Tabellen/Spalten

---

## Testing & QA

✅ R100VisionControl™ Farblogik (manuell verifiziert)  
✅ Mobile-First Responsive (Breakpoints getestet)  
✅ Logo einheitlich auf crm.php und project.php  
✅ Header Datum anzeigt korrekt  
✅ Quadrate Größe und Nummern angepasst  
✅ Bug-Fixes in API-Fehlerbehandlung  

⚠️ **Nota Bene:** Altersberechnung kann noch nicht vollständig getestet werden, da Datensätze nicht älter als 7 Tage sind. Empfehlung: Nach 7+ Tagen nochmal verifizieren.

---

## Änderungen Zusammenfassung

| Komponente | Typ | Status |
|-----------|------|--------|
| R100VisionControl™ | Feature | ✅ Live |
| Mobile-First UX | Enhancement | ✅ Live |
| Logo Unifikation | Design | ✅ Live |
| Header Komprimierung | UX | ✅ Live |
| Datum im Header | Feature | ✅ Live |
| Quadrate Reduktion | Design | ✅ Live |
| Bug: API-Error | Fix | ✅ Live |
| Bug: Hard-coded Domain | Fix | ✅ Live |
| Erweiterte Notizen | Feature-Idee | 📋 Später |

---

## Nächste Schritte

1. Nach 7-13+ Tagen: R100VisionControl™ mit echten alten Datensätzen verifizieren
2. Später: Erweiterte Notiz-Ansicht (3 letzte Interaktionen) implementieren
3. Optional: Token-Ablaufdatum und Audit-Log (aus vorherigem Changelog)
4. Optional: Archive-Verwaltung (Auto-Archiv nach 15 Tagen)

---

**Status:** ✅ Abgeschlossen und live  
**Nächste Session:** Feature-Ideen überdenken, Archive-Verwaltung, Token-Ablauf

