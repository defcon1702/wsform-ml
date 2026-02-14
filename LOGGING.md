# WSForm Multilingual - Logging System

## Übersicht

Das Logging-System ermöglicht zentralisiertes, konfigurierbares Logging mit verschiedenen Log-Levels und optionaler Datei-Ausgabe.

---

## Features

- ✅ **Log-Levels:** DEBUG, INFO, WARNING, ERROR
- ✅ **Ein/Aus-Schalter:** Aktiviere/Deaktiviere Logging global
- ✅ **Datei-Logging:** Optional zu Datei statt nur error_log
- ✅ **Auto-Rotation:** Log-Dateien werden automatisch rotiert (>10MB)
- ✅ **Cleanup:** Alte Logs automatisch löschen
- ✅ **Debug-Modus:** Via Konstante `WSFORM_ML_DEBUG` aktivierbar

---

## Verwendung

### Basic Logging

```php
$logger = WSForm_ML_Logger::instance();

// Debug (nur wenn Log-Level = DEBUG)
$logger->debug('Scanner started', ['form_id' => 123]);

// Info (Standard-Level)
$logger->info('Translation saved', ['field_id' => 45, 'language' => 'en']);

// Warning
$logger->warning('Missing translation', ['field_path' => 'groups.0.fields.1']);

// Error
$logger->error('Database error', ['error' => $wpdb->last_error]);
```

### Exception Logging

```php
try {
    // ... Code ...
} catch (Exception $e) {
    $logger->exception($e, 'Failed to save translation');
}
```

### Kontext-Daten

```php
// Füge Kontext-Daten für besseres Debugging hinzu
$logger->info('Form scanned', [
    'form_id' => $form_id,
    'fields_found' => count($fields),
    'duration' => $duration,
    'user_id' => get_current_user_id()
]);
```

---

## Konfiguration

### Via WordPress Admin

**WSForm ML → Einstellungen → Logging**

- **Logging aktivieren:** Ein/Aus-Schalter
- **Log-Level:** DEBUG, INFO, WARNING, ERROR
- **In Datei loggen:** Aktiviere File-Logging

### Via wp-config.php

```php
// Debug-Modus aktivieren (überschreibt Admin-Einstellungen)
define('WSFORM_ML_DEBUG', true);
```

### Programmatisch

```php
$logger = WSForm_ML_Logger::instance();

$logger->save_settings([
    'enabled' => true,
    'log_level' => WSForm_ML_Logger::DEBUG,
    'log_to_file' => true
]);
```

---

## Log-Levels

### DEBUG
Detaillierte Informationen für Entwickler.

**Beispiele:**
- Scanner-Schritte
- DB-Query Details
- API-Requests/Responses

**Verwendung:**
```php
$logger->debug('Processing field', [
    'field_id' => $field->id,
    'field_type' => $field->type,
    'properties' => $translatable_props
]);
```

### INFO
Allgemeine Informationen über normale Operationen.

**Beispiele:**
- Translation gespeichert
- Form gescannt
- Cache invalidiert

**Verwendung:**
```php
$logger->info('Form scan completed', [
    'form_id' => $form_id,
    'fields_found' => $stats['fields_found']
]);
```

### WARNING
Potenzielle Probleme, die keine Fehler sind.

**Beispiele:**
- Fehlende Übersetzungen
- Deprecated Features
- Performance-Warnungen

**Verwendung:**
```php
$logger->warning('Translation not found', [
    'field_path' => $field_path,
    'language' => $language_code
]);
```

### ERROR
Fehler, die Aufmerksamkeit erfordern.

**Beispiele:**
- DB-Fehler
- API-Fehler
- Exceptions

**Verwendung:**
```php
$logger->error('Failed to save translation', [
    'error' => $wpdb->last_error,
    'data' => $translation_data
]);
```

---

## Log-Dateien

### Speicherort

```
wp-content/uploads/wsform-ml-logs/
├── wsform-ml-2026-02-14.log
├── wsform-ml-2026-02-14.log.old
├── wsform-ml-2026-02-13.log
└── .htaccess (Deny from all)
```

### Format

```
[2026-02-14 15:30:45] [INFO] Translation saved | Context: {"field_id":45,"language":"en"}
[2026-02-14 15:30:46] [DEBUG] Scanner started | Context: {"form_id":123}
[2026-02-14 15:30:50] [ERROR] Database error | Context: {"error":"Duplicate entry"}
```

### Rotation

- **Automatisch:** Wenn Datei > 10MB
- **Alte Datei:** Wird zu `.log.old` umbenannt
- **Cleanup:** Alte Logs werden nach 30 Tagen gelöscht

### Manuelles Cleanup

```php
$logger = WSForm_ML_Logger::instance();

// Lösche Logs älter als 30 Tage
$deleted = $logger->cleanup_old_logs(30);

echo "Deleted {$deleted} old log files";
```

---

## Log-Dateien anzeigen

### Via Code

```php
$logger = WSForm_ML_Logger::instance();

// Liste alle Log-Dateien
$files = $logger->get_log_files();

foreach ($files as $file) {
    echo $file['name'] . ' - ' . size_format($file['size']) . "\n";
}

// Lese letzte 100 Zeilen
$content = $logger->read_log_file('wsform-ml-2026-02-14.log', 100);
echo $content;
```

### Via Admin (geplant für v1.3.0)

**WSForm ML → Einstellungen → Logs**

- Liste aller Log-Dateien
- Download-Button
- Live-View (letzte 100 Zeilen)
- Clear-Button

---

## Migration von error_log()

### Vorher

```php
error_log('WSForm ML: Scanner started - Form ID: ' . $form_id);
error_log('WSForm ML: Found ' . count($fields) . ' fields');
```

### Nachher

```php
$logger = WSForm_ML_Logger::instance();

$logger->debug('Scanner started', ['form_id' => $form_id]);
$logger->info('Fields discovered', ['count' => count($fields)]);
```

### Vorteile

- ✅ Zentraler Ein/Aus-Schalter
- ✅ Strukturierte Kontext-Daten (JSON)
- ✅ Filterbar nach Log-Level
- ✅ Optional zu Datei
- ✅ Bessere Performance (wenn disabled)

---

## Best Practices

### 1. Verwende passende Log-Levels

```php
// ❌ Falsch
$logger->error('Translation saved'); // Kein Error!

// ✅ Richtig
$logger->info('Translation saved');
```

### 2. Füge Kontext hinzu

```php
// ❌ Wenig hilfreich
$logger->error('Save failed');

// ✅ Hilfreich
$logger->error('Save failed', [
    'form_id' => $form_id,
    'field_id' => $field_id,
    'error' => $wpdb->last_error
]);
```

### 3. Vermeide sensible Daten

```php
// ❌ Niemals Passwörter, API-Keys, etc. loggen
$logger->debug('API call', ['api_key' => $api_key]);

// ✅ Logge nur nicht-sensible Daten
$logger->debug('API call', ['endpoint' => $endpoint]);
```

### 4. Nutze DEBUG sparsam

```php
// ❌ Zu verbose
foreach ($fields as $field) {
    $logger->debug('Processing field', ['field' => $field]);
}

// ✅ Zusammenfassung
$logger->debug('Processing fields', ['count' => count($fields)]);
```

### 5. Exception-Logging

```php
// ✅ Immer Exceptions loggen
try {
    $this->save_translation($data);
} catch (Exception $e) {
    $logger->exception($e, 'Translation save failed');
    throw $e; // Re-throw wenn nötig
}
```

---

## Performance

### Overhead

- **Disabled:** ~0ms (früher Return)
- **Enabled (error_log):** ~1-2ms pro Log
- **Enabled (File):** ~2-5ms pro Log

### Optimierung

```php
// ❌ Teure Operation auch wenn Logging disabled
$logger->debug('Data', ['data' => $this->expensive_operation()]);

// ✅ Prüfe erst ob Logging aktiv
if ($logger->get_settings()['enabled']) {
    $logger->debug('Data', ['data' => $this->expensive_operation()]);
}
```

---

## Troubleshooting

### Logs werden nicht geschrieben

**Prüfe:**
1. Ist Logging aktiviert? `WSForm ML → Einstellungen → Logging`
2. Ist Log-Level korrekt? DEBUG < INFO < WARNING < ERROR
3. Sind Schreibrechte vorhanden? `wp-content/uploads/wsform-ml-logs/`

**Debug:**
```php
$logger = WSForm_ML_Logger::instance();
$settings = $logger->get_settings();

var_dump($settings);
// enabled: true
// log_level: 'info'
// log_to_file: true
// log_file_path: '/path/to/uploads/wsform-ml-logs/...'
```

### Log-Datei zu groß

**Automatisch:** Rotation bei >10MB

**Manuell:**
```php
$logger->cleanup_old_logs(7); // Lösche Logs älter als 7 Tage
```

### Zu viele Logs

**Reduziere Log-Level:**
```php
// Von DEBUG zu INFO
$logger->save_settings([
    'enabled' => true,
    'log_level' => WSForm_ML_Logger::INFO
]);
```

---

## Roadmap

### v1.3.0 (aktuell)
- ✅ Logger-Klasse
- ✅ Log-Levels
- ✅ File-Logging
- ✅ Auto-Rotation

### v1.4.0
- ⏳ Admin UI für Log-Viewer
- ⏳ Download-Funktion
- ⏳ Live-Tail (WebSocket)

### v1.5.0
- ⏳ Log-Export (CSV, JSON)
- ⏳ Log-Suche/Filter
- ⏳ Performance-Metrics

---

## API-Referenz

### WSForm_ML_Logger

#### Methoden

**instance()**
```php
$logger = WSForm_ML_Logger::instance();
```

**debug($message, $context = [])**
```php
$logger->debug('Debug message', ['key' => 'value']);
```

**info($message, $context = [])**
```php
$logger->info('Info message', ['key' => 'value']);
```

**warning($message, $context = [])**
```php
$logger->warning('Warning message', ['key' => 'value']);
```

**error($message, $context = [])**
```php
$logger->error('Error message', ['key' => 'value']);
```

**exception($exception, $message = '')**
```php
$logger->exception($e, 'Optional message');
```

**save_settings($settings)**
```php
$logger->save_settings([
    'enabled' => true,
    'log_level' => WSForm_ML_Logger::DEBUG,
    'log_to_file' => true
]);
```

**get_settings()**
```php
$settings = $logger->get_settings();
```

**cleanup_old_logs($days = 30)**
```php
$deleted = $logger->cleanup_old_logs(30);
```

**get_log_files()**
```php
$files = $logger->get_log_files();
```

**read_log_file($filename, $lines = 100)**
```php
$content = $logger->read_log_file('wsform-ml-2026-02-14.log', 100);
```

---

**Letzte Aktualisierung:** 14.02.2026  
**Version:** 1.3.0
