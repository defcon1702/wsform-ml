# Native WSForm Translation API Integration

## Übersicht

Das Plugin unterstützt jetzt **zwei Translation-Systeme**, die parallel oder einzeln laufen können:

### 1. Legacy Renderer (Standard)
- Nutzt `wsf_pre_render` Hook
- Bewährt und stabil
- Funktioniert mit allen WSForm Versionen

### 2. Native API Adapter (Neu)
- Nutzt WSForms offizielle Translation API
- Hooks: `wsf_translate`, `wsf_translate_register`
- Tiefere Integration mit WSForm
- Benötigt `WS_Form_Translate` Klasse

## Feature Management

### Admin-Einstellungen
Gehe zu: **WSForm Multilingual → Einstellungen**

Dort kannst du Features aktivieren/deaktivieren:
- ✅ Native WSForm API
- ✅ Legacy Renderer

### Beide Features parallel nutzen
Beide Systeme können gleichzeitig aktiv sein. Dies ist nützlich für:
- Migration von Legacy zu Native API
- A/B Testing
- Fallback-Sicherheit

## Architektur

```
wsform-ml/
├── includes/
│   ├── class-feature-manager.php      # Feature-Toggle System
│   ├── class-native-adapter.php       # Native API Integration
│   ├── class-renderer.php             # Legacy Renderer
│   └── ...
├── admin/
│   ├── class-settings-page.php        # Einstellungs-UI
│   └── ...
└── wsform-ml.php                      # Hauptdatei
```

## Klassen-Übersicht

### WSForm_ML_Feature_Manager
Verwaltet Feature-Toggles:
```php
$manager = WSForm_ML_Feature_Manager::instance();

// Feature aktivieren
$manager->enable(WSForm_ML_Feature_Manager::FEATURE_NATIVE_API);

// Feature deaktivieren
$manager->disable(WSForm_ML_Feature_Manager::FEATURE_LEGACY_RENDERER);

// Status prüfen
if ($manager->is_enabled(WSForm_ML_Feature_Manager::FEATURE_NATIVE_API)) {
    // Native API ist aktiv
}
```

### WSForm_ML_Native_Adapter
Integriert mit WSForm's Translation API:
```php
$adapter = WSForm_ML_Native_Adapter::instance();

// Manuell aktivieren
$adapter->enable();

// Status prüfen
if ($adapter->is_enabled()) {
    // Native API läuft
}
```

## WSForm Translation API Hooks

### wsf_translate (Filter)
Wird für **jeden String** aufgerufen:
```php
add_filter('wsf_translate', function($string_value, $string_id, $form_id, $form_label) {
    // $string_id Format: 'wsf-field-123-label'
    // Gib übersetzten String zurück
    return $translated_value ?: $string_value;
}, 10, 4);
```

### wsf_translate_register (Action)
Wird beim Registrieren von Strings aufgerufen:
```php
add_action('wsf_translate_register', function($string_value, $string_id, $string_label, $type, $form_id, $form_label) {
    // Automatisches Scanning
    // Speichere String in Datenbank
}, 10, 6);
```

### String-ID Format
WSForm nutzt folgendes Format:
```
wsf-{object_type}-{object_id}-{property}

Beispiele:
- wsf-field-123-label
- wsf-field-123-placeholder
- wsf-field-456-invalid-feedback
- wsf-section-45-label
- wsf-group-12-label
```

## Migration von Legacy zu Native API

### Schritt 1: Parallel betreiben
1. Aktiviere **beide Features** in den Einstellungen
2. Teste ob Übersetzungen korrekt angezeigt werden
3. Prüfe Debug-Logs

### Schritt 2: Native API testen
1. Deaktiviere **Legacy Renderer**
2. Nur **Native API** läuft
3. Teste alle Formulare und Sprachen

### Schritt 3: Migration abschließen
1. Wenn alles funktioniert: Behalte nur Native API
2. Bei Problemen: Reaktiviere Legacy Renderer

## Vorteile Native API

✅ **Tiefere Integration**
- Nutzt WSForms eigene Translation-Infrastruktur
- Konsistente String-IDs

✅ **Automatisches Scanning**
- `wsf_translate_register` Hook erfasst automatisch Strings
- Kein manuelles Scannen nötig

✅ **Zukunftssicher**
- Wenn WSForm Updates macht, bleiben wir kompatibel
- Offizielle API statt Workarounds

✅ **Performance**
- Früher im Rendering-Prozess
- Weniger Overhead

## Debugging

### Debug-Logs aktivieren
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Native API Logs
```
WSForm ML Native: Translating string_id=wsf-field-123-label, lang=en
WSForm ML Native: Found translation for wsf-field-123-label
WSForm ML Native: Registering string_id=wsf-field-456-placeholder, type=text
```

### Legacy Renderer Logs
```
WSForm ML: translate_form called - Form ID: 4 - Preview: no
WSForm ML: Current language: en
WSForm ML: Found 8 translations for language: en
WSForm ML: Translations applied
```

## Bekannte Einschränkungen

### Native API
- ⚠️ Benötigt WSForm mit `WS_Form_Translate` Klasse
- ⚠️ String-ID Parsing könnte bei komplexen Strukturen fehlschlagen
- ⚠️ Noch in Beta-Phase

### Legacy Renderer
- ⚠️ Weniger tief integriert
- ⚠️ Manuelles Scannen erforderlich

## Support & Entwicklung

### Feature-Request
Neue Features können über GitHub Issues angefragt werden.

### Bugs melden
Bei Problemen bitte Debug-Logs beifügen und angeben:
- Welches Feature aktiv ist (Native API / Legacy Renderer)
- WSForm Version
- Polylang Version

## Changelog

### Version 1.1.0
- ✨ Native WSForm Translation API Integration
- ✨ Feature-Toggle System
- ✨ Settings-Seite für Feature Management
- ✨ Parallelbetrieb beider Systeme möglich
