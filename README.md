# WSForm Multilingual

Automatic translation management for WSForm with Polylang integration.

## ⚠️ Important Notice

**This plugin was entirely developed using AI (Cascade/Claude).**

- ✅ **Testing environment required**: Only test on staging environments before production use
- ✅ **Code review recommended**: Code should be reviewed by a developer before production deployment
- ✅ **Create backups**: Always create a complete backup of your WordPress installation before installing
- ✅ **Staging environment**: Ideally test thoroughly on a staging environment first

**Use at your own risk!**

## Features

✅ **Auto-Discovery** - Automatic scanning of all form fields
✅ **Complex Forms** - Support for Repeater, Conditional Logic, Select options
✅ **Centralized Management** - Database-based translation management
✅ **Polylang Integration** - Seamless integration with Polylang
✅ **Warning System** - Automatic detection of missing translations
✅ **Backend UI** - Intuitive admin interface
✅ **Performance** - Caching system for optimal performance

## Installation

1. Upload plugin to `/wp-content/plugins/wsform-ml/`
2. Activate plugin in WordPress
3. Ensure WS Form is installed
4. Optional: Install Polylang for multi-language support

## Usage

### 1. Scan Form

1. Navigate to **WSForm ML** in WordPress Admin
2. Select a form from the list
3. Click **"Scan Form"**
4. The system automatically detects all translatable fields

### 2. Add Translations

1. Select a language from the tabs
2. Expand fields to see properties
3. Enter translations
4. Click **"Save"**

### 3. Frontend Rendering

The plugin automatically translates forms based on the current language:

- Uses `ws_form_pre_render` hook
- Loads translations from database
- Applies translations before rendering

## Supported Field Types

### Basic Fields
- Text, Email, Textarea
- Number, Tel, URL
- Hidden, Password

### Advanced Fields
- **Select, Radio, Checkbox** - Including options
- **Repeater** - Nested fields
- **HTML/Text Editor** - Rich content
- **Conditional Logic** - Conditional texts

### Translatable Properties
- `label` - Field label
- `placeholder` - Placeholder text
- `help` - Help text
- `invalid_feedback` - Error message
- `options` - Select/Radio/Checkbox options
- `html` - HTML content
- `aria_label` - Accessibility

## Database Schema

### `wsform_ml_translations`
Stores all translations with:
- Form ID, Field ID, Field Path
- Property Type (label, placeholder, etc.)
- Language Code
- Original & Translated Value
- Sync Status

### `wsform_ml_field_cache`
Cache for scanned fields:
- Field structure
- Translatable properties
- Repeater hierarchy
- Last scan time

### `wsform_ml_scan_log`
Log of all scans:
- Found/New/Updated fields
- Scan duration
- Error logs

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
