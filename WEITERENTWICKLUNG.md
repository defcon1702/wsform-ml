# WSForm Multilingual - Weiterentwicklung & Roadmap

Dieses Dokument basiert auf dem Code-Audit vom 14.02.2026 und definiert priorisierte Verbesserungen für zukünftige Versionen.

---

## 🔴 PRIORITÄT 1 - Kritische Verbesserungen

### 1.1 Performance: N+1 Query Problem beheben

**Status:** 🔴 Kritisch  
**Aufwand:** 2-3 Stunden  
**Version:** 1.3.0

**Problem:**
`get_missing_translations()` führt für jede translatable Property eine separate DB-Query aus. Bei großen Formularen (100+ Felder) führt dies zu hunderten DB-Queries.

**Lösung:**
```php
// includes/class-translation-manager.php

// VORHER (N+1):
foreach ($translatable_props as $prop) {
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $trans_table WHERE ..."
    )); // ❌ Separate Query für jede Property
}

// NACHHER (1 Query):
// 1. Lade alle Übersetzungen für Form + Sprache auf einmal
$existing_translations = $wpdb->get_results($wpdb->prepare(
    "SELECT field_id, field_path, property_type 
     FROM $trans_table 
     WHERE form_id = %d AND language_code = %s",
    $form_id, $language_code
));

// 2. Erstelle Lookup-Map
$translation_map = [];
foreach ($existing_translations as $trans) {
    $key = "{$trans->field_id}::{$trans->field_path}::{$trans->property_type}";
    $translation_map[$key] = true;
}

// 3. Prüfe gegen Map statt DB
foreach ($translatable_props as $prop) {
    $key = "{$field->field_id}::{$field->field_path}::{$prop['type']}";
    if (!isset($translation_map[$key])) {
        $missing[] = ...;
    }
}
```

**Erwarteter Impact:**
- Bei 100 Feldern: Von ~300 Queries auf 1 Query
- Ladezeit: Von ~2-3s auf ~200ms

---

### 1.2 Logging-System zentralisieren

**Status:** 🔴 Kritisch  
**Aufwand:** 3-4 Stunden  
**Version:** 1.3.0

**Problem:**
- `error_log()` Aufrufe sind über alle Dateien verteilt
- Kein zentraler Ein/Aus-Schalter
- Keine Log-Level (Debug, Info, Warning, Error)
- Produktions-Logs sind zu verbose

**Lösung:**
Neue Klasse `WSForm_ML_Logger` mit:
- Log-Levels: DEBUG, INFO, WARNING, ERROR
- Ein/Aus-Schalter in Settings
- Optionale Log-Datei statt error_log
- Automatisches Log-Rotation

**Siehe:** `includes/class-logger.php` (neu)

---

### 1.3 Translation Memory

**Status:** 🔴 Hoch  
**Aufwand:** 8-10 Stunden  
**Version:** 1.4.0

**Feature:**
Schlage bereits übersetzte Texte vor, wenn der gleiche Quelltext in einem anderen Formular vorkommt.

**Beispiel:**
- Form A: "Vorname" → "First Name" (EN)
- Form B: "Vorname" → **Vorschlag: "First Name"** ✨

**Implementierung:**
```php
class WSForm_ML_Translation_Memory {
    /**
     * Suche nach ähnlichen Übersetzungen
     * 
     * @param string $source_text Original-Text
     * @param string $language Ziel-Sprache
     * @return array Vorschläge mit Confidence-Score
     */
    public function suggest_translation($source_text, $language) {
        global $wpdb;
        
        // 1. Exakte Matches
        $exact = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT original_value, translated_value, COUNT(*) as usage_count
             FROM {$wpdb->prefix}wsform_ml_translations
             WHERE original_value = %s AND language_code = %s
             GROUP BY translated_value
             ORDER BY usage_count DESC
             LIMIT 5",
            $source_text, $language
        ));
        
        // 2. Fuzzy Matches (Levenshtein-Distanz)
        // Für ähnliche Texte wie "Vorname" vs "Vor-Name"
        
        return [
            'exact' => $exact,
            'fuzzy' => $fuzzy,
            'confidence' => $this->calculate_confidence($exact, $fuzzy)
        ];
    }
}
```

**UI-Integration:**
- Zeige Vorschläge als Dropdown unter Übersetzungsfeld
- "Vorschlag übernehmen" Button
- Zeige Häufigkeit: "3x verwendet in anderen Formularen"

---

### 1.4 Import/Export für Übersetzer

**Status:** 🔴 Hoch  
**Aufwand:** 6-8 Stunden  
**Version:** 1.4.0

**Feature:**
Exportiere Übersetzungen als CSV/XLSX für externe Übersetzer, importiere sie zurück.

**Format:**
```csv
Field Path,Field Type,Original (DE),English (EN),Spanish (ES),Status
groups.0.sections.0.fields.0,label,Vorname,First Name,Nombre,translated
groups.0.sections.0.fields.1,label,Nachname,,Apellido,partial
groups.0.sections.0.fields.2,help,Trage Deinen Namen ein,,,missing
```

**Implementierung:**
```php
class WSForm_ML_Exporter {
    public function export_to_csv($form_id, $languages) {
        // Erstelle CSV mit allen Feldern und Übersetzungen
    }
    
    public function import_from_csv($form_id, $file) {
        // Validiere CSV
        // Importiere Übersetzungen
        // Zeige Zusammenfassung: X importiert, Y übersprungen, Z Fehler
    }
}
```

**UI:**
- Export-Button in Settings: "Übersetzungen exportieren"
- Import-Button mit File-Upload
- Preview vor Import: Zeige Änderungen

---

## 🟡 PRIORITÄT 2 - Wichtige Verbesserungen

### 2.1 Input Sanitization verbessern

**Status:** 🟡 Mittel  
**Aufwand:** 2-3 Stunden  
**Version:** 1.3.0

**Problem:**
REST API sanitized Input nicht ausreichend.

**Lösung:**
```php
// admin/class-rest-api.php

public function save_translation($request) {
    $data = $request->get_json_params();
    
    // Sanitize Input
    $sanitized = [
        'form_id' => absint($data['form_id'] ?? 0),
        'field_id' => sanitize_text_field($data['field_id'] ?? ''),
        'field_path' => sanitize_text_field($data['field_path'] ?? ''),
        'property_type' => sanitize_key($data['property_type'] ?? ''),
        'language_code' => sanitize_key($data['language_code'] ?? ''),
        'original_value' => wp_kses_post($data['original_value'] ?? ''),
        'translated_value' => wp_kses_post($data['translated_value'] ?? '')
    ];
    
    // Validiere
    if (!$sanitized['form_id'] || !$sanitized['language_code']) {
        return new WP_Error('invalid_data', 'Ungültige Daten', ['status' => 400]);
    }
    
    // Speichere
    return $this->translation_manager->save_translation($sanitized);
}
```

---

### 2.2 Transient Cache für Forms-Liste

**Status:** 🟡 Mittel  
**Aufwand:** 1-2 Stunden  
**Version:** 1.3.0

**Problem:**
Forms-Liste wird bei jedem Seitenaufruf neu geladen.

**Lösung:**
```php
public function get_forms($request) {
    $cache_key = 'wsform_ml_forms_list_v2';
    $cached = get_transient($cache_key);
    
    if ($cached !== false && !isset($_GET['refresh'])) {
        return rest_ensure_response($cached);
    }
    
    // ... Query ausführen ...
    
    set_transient($cache_key, $forms, 5 * MINUTE_IN_SECONDS);
    
    return rest_ensure_response($forms);
}

// Cache invalidieren bei Änderungen:
public function scan_form($request) {
    // ... Scan durchführen ...
    
    delete_transient('wsform_ml_forms_list_v2');
    
    return rest_ensure_response($result);
}
```

---

### 2.3 DB-Indizes optimieren

**Status:** 🟡 Mittel  
**Aufwand:** 1 Stunde  
**Version:** 1.3.0

**Prüfe und erstelle fehlende Indizes:**

```sql
-- Translations-Tabelle
ALTER TABLE wp_wsform_ml_translations 
ADD INDEX idx_form_lang (form_id, language_code);

ALTER TABLE wp_wsform_ml_translations 
ADD INDEX idx_field_lookup (form_id, field_id, field_path, property_type);

-- Field Cache
ALTER TABLE wp_wsform_ml_field_cache 
ADD INDEX idx_form_field (form_id, field_id);

ALTER TABLE wp_wsform_ml_field_cache 
ADD INDEX idx_form_path (form_id, field_path);
```

**Migration:**
Füge zu `includes/class-database.php` hinzu:
```php
private function add_indexes() {
    global $wpdb;
    
    $queries = [
        "ALTER TABLE {$this->get_table_name(self::TABLE_TRANSLATIONS)} 
         ADD INDEX IF NOT EXISTS idx_form_lang (form_id, language_code)",
        // ... weitere Indizes
    ];
    
    foreach ($queries as $query) {
        $wpdb->query($query);
    }
}
```

---

### 2.4 Inline-Editing UX

**Status:** 🟡 Mittel  
**Aufwand:** 10-12 Stunden  
**Version:** 1.5.0

**Feature:**
Direktes Editieren der Übersetzungen im Formular-Preview.

**Mockup:**
```
┌─────────────────────────────────────────┐
│ Original (DE)    │ Übersetzung (EN)     │
├─────────────────────────────────────────┤
│ [Vorname      ]  │ [First Name      ] ✓ │
│ [Nachname     ]  │ [Last Name       ] ✓ │
│ [E-Mail       ]  │ [Email           ] ✓ │
│ [Telefon      ]  │ [                ] ⚠ │ ← Fehlend
└─────────────────────────────────────────┘
```

**Features:**
- Split-Screen: Original links, Übersetzung rechts
- Inline-Editing mit Auto-Save
- Farbcodierung: Grün = übersetzt, Rot = fehlend
- Keyboard-Navigation: Tab = nächstes Feld

---

### 2.5 Auto-Translation API

**Status:** 🟡 Mittel  
**Aufwand:** 8-10 Stunden  
**Version:** 1.5.0

**Feature:**
Integration mit DeepL, Google Translate oder OpenAI für automatische Vorschläge.

**Implementierung:**
```php
class WSForm_ML_Auto_Translator {
    private $api_key;
    private $provider; // 'deepl', 'google', 'openai'
    
    public function translate_missing($form_id, $source_lang, $target_lang) {
        $missing = $this->get_missing_translations($form_id, $target_lang);
        
        foreach ($missing as $item) {
            $translation = $this->call_api(
                $item['original_value'],
                $source_lang,
                $target_lang
            );
            
            // Speichere als "auto-translated" (User kann nachbearbeiten)
            $this->save_auto_translation($item, $translation);
        }
    }
    
    private function call_api($text, $source, $target) {
        switch ($this->provider) {
            case 'deepl':
                return $this->deepl_translate($text, $source, $target);
            case 'google':
                return $this->google_translate($text, $source, $target);
            case 'openai':
                return $this->openai_translate($text, $source, $target);
        }
    }
}
```

**UI:**
- Settings: API-Key Eingabe
- Button: "Fehlende Übersetzungen automatisch vorschlagen"
- Markierung: Auto-übersetzte Texte mit Icon kennzeichnen
- Review-Modus: User kann Auto-Übersetzungen durchgehen und bestätigen

---

## 🟢 PRIORITÄT 3 - Nice-to-have Features

### 3.1 Rate Limiting

**Status:** 🟢 Niedrig  
**Aufwand:** 2-3 Stunden  
**Version:** 1.6.0

**Implementierung:**
```php
class WSForm_ML_Rate_Limiter {
    public function check_limit($user_id, $action, $max_requests = 100, $time_window = 3600) {
        $key = "wsform_ml_rate_{$action}_{$user_id}";
        $count = get_transient($key) ?: 0;
        
        if ($count >= $max_requests) {
            return new WP_Error('rate_limit', 'Zu viele Anfragen', ['status' => 429]);
        }
        
        set_transient($key, $count + 1, $time_window);
        return true;
    }
}
```

---

### 3.2 Debouncing für Auto-Save

**Status:** 🟢 Niedrig  
**Aufwand:** 1 Stunde  
**Version:** 1.3.0

**Problem:**
Jede Eingabe triggert sofort einen Save-Request.

**Lösung:**
```javascript
// admin/assets/js/admin.js

let saveTimeout;
const DEBOUNCE_DELAY = 500; // ms

input.addEventListener('input', () => {
    clearTimeout(saveTimeout);
    
    // Zeige "Speichert..." Indikator
    this.showSavingIndicator(input);
    
    saveTimeout = setTimeout(() => {
        this.saveTranslation(...);
    }, DEBOUNCE_DELAY);
});
```

---

### 3.3 Version Control für Übersetzungen

**Status:** 🟢 Niedrig  
**Aufwand:** 6-8 Stunden  
**Version:** 1.6.0

**Feature:**
Speichere Übersetzungs-Historie, ermögliche Rollback.

**Schema-Änderung:**
```sql
ALTER TABLE wp_wsform_ml_translations 
ADD COLUMN version INT DEFAULT 1,
ADD COLUMN previous_value TEXT,
ADD COLUMN changed_by BIGINT,
ADD COLUMN changed_at DATETIME;

-- Neue Tabelle für Historie
CREATE TABLE wp_wsform_ml_translation_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    translation_id BIGINT NOT NULL,
    version INT NOT NULL,
    translated_value TEXT,
    changed_by BIGINT,
    changed_at DATETIME,
    INDEX idx_translation (translation_id)
);
```

**UI:**
- "Historie anzeigen" Button bei jedem Feld
- Modal mit Versions-Liste
- "Zu dieser Version zurückkehren" Button

---

### 3.4 Multi-User Collaboration

**Status:** 🟢 Niedrig  
**Aufwand:** 10-12 Stunden  
**Version:** 1.7.0

**Feature:**
Zeige wer gerade welches Feld übersetzt, verhindere Konflikte.

**Implementierung:**
```php
class WSForm_ML_Collaboration {
    public function lock_field($form_id, $field_path, $user_id) {
        $key = "wsform_ml_lock_{$form_id}_{$field_path}";
        $lock = get_transient($key);
        
        if ($lock && $lock !== $user_id) {
            $user = get_userdata($lock);
            return new WP_Error('locked', "Feld wird bearbeitet von {$user->display_name}");
        }
        
        set_transient($key, $user_id, 5 * MINUTE_IN_SECONDS);
        return true;
    }
}
```

**UI:**
- Zeige Avatar des bearbeitenden Users
- "Übernehmen" Button wenn Lock abgelaufen
- Heartbeat API für Live-Updates

---

### 3.5 Unit Tests

**Status:** 🟢 Niedrig  
**Aufwand:** 15-20 Stunden  
**Version:** 1.6.0

**Setup:**
```bash
composer require --dev phpunit/phpunit
composer require --dev brain/monkey
```

**Test-Struktur:**
```
tests/
├── Unit/
│   ├── TranslationManagerTest.php
│   ├── FieldScannerTest.php
│   └── RendererTest.php
├── Integration/
│   ├── RestApiTest.php
│   └── DatabaseTest.php
└── bootstrap.php
```

**Beispiel:**
```php
class TranslationManagerTest extends TestCase {
    public function test_save_translation() {
        $manager = WSForm_ML_Translation_Manager::instance();
        
        $result = $manager->save_translation([
            'form_id' => 1,
            'field_id' => 3,
            'language_code' => 'en',
            'translated_value' => 'First Name'
        ]);
        
        $this->assertTrue($result);
    }
}
```

---

### 3.6 Service Layer Refactoring

**Status:** 🟢 Niedrig  
**Aufwand:** 8-10 Stunden  
**Version:** 1.7.0

**Ziel:**
Trenne Business-Logic von REST-Controllern.

**Vorher:**
```php
// admin/class-rest-api.php
public function save_translation($request) {
    $data = $request->get_json_params();
    
    // Validierung
    // Business-Logic
    // DB-Zugriff
    // Response
}
```

**Nachher:**
```php
// includes/services/class-translation-service.php
class WSForm_ML_Translation_Service {
    public function saveTranslation($data) {
        // Validierung
        $this->validate($data);
        
        // Business-Logic
        $translation = $this->prepareTranslation($data);
        
        // DB-Zugriff
        $result = $this->repository->save($translation);
        
        // Events
        do_action('wsform_ml_translation_saved', $translation);
        
        return $result;
    }
}

// admin/class-rest-api.php (wird dünner)
public function save_translation($request) {
    $service = new WSForm_ML_Translation_Service();
    return $service->saveTranslation($request->get_json_params());
}
```

---

## 📊 Analytics & Monitoring

### 4.1 Übersetzungs-Analytics

**Status:** 🟢 Niedrig  
**Aufwand:** 4-6 Stunden  
**Version:** 1.6.0

**Features:**
- Dashboard mit Statistiken
- Welche Felder werden am häufigsten übersetzt?
- Welche Sprachen sind vollständig?
- Durchschnittliche Zeit pro Übersetzung
- Aktivste Übersetzer

**UI:**
```
┌─────────────────────────────────────┐
│ Übersetzungs-Statistiken            │
├─────────────────────────────────────┤
│ Deutsch:   ████████████ 100% (45/45)│
│ English:   ████████░░░░  75% (34/45)│
│ Español:   ████░░░░░░░░  40% (18/45)│
│                                     │
│ Meist übersetzte Felder:            │
│ 1. Vorname (12x)                    │
│ 2. Nachname (12x)                   │
│ 3. E-Mail (11x)                     │
└─────────────────────────────────────┘
```

---

### 4.2 Error Tracking Integration

**Status:** 🟢 Niedrig  
**Aufwand:** 2-3 Stunden  
**Version:** 1.6.0

**Integration mit Sentry, Rollbar, etc.:**
```php
class WSForm_ML_Error_Handler {
    public function init() {
        if (defined('WSFORM_ML_SENTRY_DSN')) {
            \Sentry\init(['dsn' => WSFORM_ML_SENTRY_DSN]);
        }
    }
    
    public function log_error($exception) {
        if (function_exists('\\Sentry\\captureException')) {
            \Sentry\captureException($exception);
        }
        
        error_log($exception->getMessage());
    }
}
```

---

## 🎯 Roadmap Timeline

### Version 1.3.0 (März 2026) - Performance & Logging
- ✅ N+1 Query Fix
- ✅ Logger-Klasse
- ✅ Input Sanitization
- ✅ Transient Cache
- ✅ DB-Indizes
- ✅ Debouncing

**Aufwand:** ~15 Stunden

---

### Version 1.4.0 (April 2026) - Translator Features
- ✅ Translation Memory
- ✅ Import/Export CSV
- ✅ Bulk-Actions

**Aufwand:** ~25 Stunden

---

### Version 1.5.0 (Mai 2026) - UX Improvements
- ✅ Inline-Editing
- ✅ Auto-Translation API
- ✅ Progress Indicators

**Aufwand:** ~30 Stunden

---

### Version 1.6.0 (Juni 2026) - Advanced Features
- ✅ Version Control
- ✅ Analytics Dashboard
- ✅ Unit Tests
- ✅ Rate Limiting

**Aufwand:** ~35 Stunden

---

### Version 1.7.0 (Juli 2026) - Enterprise Features
- ✅ Multi-User Collaboration
- ✅ Service Layer Refactoring
- ✅ Advanced Permissions

**Aufwand:** ~30 Stunden

---

## 📝 Notizen

### Breaking Changes vermeiden
- Alle DB-Schema-Änderungen müssen Migrations unterstützen
- REST API: Neue Endpoints, keine Änderungen an bestehenden
- Settings: Neue Optionen mit sinnvollen Defaults

### Backward Compatibility
- Mindestens 2 Major-Versions Support für alte APIs
- Deprecation Warnings vor Breaking Changes
- Migration-Guides in CHANGELOG.md

### Testing vor Release
- Manuelle Tests auf WordPress 5.8+
- PHP 7.4, 8.0, 8.1, 8.2 Kompatibilität
- WSForm Pro Kompatibilität prüfen
- Polylang Kompatibilität prüfen

---

**Letzte Aktualisierung:** 14.02.2026  
**Nächste Review:** Nach Version 1.3.0
