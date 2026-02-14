# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [1.2.7] - 2026-02-14

### Changed

- **Admin UX**: Console-Logging entfernt
  - Alle `console.log()` Aufrufe aus admin.js entfernt
  - Nur noch `console.error()` für echte Fehler
  - Sauberere Browser-Console beim Scannen

### Added

- **Settings-Seite**: Länderkürzel-Übersicht
  - Neue Tabelle zeigt alle Polylang-Sprachen und ihre Codes
  - Erklärt welcher Code bei welcher Sprache gesetzt wird
  - Hilft bei der Formular-Auswertung nach Sprache

## [1.2.6] - 2026-02-14

### Fixed

- **Sprachfeld-Erstellung**: Fatal Error behoben
  - **Fehler**: `Call to undefined method WS_Form_Field::db_update()`
  - **Ursache**: Methode `db_update()` existiert nicht in WSForm
  - **Lösung**: Meta-Daten VOR `db_create()` setzen, kein `db_read()`/`db_update()` nötig
  - Feld wird jetzt korrekt erstellt ohne Fehler

## [1.2.5] - 2026-02-14

### Fixed

- **Tabs-Akkordion**: Tabs verschwinden nicht mehr nach Scan ✅
  - **Root Cause**: `field_id` Spalte ist INTEGER, aber Scanner verwendete Strings ("group_0")
  - Strings wurden zu `0` konvertiert → alle Tabs hatten `field_id: 0` → Duplicate Key
  - **Lösung**: Negative Integer IDs für Group Labels (Group 0 → -1, Group 1 → -2)
  - Alte fehlerhafte Group Labels (field_id=0) werden automatisch gelöscht
  - Tabs bleiben jetzt persistent nach jedem Scan

## [1.2.4] - 2026-02-14

### Fixed

- **Sprachfeld-Löschung**: Feld wird jetzt auch aus WSForm entfernt
  - `db_delete()` wird aufgerufen, nicht nur Konfiguration gelöscht
  - Formular wird nach Löschung publiziert
- **Sprachfeld-Rendering**: Feld bekommt korrekte ID und name-Attribut
  - Feld wird nach Erstellung neu geladen (`db_read()`)
  - Meta-Daten werden korrekt gesetzt und gespeichert (`db_update()`)
  - Feld wird sofort korrekt von WSForm gerendert

## [1.2.3] - 2026-02-14

### Fixed

- **Sprachfeld**: Formular wird nach Erstellung publiziert
  - `db_publish()` wird aufgerufen, damit WSForm das neue Feld sofort erkennt
  - Feld muss nicht mehr manuell editiert werden
- **Sprachfeld**: Eye Icons entfernt
  - Meta-Daten korrigiert (`hidden`, `hidden_bypass` leer)
  - Feld wird als normales Hidden Field ohne Conditional Logic angezeigt

### Known Issues

- **Tabs-Akkordion**: Verschwindet nach Scan (wird in v1.2.4 behoben)
  - Ursache wird noch analysiert
  - Workaround: Seite neu laden nach Scan

## [1.2.2] - 2026-02-14

### Fixed

- **Settings-Seite**: Sprachfeld-Integration wird jetzt korrekt angezeigt
  - `render_settings_page` ruft nun `WSForm_ML_Settings_Page::instance()` auf
  - Alte View-Datei wurde durch neue Settings-Klasse ersetzt
- **Tabs-Akkordion**: Group Labels (Tabs) werden im Admin korrekt angezeigt
  - Badge "tab" für Group Labels hinzugefügt (grüner Hintergrund)
  - `field_type === 'group'` wird korrekt erkannt

## [1.2.1] - 2026-02-14

### Fixed

- **Akkordion-Layout**: Akkordions lassen sich wieder einzeln aufklappen
  - Korrektur der CSS-Klasse von `.wsform-ml-field` zu `.wsform-ml-field-header`
  - Event-Handler funktioniert wieder korrekt
- **Icon**: Speicher-Button nutzt jetzt Pfeil-Icon (`dashicons-arrow-down-alt`)
  - Statt DB-Icon nun einfacher Pfeil nach unten

## [1.2.0] - 2026-02-14

### Added

- **Sprachfeld-Integration**: Automatisches Erstellen von Hidden Fields für Sprachcode-Injection
  - Neue Klasse `WSForm_ML_Language_Field_Manager` für Field-Verwaltung
  - UI in Settings-Seite mit Form-Dropdown und "Sprachfeld erstellen" Button
  - Automatische Wertsetzung beim Form-Rendering via `wsf_pre_render` Hook
  - Übersicht konfigurierter Sprachfelder mit Entfernen-Funktion
  - AJAX-Warnung für Polylang-Kompatibilität
- **Farbliche Kennzeichnung**: Unübersetzte Felder werden visuell hervorgehoben
  - Orange Border (4px links) + gelber Hintergrund (#fffbf0)
  - CSS-Klasse `untranslated` für bessere UX

### Changed

- **UI-Verbesserung**: Speicher-Button Icon von Häkchen zu Diskette (`dashicons-download`)
  - Bündige Ausrichtung mit Typografie (margin-top: -2px, vertical-align: middle)
  - Konsistente Icon-Größe (18px)

### Fixed

- **Scan-Fehler**: "Unexpected token '<'" beim Form-Scannen behoben
  - Output Buffer Handling in REST API Endpoint (`ob_start()`, `ob_end_clean()`)
  - Verhindert HTML-Ausgabe in JSON-Response
- **Duplicate Entry**: Group Labels (Tabs) verursachten DB-Fehler beim Scannen
  - Double-Check vor INSERT implementiert
  - Race Condition bei parallelen Scans behoben
- **Tabs verschwinden**: Group Labels wurden abwechselnd gefunden/gelöscht
  - `discovered_keys` wird immer gesetzt, auch bei fehlgeschlagenem INSERT
  - Verhindert fälschliches Löschen bei DB-Fehlern
- **Doppeltes Menü**: "Einstellungen" Eintrag war doppelt im Admin-Menü
  - Menü-Registrierung aus `class-settings-page.php` entfernt
  - Zentralisierung in `class-admin-menu.php`

## [1.1.0] - 2026-02-13

### Added

- **Native WSForm API Integration**: Optionale Nutzung der offiziellen WSForm Translation API
  - Neue Klasse `WSForm_ML_Native_Adapter` für `wsf_translate` Hook
  - Feature Toggle System via `WSForm_ML_Feature_Manager`
  - Settings-Seite für Feature-Verwaltung
  - Parallelbetrieb von Legacy und Native API möglich
- **Tab-Namen Übersetzung**: Group Labels (Tabs) werden gescannt und übersetzt
  - Scanner erkennt `group->label` als übersetzbare Property
  - Renderer übersetzt Tab-Namen im Frontend

### Changed

- **Globaler Speicher-Button**: Einzelne Save-Buttons durch globalen Button ersetzt
  - Akkordion bleibt nach Speichern geöffnet
  - Bessere UX beim Übersetzen mehrerer Felder
- **Admin UI**: Irreführender Text "automatische Speicherung" entfernt

### Fixed

- **Placeholder-Übersetzung**: Placeholder-Texte wurden nicht übersetzt
  - Key-Format im Renderer korrigiert (`field_path::placeholder`)
- **Options-Übersetzung**: Select/Radio/Checkbox Optionen wurden überschrieben
  - Jede Option hat eigenen `field_path` (z.B. `meta.data_grid_select.groups.0.rows.0.data.0`)
  - Renderer nutzt korrektes Key-Format mit Punkt statt `::`
- **Range Slider**: Min/Max Labels, Prefix, Suffix werden gescannt und übersetzt
- **Button Text**: Submit/Save Button Text wird gescannt und übersetzt
- **Label Masks**: Prepend/Append Texte werden gescannt und übersetzt
- **DSGVO Checkbox**: Übersetzung erscheint jetzt im Frontend
- **Akkordion**: Schließt nicht mehr nach Speichern
- **PHP Warnings**: Fehlende Array-Keys für Group Labels behoben
  - `parent_field_id` und `field_structure` hinzugefügt

## [1.0.0] - 2026-02-12

### Added

- **Initiales Release**: WSForm Multilingual Plugin
- **Polylang Integration**: Automatische Erkennung verfügbarer Sprachen
- **Field Scanner**: Erkennung übersetzbare Properties in WSForm Formularen
  - Label, Help Text, Placeholder, Invalid Feedback
  - Select/Radio/Checkbox Optionen
- **Translation Manager**: Speicherung und Verwaltung von Übersetzungen
  - Datenbank-Schema mit Caching
  - REST API Endpoints für Admin-Interface
- **Renderer**: Anwendung von Übersetzungen im Frontend
  - Hook auf `wsf_pre_render`
  - Unterstützung für Standard-Sprache
- **Admin Interface**:
  - Formular-Auswahl
  - Sprach-Tabs für alle Polylang-Sprachen
  - Übersetzungs-Editor mit Original-Werten
  - Speicher-Funktion pro Feld

## Versionierungs-Schema

Wir folgen [Semantic Versioning](https://semver.org/lang/de/)

**MAJOR.MINOR.PATCH** (z.B. 1.2.0)

- **MAJOR**: Breaking Changes (inkompatible API-Änderungen)
- **MINOR**: Neue Features (abwärtskompatibel)
- **PATCH**: Bugfixes (abwärtskompatibel)

### Beispiele

- `1.0.0` → `1.0.1`: Bugfix (z.B. Scan-Fehler behoben)
- `1.0.1` → `1.1.0`: Neues Feature (z.B. Tab-Übersetzung)
- `1.1.0` → `2.0.0`: Breaking Change (z.B. DB-Schema geändert)
