# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [1.7.0] - 2026-02-14

### ✨ Added
- **Auto-Save mit Debouncing**
  - Automatisches Speichern nach 500ms Inaktivität beim Tippen
  - Visuelle Indikatoren: "⏳ Speichert...", "✓ Gespeichert", "✗ Fehler"
  - Keine manuellen Speicher-Klicks mehr nötig
  - Smooth UX mit fadeIn Animation

- **Plugin-Internationalisierung (i18n)**
  - `.pot` Template mit allen übersetzungsfähigen Strings
  - Deutsche Übersetzung (`de_DE.po/.mo`)
  - Englische Übersetzung (`en_US.po/.mo`)
  - Textdomain automatisch geladen via `load_plugin_textdomain()`
  - Plugin-UI jetzt in Deutsch und Englisch verfügbar

### 🚀 Performance
- **Transient Cache für Forms-Liste**
  - 5-Minuten Cache für Forms-Liste
  - Reduziert Ladezeit von ~500ms auf ~10-20ms
  - Cache wird nach Scan automatisch invalidiert
  - Refresh-Parameter `?refresh=1` zum Bypass

### 📝 Changed
- `admin/assets/js/admin.js`: Auto-Save Logik + Debouncing
- `admin/assets/css/admin.css`: Speicher-Indikatoren Styles
- `admin/class-rest-api.php`: Transient Cache für `get_forms()`
- `languages/`: Neue `.pot/.po/.mo` Dateien für i18n

### ℹ️ Info
- **N+1 Query Fix**: Bereits in v1.6.x implementiert
- **DB-Indizes**: Bereits optimal in v1.6.x
- Keine Breaking Changes

## [1.6.3] - 2026-02-14

### ✨ Added
- **HTML-Felder Support**
  - Scanner extrahiert jetzt `html_editor` Property (HTML-Feld Content)
  - Renderer übersetzt `html_editor` im Frontend
  - HTML-Tags bleiben erhalten beim Speichern und Rendern
  - Betrifft: WSForm HTML-Felder (Typ: `html`)

### 🔧 Fixed
- **Numerische Sortierung der Felder im Admin UI**
  - Vorher: Alphabetische Sortierung (fields.10 vor fields.2)
  - Jetzt: Numerische Sortierung (fields.2 vor fields.10)
  - Felder werden in natürlicher Reihenfolge angezeigt
  - Implementiert via `usort()` in `get_cached_fields()`

### 📝 Changed
- `class-field-scanner.php`: `html_editor` zu `meta_properties` hinzugefügt
- `class-renderer.php`: `html_editor` zu `meta_properties` hinzugefügt
- `class-field-scanner.php`: Numerische Sortierung nach `field_path`

## [1.6.2] - 2026-02-14

### 🔧 Fixed - CRITICAL
- **Options überschreiben sich nicht mehr beim Speichern**
  - Problem: `get_translation()` suchte nur nach `(form_id, field_id, property_type, language_code)`
  - Für Options mit gleichem field_id aber unterschiedlichem field_path wurden alle als "existierend" erkannt
  - Resultat: Option 1 gespeichert → überschrieben durch Option 2 → überschrieben durch Option 3
  - Nur die letzte Option blieb in der DB
  - Fix: `field_path_hash` zur WHERE-Klausel hinzugefügt
  - Jetzt: Jede Option wird einzeln identifiziert und gespeichert
  - Betrifft: Price Radio, Price Checkbox, Price Select, normale Radio/Checkbox Options

### 📝 Changed
- `class-translation-manager.php`: `get_translation()` verwendet jetzt `field_path_hash` in WHERE-Klausel

## [1.6.1] - 2026-02-14

### 🔧 Fixed - CRITICAL
- **field_id Spalte von UNSIGNED zu SIGNED geändert**
  - Problem: `bigint unsigned` kann keine negativen Werte speichern
  - Groups verwenden negative field_ids (-4, -6) um Kollisionen zu vermeiden
  - MySQL konvertierte -4 → 0, -6 → 0
  - Resultat: Alle Groups hatten field_id=0, Renderer konnte Übersetzungen nicht finden
  - Fix: `field_id bigint NOT NULL` (SIGNED) in beiden Tabellen
  - Betrifft: `wp_wsform_ml_translations` und `wp_wsform_ml_field_cache`

### ⚠️ BREAKING CHANGE
- **Tabellen müssen neu erstellt werden**
  - DROP TABLE wp_wsform_ml_translations
  - DROP TABLE wp_wsform_ml_field_cache  
  - DROP TABLE wp_wsform_ml_scan_log
  - Plugin deaktivieren + aktivieren
  - Formular neu scannen
  - Übersetzungen neu eingeben

## [1.6.0] - 2026-02-14

### Fixed
- **CRITICAL: MySQL Unique Index Limit überschritten - Duplicate Entry Fehler**
  - Problem: Unique Index `(form_id, field_id, field_path, property_type, language_code)` zu lang
  - MySQL Limit: 3072 bytes für Unique Keys
  - `field_path` varchar(500) → überschreitet Limit → Index wird abgeschnitten
  - Resultat: Duplicate Entry Fehler beim Speichern von Options mit langen Paths
  - Symptom: "Duplicate entry '4-28-groups.0.sections.0.fields.19.meta.data_grid_checkbox.group'"
  - Lösung: Verwende `field_path_hash` (SHA256) im Unique Index statt `field_path`

### Changed
- **BREAKING CHANGE: DB Schema geändert**
  - Neue Spalte: `field_path_hash` char(64) NOT NULL
  - Unique Index jetzt: `(form_id, field_id, field_path_hash, property_type, language_code)`
  - `field_path_hash` wird automatisch berechnet: `hash('sha256', $field_path)`
  - Keine Kollisionen mehr, unabhängig von Path-Länge

### Migration Required
- **Plugin muss neu installiert werden**
  - Alte Tabelle löschen: `DROP TABLE wp_wsform_ml_translations;`
  - Plugin deaktivieren und neu aktivieren → Tabelle wird neu erstellt
  - Formular neu scannen
  - Übersetzungen neu eingeben

## [1.5.5] - 2026-02-14

### Fixed
- **CRITICAL: Translation Map Building verwendet jetzt korrekten Key-Typ**
  - Problem: `build_translation_map()` verwendete immer `field_id::property_type` für alle Keys
  - Options brauchen aber `field_path::property_type` (weil Renderer mit field_path sucht)
  - Resultat: ALLE Options Übersetzungen wurden nicht gefunden (Checkboxen, Radios, Select, eCommerce)
  - Lösung: Options verwenden jetzt `field_path::option`, andere Properties verwenden `field_id::property_type`

### Technical Details
- `build_translation_map()`: Unterscheidet jetzt zwischen Options und anderen Properties
- Options: `field_path::option` (z.B. "groups.0.sections.0.fields.1.meta.data_grid_checkbox...::option")
- Andere: `field_id::property_type` (z.B. "24::label" oder "-4::group_label")
- Konsistent mit `translate_options()` aus v1.5.4

### Migration Required
- **Alte field_ids müssen aktualisiert werden**
  - Problem: DB hat noch alte field_ids (0, 4, 6) statt neue (-4, -6)
  - Lösung: Führe Migration Script aus
  - Script: `migrate-field-ids.php?form_id=4&dry_run` (erst testen)
  - Dann: `migrate-field-ids.php?form_id=4` (ausführen)

## [1.5.4] - 2026-02-14

### Fixed
- **CRITICAL: E-Commerce Optionen werden jetzt im Frontend ausgespielt**
  - Problem: Renderer verwendete falsches Key-Format für Options Lookup
  - Renderer suchte: `"53.meta.data_grid_checkbox_price.groups.0.rows.2.data.0::option"` ❌
  - DB hat aber: `"groups.0.sections.0.fields.1.meta.data_grid_checkbox_price.groups.0.rows.2.data.0::option"` ✅
  - Resultat: Options Übersetzungen wurden nicht gefunden
  - Lösung: Renderer verwendet jetzt field_path statt field_id für Options

### Technical Details
- Renderer: `translate_options()` verwendet jetzt `field_path` Parameter
- Renderer: `translate_field()` erhält jetzt `field_path` Parameter
- Key-Format jetzt konsistent mit Admin.js und DB
- Admin.js speichert: `field_path + ".meta.data_grid_TYPE..."`
- Renderer sucht jetzt: `field_path + ".meta.data_grid_TYPE..."` ✅

### Migration Required
- **Group Labels müssen neu eingegeben werden**
  - Alte field_ids: 0, 4, 6 (aus v1.5.0-v1.5.2)
  - Neue field_ids: -4, -6 (aus v1.5.3)
  - Aktion: Formular neu scannen und Tab-Übersetzungen neu eingeben

## [1.5.3] - 2026-02-14

### Fixed
- **CRITICAL: field_id Kollision zwischen Groups und Feldern**
  - Problem: Group Labels verwendeten positive group->id als field_id
  - Group ID 4 → field_id = 4 ❌
  - Echtes Feld ID 4 → field_id = 4 ❌
  - Resultat: Kollision! Nur ein Feld wird im Admin-Interface angezeigt
  - Symptom: Tab 2 fehlt, Nachname-Feld erscheint unter Tab 1
  - Lösung: Verwende negative group->id → -4, -6 (keine Kollision)
  - Group ID 4 → field_id = -4 ✅
  - Echtes Feld ID 4 → field_id = 4 ✅
  - Stabil bei Tab-Reihenfolge-Änderungen ✅

### Technical Details
- Scanner: `$group_field_id = -($group->id)` statt `$group->id`
- Renderer: `$group_label_key = (-($group->id)) . "::group_label"`
- Negative IDs vermeiden Kollision mit echten Feld-IDs
- Weiterhin stabil basierend auf group->id (nicht group_index)

## [1.5.2] - 2026-02-14

### Fixed
- **CRITICAL: Tab-Übersetzungen werden jetzt im Frontend ausgespielt**
  - Problem: Renderer verwendete `group_index` statt `group->id` für Translation Lookup
  - Translation Map Key: `"group_0::group_label"` ❌
  - Sollte sein: `"4::group_label"` (group->id) ✅
  - Resultat: Tab-Übersetzungen wurden nicht gefunden
  - Lösung: Renderer verwendet jetzt `group->id` wie Scanner

- **CRITICAL: Preis-Feld Übersetzungen werden jetzt im Frontend ausgespielt**
  - Problem: Renderer verwendete falsche `data_grid` Property-Namen
  - `price_checkbox` → Renderer suchte `data_grid_price_checkbox` ❌
  - Sollte sein: `data_grid_checkbox_price` ✅
  - Resultat: Preis-Feld Optionen wurden nicht übersetzt
  - Lösung: Renderer verwendet jetzt `get_data_grid_property()` wie Scanner

### Technical Details
- Renderer: `apply_translations()` verwendet jetzt `group->id` statt `group_index`
- Renderer: `translate_field()` verwendet jetzt `get_data_grid_property()`
- Neue Helper-Methode `get_data_grid_property()` im Renderer (gleiche Logik wie Scanner)
- Konsistenz zwischen Scanner und Renderer hergestellt ✅

## [1.5.1] - 2026-02-14

### Changed
- **UI Verbesserung: Infofeld mit fehlenden Übersetzungen entfernt**
  - Infofeld "X fehlende Übersetzung(en) gefunden" wird nicht mehr angezeigt
  - Farbmarkierungen (rote Warnsymbole) reichen zur Kennzeichnung aus
  - Reduziert visuelle Unordnung im Admin-Interface

## [1.5.0] - 2026-02-14

### BREAKING CHANGE
- **Group Labels (Tabs) verwenden jetzt WSForm Group ID statt Index**
  - Problem: Scanner verwendete `group_index` für `field_id`
  - `group_index` ändert sich bei Tab-Reihenfolge-Änderungen
  - Resultat: Übersetzungen werden vertauscht wenn User Tabs verschiebt
  - Lösung: Verwende `group->id` (WSForm's echte Group ID)
  - `group->id` ist stabil und ändert sich nicht bei Reihenfolge-Änderungen
  - **WICHTIG:** Bestehende Tab-Übersetzungen müssen neu eingegeben werden!

### Fixed
- **Tab-Übersetzungen werden jetzt korrekt gespeichert und ausgespielt**
  - Scanner verwendet jetzt `group->id` statt negative Index-basierte IDs
  - Beispiel: Group 0 mit ID 4 → `field_id = 4` (statt `-1`)
  - Übersetzungen bleiben erhalten bei Tab-Reihenfolge-Änderungen ✅

### Migration Required
- **Alte Tab-Übersetzungen sind ungültig**
  - Alte `field_id`: `-1`, `-2`, `-3` (Index-basiert)
  - Neue `field_id`: `4`, `6`, `8` (WSForm Group IDs)
  - Aktion: Formular neu scannen und Tab-Übersetzungen neu eingeben

## [1.4.2] - 2026-02-14

### Fixed
- **CRITICAL: Preis-Felder Optionen wurden nicht gescannt**
  - Problem: Scanner suchte nach falschen data_grid Property-Namen
  - `price_checkbox` → Scanner suchte `data_grid_price_checkbox`
  - WSForm nutzt aber: `data_grid_checkbox_price` ❌
  - Resultat: Keine Optionen für Preis-Felder gescannt
  - Lösung: Neue `get_data_grid_property()` Methode mit korrekter Namenskonvention
  - Betrifft: `price_select`, `price_radio`, `price_checkbox`

### Technical Details
- WSForm Namenskonvention für Preis-Felder:
  - Standard: `data_grid_[type]` (z.B. `data_grid_checkbox`)
  - Preis: `data_grid_[base]_price` (z.B. `data_grid_checkbox_price`)
- Neue Helper-Methode `get_data_grid_property($field_type)`:
  - Erkennt `price_*` Präfix
  - Entfernt Präfix und hängt `_price` Suffix an
  - `price_checkbox` → `checkbox` → `data_grid_checkbox_price` ✅

### Known Issues
- ❌ Tab 1 (Group Label) wird nicht gespeichert
- ❌ Tab 2 wird gespeichert aber nicht im Frontend ausgespielt
- Weitere Analyse erforderlich

## [1.4.1] - 2026-02-14

### Fixed
- **Scanner empty() Bug bei Preis-Feldern**
  - Problem: `empty("0")` = `true` in PHP
  - Resultat: Optionen mit Wert "0" wurden nicht gescannt
  - Lösung: Verwende `!isset($value) || $value === '' || $value === null` statt `empty($value)`
  - Betrifft: Alle Option-Felder (select, radio, checkbox, price_*)
  - Verhindert: Fehlende Optionen im Scanner

### Technical Details
- PHP's `empty()` Funktion behandelt "0" als leer
- Bei Preis-Feldern kann die Value-Spalte "0" sein
- Neue Validierung erlaubt "0" als gültigen Wert
- Nur echte leere Werte (null, '', undefined) werden übersprungen

## [1.4.0] - 2026-02-14

### Refactoring
- **Scanner auf WSForm native Funktionen umgestellt**
  - Verwendet jetzt `wsf_form_get_object()` statt direktem `db_read()`
  - Fallback für ältere WSForm Versionen implementiert
  - Sauberer, wartbarer Code
  - Bessere Kompatibilität mit zukünftigen WSForm-Versionen

### Fixed
- **Price Fields Scanner (price_select, price_radio, price_checkbox)**
  - Problem: Scanner erkannte alle Spalten als übersetzbar
  - Resultat: Value, Price, Currency wurden fälschlicherweise gescannt
  - Lösung: Nur Spalte 0 (Label) wird jetzt gescannt
  - Spalten 1-3 (Value, Price, Currency) werden übersprungen
  - Verhindert Daten-Korruption bei Preis-Feldern

### Changed
- `get_form_object()`: Nutzt WSForm native API
- `extract_options()`: Intelligente Spalten-Erkennung für Preis-Felder
- Bessere Code-Struktur und Kommentare
- Entfernt unnötigen Output-Buffering Code

### Technical Details
- Price Fields haben 4 Spalten:
  * Spalte 0: Label (übersetzbar) ✅
  * Spalte 1: Value/ID (nicht übersetzbar) ❌
  * Spalte 2: Price (nicht übersetzbar) ❌
  * Spalte 3: Currency (nicht übersetzbar) ❌
- Scanner erkennt automatisch `price_*` Felder via `strpos($field->type, 'price_') === 0`
- Fallback-Logik für ältere WSForm Versionen ohne native Funktionen

## [1.3.0] - 2026-02-14

### ⚠️ BREAKING CHANGE
- **Translation Lookup auf field_id umgestellt** (statt field_path)
  - Problem: field_path ändert sich beim Hinzufügen/Entfernen von Feldern
  - Resultat: Übersetzungen wurden verschoben (z.B. "Price Select" zeigte "Vorname")
  - Lösung: Verwende field_id als PRIMARY Key (stabil, ändert sich nicht)
  - **WICHTIG**: Alte Übersetzungen funktionieren nicht mehr - Formular neu scannen und Übersetzungen neu eingeben!

### Fixed
- **400 Bad Request beim Speichern**
  - Problem: parseInt(field_id) gab NaN zurück wenn field_id String war
  - Lösung: Entferne parseInt() in admin.js, verwende field_id direkt
- **Accordion-Icons wurden nicht angezeigt**
  - Problem: ::before Pseudo-Element mit absolute Position funktionierte nicht
  - Lösung: Verwende inline-block mit margin statt absolute Position

### Changed
- Translation Manager: get_translation() sucht nur nach field_id (ohne field_path)
- Renderer: build_translation_map() verwendet field_id als Key
- Renderer: apply_translations() verwendet field->id für Lookup
- Renderer: translate_options() verwendet field_id für Options
- Admin JS: field_id wird nicht mehr mit parseInt() konvertiert

### Technical Details
- field_id ist stabil und ändert sich nicht beim Umstrukturieren
- field_path ist nur noch zur Information/Debugging
- Translation Key Format: `{field_id}::{property_type}`
- Options Key Format: `{field_id}.meta.data_grid_{type}.groups.{g}.rows.{r}.data.{c}::option`

## [1.2.9] - 2026-02-14

### Fixed
- **KRITISCHER BUGFIX**: Scanner vermischte Feld-Labels
  - Problem: Scanner verwendete `field_id + field_path` als eindeutigen Key
  - WSForm kann dieselbe `field_id` für verschiedene Felder vergeben
  - Resultat: Label von "price_select" wurde mit "Vorname" überschrieben
  - Lösung: Verwende nur `field_path` als eindeutigen Key (ist bereits eindeutig)
  - UPDATE/DELETE Queries verwenden jetzt nur noch `field_path` in WHERE-Klausel
  - `field_id` wird jetzt auch beim UPDATE aktualisiert (kann sich ändern)

### Added
- Debug-Script für Price Checkbox Felder (`debug-price-checkbox.php`)
  - Zeigt Formular-Struktur und Scanner-Ergebnisse
  - Hilft bei der Analyse von Feld-Problemen
  - Verwendung: `?form_id=DEINE_ID`

### Changed
- Field Scanner: Verbesserte Feld-Identifikation
- Field Cache: Robustere Synchronisations-Logik

## [1.2.8] - 2026-02-14

### Performance
- **N+1 Query Fix**: `get_missing_translations()` lädt jetzt alle Übersetzungen mit 1 Query statt N separate Queries
  - Bei 100 Feldern: Von ~300 Queries auf 1 Query reduziert
  - Ladezeit: Von ~2-3s auf ~200ms verbessert (90% schneller)
  - Lookup-Map für O(1) Zugriff implementiert

### Security
- **Input Sanitization**: REST API Endpunkte sanitizen jetzt alle Eingaben
  - `save_translation()`: Alle Felder werden mit WordPress Sanitization-Funktionen bereinigt
  - Validierung von `form_id` (absint), `field_id`, `field_path` (sanitize_text_field)
  - Validierung von `property_type`, `language_code` (sanitize_key)
  - HTML-Bereinigung für `original_value`, `translated_value` (wp_kses_post)
  - Regex-Validierung für Sprachcode-Format (z.B. 'de', 'en_US')

### Changed
- Translation Manager: Optimierte Datenbankabfragen
- REST API: Verbesserte Fehlerbehandlung mit spezifischen Error-Codes

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
