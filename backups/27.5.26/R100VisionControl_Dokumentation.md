# R100VisionControl™

## Intelligentes Aktivitäts-Monitoring für Projektmanagement

---

## Überblick

**R100VisionControl™** ist ein innovatives Farb-Codierungssystem, das den **Fortschritt** und die **Aktivität** eines Projekts in einer einzigen, intuitiven Visualisierung darstellt.

Statt getrennter Anzeigen (Phase + Status) kombiniert R100VisionControl™ beide Informationen in 6 progressiven Farbquadraten — jedes Quadrat zeigt gleichzeitig, **in welcher Phase** sich das Projekt befindet und **wie lange** es dort inaktiv ist.

---

## Das Problem (vorher)

### Alte Lösung:
```
Projekt "Acme GmbH"
[01] [02] [03] [04] [05] [06]  ← Phase/Fortschritt
           ↑ aktuelle Phase
                             Alert: KRITISCH  ← Separater Status
```

**Nachteile:**
- Zwei separate Informationen, die man kombinieren muss
- Verwirrend: Ist das Projekt in Phase 3 und kritisch? Oder kritisch wegen Alter?
- Keine Warnung, wenn ein Projekt in einer Phase stecken bleibt
- Admin muss beide Anzeigen lesen

---

## Die Lösung (R100VisionControl™)

### Neue Lösung:
```
Projekt "Acme GmbH"
[1] [2] [3] [4] [5] [6]  ← Phase + Aktivitätsstatus kombiniert
  ↑ aktuelle Phase (Farbe zeigt auch das Alter)
```

**Vorteile:**
- **Eine** intuitive Visualisierung
- Farbe zeigt **gleichzeitig** Phase UND Alter
- Sofortiges visuelles Feedback: "Dieses Projekt altert!"
- Perfekt für schnelle Übersichten auf dem Dashboard

---

## Funktionsweise

### Farblogik nach Aktivitätsalter

Die 6 Quadrate zeigen die Fortschritts-Phase (1-6). Die **Farbpalette** ändert sich basierend auf den **Tagen seit letzter Aktivität**:

#### 🟢 GRÜN (0-7 Tage) — Aktiv
- Projekt läuft, letzte Aktion liegt unter einer Woche
- Bedeutung: Alles im Plan
- **Handlung:** Keine Eile

```
[1🟢] [2🟢] [3🟢] [4🟢] [5⚪] [6⚪]
Hellgrün → Dunkelgrün (progressive Färbung)
```

#### 🟠 ORANGE (7-12 Tage) — Älter
- Keine Aktivität seit 7+ Tagen
- Bedeutung: Kontakt erforderlich
- **Handlung:** Folge-Mail senden, anrufen

```
[1🟠] [2🟠] [3🟠] [4🟠] [5⚪] [6⚪]
Hellorange → Dunkelorange
```

#### 🔴 ROT (12-13 Tage) — Kritisch alt
- Keine Aktivität seit 12+ Tagen
- Bedeutung: Projekt steckt fest, höchste Priorität
- **Handlung:** Sofortige Eskalation, persönlicher Kontakt

```
[1🔴] [2🔴] [3🔴] [4🔴] [5⚪] [6⚪]
Hellrot → Dunkelrot
```

#### ⚫ GRAU (13+ Tage) — Archiviert
- Keine Aktivität seit 13+ Tagen
- Automatisch archiviert nach 15 Tagen (13 + 2 Toleranz)
- Bedeutung: Projekt beendet/eingefroren
- **Handlung:** Archiv, Review oder Neustart nötig

```
[1⚫] [2⚫] [3⚫] [4⚫] [5⚫] [6⚫]
Hellgrau → Dunkelgrau
```

---

## Visuelle Beispiele

### Beispiel 1: Neues Projekt
```
Kunde: TechStartup GmbH
[1] [2] [3] [4] [5] [6]
↑ Phase 1 (Anfrage), Hellgrün → aktiv, alles im Plan
```
**Admin sieht sofort:** "Neuer Lead, gerade reingekommen, kümmere dich darum."

---

### Beispiel 2: Projekt in Bearbeitung, wird älter
```
Kunde: MediaAgentur Berlin
[1] [2] [3] [4] [5] [6]
      ↑ Phase 3 (Kontakt), Hellorange → 9 Tage keine Aktion
```
**Admin sieht sofort:** "Kunde antwortet nicht, seit über einer Woche. Muss folgen."

---

### Beispiel 3: Projekt stuck
```
Kunde: E-Commerce Shop
[1] [2] [3] [4] [5] [6]
         ↑ Phase 4 (Beauftragung), Hellrot → 13 Tage keine Aktion
```
**Admin sieht sofort:** "ALARMZEICHEN! Kunde hat ja gesagt, aber Vertrag nicht unterschrieben. Eskalation!"

---

### Beispiel 4: Archiviert
```
Kunde: OldClient (deprecated)
[1] [2] [3] [4] [5] [6]
[⚫] [⚫] [⚫] [⚫] [⚫] [⚫] Grau → >13 Tage, archiviert
```
**Admin sieht sofort:** "Abgeschlossen/Eingefroren, kann ignoriert werden."

---

## Timeline & Automation

```
Tag 0        7 Tage       12 Tage      13 Tage      15 Tage
├─ GRÜN ─────→ ORANGE ────→ ROT ────→ GRAU ────→ AUTO-ARCHIVE
│
└─ Letzte Aktion
```

### Automatische Aktionen:
- **Tag 7:** Quadrate wechseln zu Orange (visuell sichtbar)
- **Tag 12:** Quadrate wechseln zu Rot (sichtbare Warnung)
- **Tag 13:** Quadrate wechseln zu Grau (archiv-Status)
- **Tag 15:** Projekt wird automatisch ins Archiv verschoben

---

## Warum das besser ist

| Aspekt | Vorher | R100VisionControl™ |
|--------|--------|-------------------|
| **Information** | 2 separate Anzeigen | 1 kombinierte Visualisierung |
| **Schnelligkeit** | Muss beide lesen | Farbe sagt alles |
| **Intuition** | Rot = kritisch, aber wann? | Rot = kritisch alt, Farbe zeigt es |
| **Übersicht** | Verwirrend bei vielen Projekten | Schnell scannen, rote finden |
| **Kontext** | Status ≠ Phase | Farbe + Position = voller Kontext |
| **Automatik** | Manuell überwachen | Automatisches Altern sichtbar |

---

## Use Cases

### Use Case 1: Morgen-Standup
Admin blickt auf das CRM Dashboard:
- 🟢 Hellgrüne Projekte: "Alles läuft, keine Action nötig"
- 🟠 Orange: "Diese 3 Kunden brauchen Follow-Up heute"
- 🔴 Rot: "SOFORT anrufen, diese 2 sind kritisch"
- ⚫ Grau: "Archive, ignorieren"

**Ergebnis:** 30 Sekunden, vollständiger Überblick, keine gemissenen Projekte.

---

### Use Case 2: Wochenreview
Admin schaut auf die Woche:
- Keine neuen Grünen? → Marketing muss mehr Leads generieren
- Zu viele Orange? → Team ist überfordert oder zu langsam
- Rote in der Woche nicht rot geworden? → Team arbeitet aktiv, gut!

**Ergebnis:** Daten-getriebenes Feedback für Performance.

---

### Use Case 3: Eskalation vermeiden
Ein Projekt sitzt bei 12 Tagen (Rot). Admin sieht sofort:
- Kunde antwortet nicht
- Projekt steckt in Phase 2
- Höchste Priorität

Durch das visuelle Feedback wird Eskalation verhindert, bevor der Kunde selbst unzufrieden wird.

---

## Technische Details

### Datenquelle
```
last_interaction_date ← Jede Aktion (Notiz, Mail, Anruf) aktualisiert dies
         ↓
   getAgeStatus()
         ↓
    0-7 Tage? → GRÜN
    7-12 Tage? → ORANGE
    12-13 Tage? → ROT
    13+ Tage? → GRAU
         ↓
   Farbpalette auswählen
         ↓
   Quadrate rendern (1-6, progressive Farbe)
```

### Performance
- Berechnung: Echtzeit (JavaScript, keine DB-Query)
- Speicher: Minimal (nur 4 Farbpaletten gecacht)
- Automatik: Server-seitig (Task für 15-Tage Auto-Archiv)

---


**Version:** 1.0  
**Eingeführt:** 17. Mai 2026  
**Status:** ✅ Live  
**Made with:** Claude Code × REVISION100™

