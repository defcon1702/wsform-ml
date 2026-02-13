# WSForm Multilingual - Anforderungsdokument

## Projektziel

Entwicklung einer Zusatzlösung für WordPress, die es ermöglicht, n WSForm-Formulare zu übersetzen, ohne diese duplizieren zu müssen. Die Lösung soll iterativ Feld-IDs besorgen und Aktualisierungen der Formulare ermöglichen.

## Ausgangssituation

- **Projekt**: Komplexe WSForm-Formulare mit Repeater, Verschachtelungen, Conditional Logic
- **Problem**: Manuelle Übersetzungslösung aus WebBaker-Artikel erfordert:
  - Hardcodierte Feld-IDs
  - Separaten Code-Snippet pro Formular
  - Keine automatische Erkennung neuer/geänderter Felder
  - Keine zentrale Verwaltung

## Funktionale Anforderungen

### 1. Auto-Discovery System

**FR-001: Automatisches Scannen von Formularfeldern**
- System MUSS iterativ alle Feld-IDs eines Formulars erfassen
- System MUSS Feldstruktur vollständig analysieren
- System MUSS Änderungen an Formularen erkennen (neue/gelöschte/geänderte Felder)
- System MUSS Scan-Ergebnisse persistent speichern

**FR-002: Unterstützung komplexer Feldtypen**
- System MUSS Repeater-Felder (verschachtelt) unterstützen
- System MUSS Select/Radio/Checkbox-Optionen einzeln erfassen
- System MUSS Conditional Logic Texte erkennen
- System MUSS alle WSForm-Feldtypen verarbeiten können

**FR-003: Übersetzbare Eigenschaften**
- System MUSS folgende Eigenschaften übersetzen können:
  - `label` (Feldbezeichnung)
  - `placeholder` (Platzhalter-Text)
  - `help` (Hilfetext)
  - `invalid_feedback` (Fehlermeldung)
  - `options` (Select/Radio/Checkbox-Optionen)
  - `html` / `text_editor` (Rich Content)
  - `aria_label` (Barrierefreiheit)
- System MUSS alle übersetzbaren Eigenschaften identifizieren
- Anforderung: **"Alles was sich übersetzen lässt idealerweise. Mischsprache ist schlimmer als garkeine Übersetzung."**

### 2. Übersetzungsverwaltung

**FR-004: Zentrale Speicherung**
- System MUSS Übersetzungen in Datenbank speichern
- System MUSS Übersetzungen pro Formular, Feld, Eigenschaft und Sprache verwalten
- System MUSS Original-Werte für Referenz speichern
- System MUSS Sync-Status (letzte Aktualisierung) tracken

**FR-005: Multi-Language Support**
- System MUSS n Sprachen unterstützen (skalierbar)
- System MUSS Polylang-Integration bieten
- System MUSS auch ohne Polylang funktionieren (Fallback)
- System MUSS Standard-Sprache erkennen und ausschließen

**FR-006: CRUD-Operationen**
- System MUSS Übersetzungen erstellen können
- System MUSS Übersetzungen lesen können
- System MUSS Übersetzungen aktualisieren können
- System MUSS Übersetzungen löschen können
- System MUSS Bulk-Operationen unterstützen

### 3. Backend-Oberfläche

**FR-007: Formular-Übersicht**
- System MUSS alle WSForm-Formulare auflisten
- System MUSS Scan-Status pro Formular anzeigen
- System MUSS letzte Scan-Zeit anzeigen
- System MUSS Anzahl gecachter Felder anzeigen
- Anforderung: **"Backendoberfläche, am besten wo man die einzelnen Forms sehen kann"**

**FR-008: Automatisches Scannen per Klick**
- System MUSS Scan-Funktion per Button anbieten
- System MUSS Scan-Ergebnisse sofort anzeigen
- System MUSS Scan-Statistiken ausgeben (neu/aktualisiert/gelöscht)
- Anforderung: **"beim draufklicken automatisch auf felder gescannt wird"**

**FR-009: Übersetzungs-Interface**
- System MUSS Felder nach Sprachen gruppiert anzeigen
- System MUSS Original-Wert neben Übersetzungsfeld anzeigen
- System MUSS Inline-Editing ermöglichen
- System MUSS Speichern pro Feld oder Bulk ermöglichen
- System MUSS Übersetzungsfortschritt visualisieren

**FR-010: Sprach-Navigation**
- System MUSS verfügbare Sprachen als Tabs anzeigen
- System MUSS aktive Sprache hervorheben
- System MUSS Sprachwechsel ohne Neuladen ermöglichen

### 4. Warnsystem

**FR-011: Erkennung fehlender Übersetzungen**
- System MUSS fehlende Übersetzungen automatisch erkennen
- System MUSS Anzahl fehlender Übersetzungen anzeigen
- System MUSS Warnungen prominent darstellen
- Anforderung: **"Eine Warnung bei fehlenden Übersetzungen finde ich gut."**

**FR-012: Übersetzungsstatistiken**
- System MUSS Übersetzungsfortschritt pro Sprache berechnen
- System MUSS Prozentsatz übersetzte Felder anzeigen
- System MUSS Gesamt-Statistiken bereitstellen

### 5. Frontend-Rendering

**FR-013: Automatische Übersetzung**
- System MUSS Formulare basierend auf aktueller Sprache übersetzen
- System MUSS `ws_form_pre_render` Hook nutzen
- System MUSS Übersetzungen vor Rendering anwenden
- System MUSS Performance optimieren (Caching)

**FR-014: Keine Formular-Duplikation**
- System MUSS mit einem Formular für alle Sprachen arbeiten
- System MUSS Feld-IDs konsistent halten
- System MUSS Integrationen nicht brechen

### 6. Aktualisierungsmechanismus

**FR-015: Sync-Funktion**
- System MUSS Änderungen an Formularen erkennen
- System MUSS neue Felder automatisch erfassen
- System MUSS gelöschte Felder aus Cache entfernen
- System MUSS geänderte Felder aktualisieren
- Anforderung: **"Eine Lösung die sich die Feld IDs des Formulares itterativ besorgt und auch aktualisierungen ermöglicht."**

**FR-016: Scan-Protokollierung**
- System MUSS jeden Scan protokollieren
- System MUSS Scan-Dauer messen
- System MUSS Fehler loggen
- System MUSS Scan-Historie bereitstellen

## Nicht-funktionale Anforderungen

### Performance

**NFR-001: Skalierbarkeit**
- System MUSS n Formulare effizient verarbeiten
- System MUSS große Formulare (>100 Felder) handhaben
- System MUSS mehrere Sprachen ohne Performance-Einbußen unterstützen

**NFR-002: Caching**
- System MUSS gescannte Felder cachen
- System MUSS Übersetzungen cachen
- System MUSS Cache-Invalidierung bei Änderungen durchführen

**NFR-003: Datenbank-Optimierung**
- System MUSS indizierte Queries nutzen
- System MUSS Batch-Operations für Bulk-Saves verwenden
- System MUSS Transaktionen für Daten-Integrität nutzen

### Architektur

**NFR-004: Plugin-Architektur**
- System MUSS als separates WordPress-Plugin implementiert sein
- System MUSS unabhängig von Polylang-Updates funktionieren
- System MUSS Polylang-API nutzen (wenn verfügbar)
- Entscheidung: **"Separates Plugin" für bessere Stabilität und Skalierbarkeit**

**NFR-005: Datenbank-Speicherung**
- System MUSS Datenbank statt JSON nutzen
- System MUSS 3 Tabellen verwenden: `translations`, `field_cache`, `scan_log`
- Entscheidung: **"Datenbank - Performanter bei vielen Formularen, bessere Query-Möglichkeiten, Transaktions-Sicherheit"**

**NFR-006: Code-Qualität**
- System MUSS PSR-Standards folgen
- System MUSS Vanilla JavaScript nutzen (kein jQuery)
- System MUSS moderne ES6+ Syntax verwenden
- System MUSS dokumentiert sein (Inline-Kommentare, README)

### Benutzerfreundlichkeit

**NFR-007: Intuitive UI**
- System MUSS moderne WordPress-Optik haben
- System MUSS responsive sein (Desktop, Tablet, Mobile)
- System MUSS Loading-States für alle Aktionen zeigen
- System MUSS Feedback bei Erfolg/Fehler geben

**NFR-008: Workflow-Effizienz**
- System MUSS Übersetzungsprozess minimieren
- System MUSS Inline-Editing ermöglichen
- System MUSS Bulk-Operationen anbieten
- System MUSS Fortschritt visualisieren

### Kompatibilität

**NFR-009: WordPress-Kompatibilität**
- System MUSS WordPress 5.8+ unterstützen
- System MUSS PHP 7.4+ unterstützen
- System MUSS WS Form (beliebige Version) unterstützen

**NFR-010: Polylang-Integration**
- System MUSS mit Polylang funktionieren
- System MUSS ohne Polylang funktionieren (Fallback)
- System MUSS Polylang-Sprachen automatisch erkennen

### Wartbarkeit

**NFR-011: Erweiterbarkeit**
- System MUSS Hooks und Filter bereitstellen
- System MUSS REST API für externe Integrationen bieten
- System MUSS modulare Architektur haben

**NFR-012: Fehlerbehandlung**
- System MUSS Fehler graceful handhaben
- System MUSS Fehler loggen
- System MUSS Benutzer über Fehler informieren

## Technische Spezifikationen

### Datenbank-Schema

**Tabelle: `wsform_ml_translations`**
- Primärschlüssel: `id`
- Unique Index: `(form_id, field_id, field_path, property_type, language_code)`
- Felder: form_id, field_id, field_path, property_type, language_code, original_value, translated_value, context, is_auto_generated, last_synced, created_at, updated_at

**Tabelle: `wsform_ml_field_cache`**
- Primärschlüssel: `id`
- Unique Index: `(form_id, field_id, field_path)`
- Felder: form_id, field_id, field_type, field_path, field_label, parent_field_id, is_repeater, has_options, translatable_properties, field_structure, last_scanned

**Tabelle: `wsform_ml_scan_log`**
- Primärschlüssel: `id`
- Felder: form_id, scan_type, fields_found, new_fields, updated_fields, deleted_fields, scan_status, error_message, scan_duration, scanned_at

### REST API Endpoints

- `GET /wp-json/wsform-ml/v1/forms` - Liste aller Formulare
- `POST /wp-json/wsform-ml/v1/forms/{id}/scan` - Formular scannen
- `GET /wp-json/wsform-ml/v1/forms/{id}/fields` - Gecachte Felder abrufen
- `GET /wp-json/wsform-ml/v1/forms/{id}/translations` - Übersetzungen abrufen
- `GET /wp-json/wsform-ml/v1/forms/{id}/translations/missing` - Fehlende Übersetzungen
- `GET /wp-json/wsform-ml/v1/forms/{id}/stats` - Übersetzungsstatistiken
- `POST /wp-json/wsform-ml/v1/translations` - Übersetzung speichern
- `POST /wp-json/wsform-ml/v1/translations/bulk` - Bulk-Speichern
- `DELETE /wp-json/wsform-ml/v1/translations/{id}` - Übersetzung löschen

### Hooks & Filter

**Actions:**
- `wsform_ml_after_scan` - Nach erfolgreichem Scan
- `wsform_ml_translation_saved` - Nach Speichern einer Übersetzung

**Filter:**
- `wsform_ml_translatable_properties` - Übersetzbare Eigenschaften anpassen
- `wsform_ml_translation_map` - Translation Map vor Rendering anpassen

## Abnahmekriterien

### Funktional

- [ ] Formular kann gescannt werden und alle Felder werden erkannt
- [ ] Repeater-Felder werden korrekt verschachtelt erfasst
- [ ] Select/Radio/Checkbox-Optionen werden einzeln erfasst
- [ ] Übersetzungen können pro Feld und Sprache gespeichert werden
- [ ] Formular wird im Frontend korrekt übersetzt
- [ ] Fehlende Übersetzungen werden angezeigt
- [ ] Aktualisierungen am Formular werden beim Re-Scan erkannt
- [ ] Backend-UI zeigt alle Formulare und deren Status
- [ ] Polylang-Sprachen werden automatisch erkannt

### Nicht-Funktional

- [ ] Plugin aktiviert ohne Fehler
- [ ] Datenbank-Tabellen werden korrekt erstellt
- [ ] UI ist responsive auf allen Geräten
- [ ] Scan-Vorgang dauert <5 Sekunden für Formular mit 50 Feldern
- [ ] Keine jQuery-Abhängigkeit
- [ ] Code folgt WordPress Coding Standards
- [ ] README.md ist vollständig und verständlich

## Offene Punkte / Zukünftige Erweiterungen

- Auto-Translate via DeepL/Google Translate API
- Export/Import von Übersetzungen (CSV/JSON)
- Versionierung von Übersetzungen
- Review-Workflow für Übersetzungen
- WPML-Support (zusätzlich zu Polylang)
- Übersetzungs-Memory für wiederkehrende Texte
- Statistik-Dashboard mit Diagrammen
- E-Mail-Benachrichtigungen bei fehlenden Übersetzungen

## Referenzen

- **Ursprüngliche Lösung**: https://webbaker.sk/en/translate-ws-form-using-polylang-php/
- **WSForm Dokumentation**: https://wsform.com/knowledgebase/
- **Polylang API**: https://polylang.pro/doc/developers-how-to/

## Änderungshistorie

| Datum | Version | Änderung |
|-------|---------|----------|
| 2026-02-13 | 1.0 | Initiale Anforderungsdefinition |

---

**Status**: ✅ Alle Anforderungen implementiert (Version 1.0.0)
