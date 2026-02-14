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

**🔒 Security:** All endpoints require `manage_options` capability (WordPress Admin only). Not accessible from outside.

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
// After successful scan
do_action('wsform_ml_after_scan', $form_id, $stats);

// After saving a translation
do_action('wsform_ml_translation_saved', $translation_id, $data);
```

### Filter
```php
// Customize translatable properties
apply_filters('wsform_ml_translatable_properties', $properties, $field);

// Customize translation map before rendering
apply_filters('wsform_ml_translation_map', $map, $form_id, $language);
```

## Performance

- **Caching**: Scanned fields are cached (5-minute transient cache for forms list)
- **Auto-Save**: Debounced auto-save (500ms) with visual indicators
- **N+1 Query Fix**: All translations loaded in single query
- **Batch Operations**: Bulk-save for multiple translations
- **Optimized Queries**: Indexed database queries
- **Lazy Loading**: Translations loaded only when needed

## Polylang Integration

The plugin uses Polylang functions:

```php
// Detect current language
pll_current_language()

// Default language
pll_default_language()

// Available languages
pll_languages_list()
```

Also works **without Polylang** (fallback to English).

## Development

### Structure
```
wsform-ml/
├── wsform-ml.php (Main Plugin)
├── includes/ (Core Classes)
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

### Adding Custom Fields

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

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WS Form (any version)
- Optional: Polylang

## Support

For questions or issues:
- GitHub Issues
- WordPress Support Forum

## License

GPL v2 or later

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history.

### 1.7.0 (Latest)
- Auto-Save with Debouncing (500ms)
- Transient Cache for Forms List (5 min)
- Plugin Internationalization (de_DE, en_US)
- Visual Save Indicators
- Performance Optimizations

### 1.6.3
- HTML Field Support
- Numeric Field Sorting

### 1.0.0
- Initial Release
- Auto-Discovery for all field types
- Polylang Integration
- Backend UI
- REST API
- Warning system for missing translations
