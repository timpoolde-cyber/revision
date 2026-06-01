# REVISION100 Logo — Platzierungsmöglichkeiten

## 📋 Liste aller Seiten mit Logo-Integration

### ✅ Seiten MIT Header & Logo (Primär)

| Seite | Datei | Aktueller Text | Status | Notizen |
|-------|-------|----------------|--------|---------|
| **Startseite** | `index.php` | `REVISION 100` | ✓ Header vorhanden | Logo in `<header>` mit Klasse `.logo`. Mit Border-Box-Styling |
| **CRM Dashboard** | `crm.php` | `Revision100™` | ✓ Header vorhanden | Logo in `<header>` mit Klasse `.logo`. Auch branding im Body (`inv-logo` auf Zeilen 195, 288) |
| **Einstellungen** | `settings.php` | `Revision100` | ✓ Header vorhanden | Logo in `<header>` mit Klasse `.logo`. Standard-Header-Setup |
| **Lead-Management** | `lead.php` | `Revision100™` | ✓ Logo vorhanden | Logo als `<div class="logo">`. Separate Darstellung |

---

### ⚠️ Seiten OHNE Header (Sekundär)

| Seite | Datei | Notizen |
|-------|-------|---------|
| **Login** | `login.php` | Nur Text `<h1>REVISION 100</h1>` in Formular. Minimales Design, kein Header |
| **Projekt-Detail** | `project.php` | Keine Header-Struktur. Customer-Daten-fokussiert. Nur Close-Button (✕) oben |
| **Phasen** | `phases.html` | Fragment/Komponente (in index.php eingebunden). Keine eigenständige HTML |

---

## 🎯 Empfohlene Logo-Positionen

### **1. Header (Primär)**
```html
<header>
  <div class="header-inner">
    <a href="/" class="logo">
      <!-- LOGO-SVG HIER -->
      REVISION100™
    </a>
    <nav>...</nav>
  </div>
</header>
```
**Betroffene Dateien:** 
- `index.php` (Zeile 142)
- `crm.php` (Zeile 25)
- `settings.php` (Zeile 351)
- `lead.php` (alternative Darstellung, Zeile 153)

---

### **2. Branding-Blöcke (CRM)**
In `crm.php` gibt es zusätzliche Logo-Bereiche:
- **Zeile 195:** `<div class="inv-logo">Revision100™</div>` (Branding im Hauptbereich)
- **Zeile 288:** `<div class="inv-logo">Revision100™</div>` (wiederholtes Branding)

Diese können als vollständiges Logo-Element gestaltet werden.

---

### **3. Login-Seite (Optional)**
`login.php` Zeile 32: `<h1>REVISION 100</h1>` 
Könnte mit einem kleineren Logo versehen werden, aber minimales Design aktuell bewusst gewählt.

---

### **4. Projekt-Seite (Zurück-Link)**
`project.php` hat kein dediziertes Logo-Bereich. Nur ein Zurück-Link (✕) zu crm.php.
**Empfehlung:** Optional ein Branding-Element oben hinzufügen.

---

## 📊 Zusammenfassung: Platzierungen nach Priorität

| Priorität | Ort | Dateien | Zweck |
|-----------|-----|---------|--------|
| 🔴 **Hoch** | Header `.logo` | `index.php`, `crm.php`, `settings.php` | Navigation & Branding |
| 🟡 **Mittel** | Lead-Page Logo | `lead.php` | Separate Darstellung |
| 🟡 **Mittel** | CRM `.inv-logo` | `crm.php` (2× vorhanden) | Branding-Blöcke |
| 🟢 **Niedrig** | Login-Seite | `login.php` | Optional |
| 🟢 **Niedrig** | Projekt-Detail | `project.php` | Optional |

---

## 🔧 Implementierungs-Anleitung

Das Logo kann folgende Formate haben:
- ✓ **SVG** (empfohlen) — scalable, responsive
- ✓ **PNG/JPG** (als Fallback) — alt. Formate
- ✓ **HTML-Text** (aktuell) — kann zu SVG/Bild gewechselt werden

**Nächste Schritte:**
1. SVG-Logo in Projektordner ablegen (z.B. `/assets/logo.svg`)
2. HTML-Dateien entsprechend updaten (Text durch `<img>` oder inline SVG ersetzen)
3. CSS-Klasse `.logo` anpassen ggf. (Größe, Padding, Border)
