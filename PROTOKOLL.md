# Protokoll: Kontaktverwaltung & Projektdaten-Editor

## Datum
Mai 2026

## Zusammenfassung
Implementierung eines umfassenden Kontaktmanagement-Systems mit harmonisierter Header-Struktur über alle Projektseiten. Vollständige Integration von Projektdaten-Bearbeitung, Kontaktpersonen-Verwaltung und Email-Versand mit Standard-Kontakt-Auswahl.

---

## 1. Datenbank-Schema

### Neue Tabelle: `project_contacts`
```sql
CREATE TABLE IF NOT EXISTS project_contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    email TEXT,
    phone_mobile TEXT,
    is_default INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(project_id) REFERENCES projects(id)
);
CREATE INDEX IF NOT EXISTS idx_project_contacts_project 
  ON project_contacts(project_id, is_default);
```

Status: ✅ Implementiert in `init_db.php`

---

## 2. API-Endpoints (`api.php`)

| Endpoint | Methode | Funktion | Status |
|----------|---------|----------|--------|
| `save_project_data` | POST | Projektfelder speichern | ✅ |
| `add_project_contact` | POST | Neue Kontaktperson | ✅ |
| `update_project_contact` | POST | Kontakt bearbeiten | ✅ |
| `delete_project_contact` | POST | Kontakt löschen | ✅ |
| `set_default_contact` | POST | Default-Kontakt setzen | ✅ |

---

## 3. Implementierte Features

### 3.1 Neue Seite: `edit_data.php`
- **Eingabe:** GET Parameter `id` (project_id)
- **Funktionalität:**
  - Projektdaten-Formular (customer_name, target_url, address, city, postal_code)
  - Google Places Autocomplete für Adresse mit PLZ-Extraktion
  - Kontaktpersonen-Liste mit Edit/Delete/Default-Funktionen
  - Neue Kontaktperson hinzufügen
  - Inline-Bearbeitung von Kontakten

**Implementierte Funktionen (JavaScript):**
- `saveProjectData()` — Projektdaten speichern
- `addContact()` — Neue Kontaktperson hinzufügen
- `editContact(contactId)` — Kontakt bearbeiten (Inline-Formular)
- `saveEditContact(contactId)` — Bearbeitete Kontaktdaten speichern
- `deleteContact(contactId)` — Kontakt löschen
- `setDefaultContact(contactId)` — Kontakt als Default setzen
- `initAutocomplete()` — Google Places Autocomplete
- `renderPhaseSquares()` — Visioncontrol-Quadrate (6 Status-Indikatoren)

Status: ✅ Vollständig implementiert

### 3.2 Header-Harmonisierung (alle 4 Seiten)
Alle Seiten verwenden konsistente 2-Spalten-Grid-Layout:
- **Linke Spalte:** Logo (220x66px) + Firma + Ansprechpartner + URL
- **Rechte Spalte:** Adresse + Email + Telefon (unten ausgerichtet)
- **Visioncontrol:** 6 Status-Quadrate (24x24px) unter Header

**Betroffene Seiten:**
- ✅ `project.php`
- ✅ `mail.php`
- ✅ `edit_data.php`
- ✅ `crm.php`

**CSS-Struktur:**
```css
.header-grid { 
  display: grid; 
  grid-template-columns: 1fr 1fr; 
  gap: 32px; 
  align-items: flex-start; 
}
.header-right-col { 
  display: flex; 
  flex-direction: column; 
  justify-content: flex-end; 
  align-self: flex-end; 
}
.status-square { 
  width: 24px; 
  height: 24px; 
  border-radius: 2px; 
  border: 1px solid #000;
}
```

### 3.3 Mail-Integration (`mail.php`)
- Standard-Kontakt wird automatisch beim Emailversand genutzt
- Fallback auf ursprüngliche Kundendaten, falls kein Standard-Kontakt definiert

### 3.4 Telefonnummern-Formatierung
- Automatische Konvertierung zu +49 International-Format
- Funktioniert in `edit_data.php` und `mail.php`
- Regex: Entfernt Sonderzeichen und konvertiert deutsche Formate

### 3.5 Google Places Autocomplete (`edit_data.php`)
- Deutsche Adressen nur (componentRestrictions: country: 'de')
- Automatische Extraktion von:
  - Stadt (locality/postal_town)
  - Postleitzahl (postal_code)
  - Formatted Address (mit ", Deutschland" Filterung)

---

## 4. Behobene Probleme

### Layout & Rendering
- ✅ 3-Spalten-Layout → 2-Spalten-Grid
- ✅ Header-Overflow/Rahmen-Probleme
- ✅ Page-Jumping (Scrollbar-Flackern) → `html { overflow-y: scroll; }`
- ✅ Status-Quadrate Größen-Inkonsistenz (mail.php größer)
  - Root Cause: Inline-Styles vs className
  - Fix: mail.php auf `createElement()` mit className umgestellt

### Zoom-Verhalten
- ✅ Zoom-Restrictions auf allen Seiten konsistent
- ✅ Viewport Meta-Tag: `user-scalable=no, maximum-scale=1`

### Daten-Handling
- ✅ Default-Kontakt wird korrekt geladen
- ✅ Fallback auf customers-Daten, falls keine Kontakte
- ✅ PLZ wird aus Google Places API extrahiert

---

## 5. Datei-Übersicht

| Datei | Änderungen | Status |
|-------|-----------|--------|
| `init_db.php` | `project_contacts` Tabelle + Index | ✅ |
| `api.php` | 5 neue Endpoints (~150 Zeilen) | ✅ |
| `edit_data.php` | Neue Seite (~540 Zeilen) | ✅ |
| `project.php` | "Data" Button + Header-CSS | ✅ |
| `mail.php` | Header harmonisiert + renderPhaseSquares fix | ✅ |
| `crm.php` | Header harmonisiert | ✅ |

---

## 6. Verifikations-Checkliste

### Datenbank
- [x] `project_contacts` Tabelle erstellt
- [x] Index für Performance vorhanden
- [x] Foreign Key zu projects gesetzt

### UI (`edit_data.php`)
- [x] Projektdaten-Form angezeigt
- [x] Kontakt-Liste mit Edit/Delete Buttons
- [x] Neue Kontaktperson hinzufügbar
- [x] Radio-Button für Default-Auswahl
- [x] Edit-Funktion: Inline-Formular mit Speichern/Abbrechen
- [x] Google Places Autocomplete funktioniert
- [x] PLZ-Extraktion funktioniert

### Integration
- [x] `project.php` hat "Data" Button
- [x] Header auf allen Seiten harmonisiert
- [x] Status-Quadrate konsistent groß
- [x] Default-Kontakt wird in `mail.php` genutzt
- [x] Telefonnummern im +49 Format

### Viewport/Zoom
- [x] Zoom deaktiviert (user-scalable=no, maximum-scale=1)
- [x] Konsistent auf allen 4 Seiten (project, mail, edit_data, crm)

---

## 7. Technische Details

### Phone-Number Formatting (JavaScript)
```javascript
function formatPhoneNumberJS(phone) {
  phone = phone.replace(/[^0-9+]/g, '');
  if (phone.startsWith('+')) return phone;
  if (phone.startsWith('0')) {
    phone = '+49' + phone.substring(1);
  } else if (phone.length === 11 && phone[0] === '1') {
    phone = '+49' + phone;
  } else if (phone.length === 10 && /^\d+$/.test(phone)) {
    phone = '+49' + phone;
  }
  return phone;
}
```

### Edit Contact Flow
1. User klickt "Edit" Button
2. `editContact(contactId)` wird aufgerufen
3. Aktuelle Daten aus DOM extrahiert
4. Inline-Bearbeitungsformular wird angezeigt
5. User bearbeitet und klickt "Speichern"
6. `saveEditContact()` sendet Update zu API
7. `update_project_contact` Endpoint aktualisiert Datenbank
8. Seite wird neu geladen

---

## 8. Abgeschlossene Aufgaben

- [x] Datenbank-Schema erweitern
- [x] API-Endpoints implementieren
- [x] Seite `edit_data.php` erstellen
- [x] Header-Struktur harmonisieren (4 Seiten)
- [x] Visioncontrol-Quadrate konsistent machen
- [x] Google Places Autocomplete integrieren
- [x] Telefonnummern-Formatierung
- [x] Kontakt Edit-Funktion implementieren
- [x] Zoom-Restrictions konsistent setzen
- [x] Layout-Overflow Probleme beheben

---

## 9. Notizen

- Auto-Create: Beim ersten Besuch von `edit_data.php` wird automatisch ein Default-Kontakt aus den customers-Daten erstellt (falls keine Kontakte existieren)
- All-in-One Seite: Edit-Funktion ersetzt Kontakt-Item inline mit Formular (keine Modal oder separate Seite)
- Fallback-Logik: Wenn kein Standard-Kontakt → nutze original customers Email
- CSS Grid Gaps: 32px zwischen Spalten, 2px zwischen Text-Zeilen (optimiert für 12px Font)

---

**Status:** ✅ ABGESCHLOSSEN

