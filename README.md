# WSForm Multilingual

Automatische Übersetzungsverwaltung für WSForm mit Polylang-Integration.

## Features

✅ **Auto-Discovery** - Automatisches Scannen aller Formularfelder
✅ **Komplexe Formulare** - Support für Repeater, Conditional Logic, Select-Optionen
✅ **Zentrale Verwaltung** - Datenbank-basierte Übersetzungsverwaltung
✅ **Polylang-Integration** - Nahtlose Integration mit Polylang
✅ **Warnsystem** - Automatische Erkennung fehlender Übersetzungen
✅ **Backend-UI** - Intuitive Admin-Oberfläche
✅ **Performance** - Caching-System für optimale Performance

## Installation

1. Plugin in `/wp-content/plugins/wsform-ml/` hochladen
2. Plugin in WordPress aktivieren
3. Sicherstellen, dass WS Form installiert ist
4. Optional: Polylang für Multi-Language-Support installieren

## Verwendung

### 1. Formular scannen

1. Navigiere zu **WSForm ML** im WordPress-Admin
2. Wähle ein Formular aus der Liste
3. Klicke auf **"Formular scannen"**
4. Das System erkennt automatisch alle übersetzbaren Felder

### 2. Übersetzungen hinzufügen

1. Wähle eine Sprache aus den Tabs
2. Klappe Felder auf, um Eigenschaften zu sehen
3. Gib Übersetzungen ein
4. Klicke auf **"Speichern"**

### 3. Frontend-Rendering

Das Plugin übersetzt Formulare automatisch basierend auf der aktuellen Sprache:

- Nutzt `ws_form_pre_render` Hook
- Lädt Übersetzungen aus der Datenbank
- Wendet Übersetzungen vor dem Rendering an

## Unterstützte Feldtypen

### Basis-Felder
- Text, Email, Textarea
- Number, Tel, URL
- Hidden, Password

### Erweiterte Felder
- **Select, Radio, Checkbox** - Inklusive Optionen
- **Repeater** - Verschachtelte Felder
- **HTML/Text Editor** - Rich Content
- **Conditional Logic** - Bedingte Texte

### Übersetzbare Eigenschaften
- `label` - Feldbezeichnung
- `placeholder` - Platzhalter-Text
- `help` - Hilfetext
- `invalid_feedback` - Fehlermeldung
- `options` - Select/Radio/Checkbox-Optionen
- `html` - HTML-Inhalt
- `aria_label` - Barrierefreiheit

## Datenbank-Schema

### `wsform_ml_translations`
Speichert alle Übersetzungen mit:
- Form ID, Field ID, Field Path
- Property Type (label, placeholder, etc.)
- Language Code
- Original & Translated Value
- Sync-Status

### `wsform_ml_field_cache`
Cache für gescannte Felder:
- Feldstruktur
- Übersetzbare Eigenschaften
- Repeater-Hierarchie
- Letzte Scan-Zeit

### `wsform_ml_scan_log`
Protokoll aller Scans:
- Gefundene/Neue/Aktualisierte Felder
- Scan-Dauer
- Fehler-Logs

## REST API Endpoints

```
GET    /wp-json/wsform-ml/v1/forms
GET    /wp-json/wsform-ml/v1/forms/{id}/fields
GET    /wp-json/wsform-ml/v1/forms/{id}/translations
GET    /wp-json/wsform-ml/v1/forms/{id}/translations/missing
GET    /wp-json/wsform-ml/v1/forms/{id}/stats
POST   /wp-json/wsform-ml/v1/forms/{id}/scan
POST   /wp-json/wsform-ml/v1/translations
POST   /wp-json/wsform-ml/v1/translations/bulk
DELETE /wp-json/wsform-ml/v1/translations/{id}
```

## Hooks & Filter

### Actions
```php
// Nach erfolgreichem Scan
do_action('wsform_ml_after_scan', $form_id, $stats);

// Nach Speichern einer Übersetzung
do_action('wsform_ml_translation_saved', $translation_id, $data);
```

### Filter
```php
// Übersetzbare Eigenschaften anpassen
apply_filters('wsform_ml_translatable_properties', $properties, $field);

// Translation Map vor Rendering anpassen
apply_filters('wsform_ml_translation_map', $map, $form_id, $language);
```

## Performance

- **Caching**: Gescannte Felder werden gecacht
- **Lazy Loading**: Übersetzungen nur bei Bedarf laden
- **Batch Operations**: Bulk-Save für mehrere Übersetzungen
- **Optimierte Queries**: Indizierte Datenbank-Abfragen

## Polylang-Integration

Das Plugin nutzt Polylang-Funktionen:

```php
// Aktuelle Sprache erkennen
pll_current_language()

// Standard-Sprache
pll_default_language()

// Verfügbare Sprachen
pll_languages_list()
```

Funktioniert auch **ohne Polylang** (Fallback auf Englisch).

## Entwicklung

### Struktur
```
wsform-ml/
├── wsform-ml.php (Haupt-Plugin)
├── includes/ (Core-Klassen)
│   ├── class-database.php
│   ├── class-field-scanner.php
│   ├── class-translation-manager.php
│   ├── class-renderer.php
│   └── class-polylang-integration.php
├── admin/ (Backend)
│   ├── class-admin-menu.php
│   ├── class-rest-api.php
│   ├── assets/
│   └── views/
└── languages/ (i18n)
```

### Eigene Felder hinzufügen

```php
add_filter('wsform_ml_translatable_properties', function($properties, $field) {
	if ($field->type === 'custom_field') {
		$properties[] = [
			'type' => 'custom_property',
			'path' => 'meta.custom_property',
			'value' => $field->meta->custom_property
		];
	}
	return $properties;
}, 10, 2);
```

## Anforderungen

- WordPress 5.8+
- PHP 7.4+
- WS Form (beliebige Version)
- Optional: Polylang

## Support

Bei Fragen oder Problemen:
- GitHub Issues
- WordPress Support Forum

## Lizenz

GPL v2 or later

## Changelog

### 1.0.0
- Initial Release
- Auto-Discovery für alle Feldtypen
- Polylang-Integration
- Backend-UI
- REST API
- Warnsystem für fehlende Übersetzungen
