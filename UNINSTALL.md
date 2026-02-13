# Deinstallations-Anleitung

## Automatische Deinstallation (Empfohlen)

Das Plugin kann über WordPress vollständig deinstalliert werden:

1. **WordPress Admin** → Plugins
2. Plugin **deaktivieren**
3. **Löschen** klicken

Die `uninstall.php` wird automatisch ausgeführt und entfernt:
- ✅ Alle Datenbank-Tabellen
- ✅ Alle Plugin-Optionen
- ✅ Alle Daten in Multisite-Installationen

## Manuelle Deinstallation

Falls die automatische Deinstallation nicht funktioniert:

### 1. Plugin-Dateien löschen

```bash
rm -rf wp-content/plugins/wsform-ml/
```

### 2. Datenbank-Tabellen löschen

```sql
DROP TABLE IF EXISTS wp_wsform_ml_translations;
DROP TABLE IF EXISTS wp_wsform_ml_field_cache;
DROP TABLE IF EXISTS wp_wsform_ml_scan_log;
```

**Hinweis**: Ersetze `wp_` mit deinem Tabellen-Präfix.

### 3. Plugin-Optionen löschen

```sql
DELETE FROM wp_options WHERE option_name LIKE 'wsform_ml_%';
```

### 4. Multisite: Für alle Sites wiederholen

```sql
-- Für jede Site-ID (z.B. 2, 3, 4...)
DROP TABLE IF EXISTS wp_2_wsform_ml_translations;
DROP TABLE IF EXISTS wp_2_wsform_ml_field_cache;
DROP TABLE IF EXISTS wp_2_wsform_ml_scan_log;

DELETE FROM wp_2_options WHERE option_name LIKE 'wsform_ml_%';
```

## Was wird NICHT gelöscht

- ❌ WS Form Formulare (bleiben unverändert)
- ❌ WS Form Submissions (bleiben unverändert)
- ❌ Polylang Einstellungen (bleiben unverändert)

## Daten-Export vor Deinstallation

Falls du die Übersetzungen sichern möchtest:

### Export via phpMyAdmin

1. Tabelle `wp_wsform_ml_translations` auswählen
2. **Exportieren** → SQL-Format
3. Datei speichern

### Export via WP-CLI

```bash
wp db export wsform-ml-backup.sql --tables=wp_wsform_ml_translations,wp_wsform_ml_field_cache,wp_wsform_ml_scan_log
```

### Re-Import nach Neuinstallation

```bash
wp db import wsform-ml-backup.sql
```

## Teilweise Deinstallation

### Nur Cache löschen (Übersetzungen behalten)

```sql
TRUNCATE TABLE wp_wsform_ml_field_cache;
TRUNCATE TABLE wp_wsform_ml_scan_log;
```

### Nur Übersetzungen einer Sprache löschen

```sql
DELETE FROM wp_wsform_ml_translations WHERE language_code = 'de';
```

### Nur Übersetzungen eines Formulars löschen

```sql
DELETE FROM wp_wsform_ml_translations WHERE form_id = 123;
DELETE FROM wp_wsform_ml_field_cache WHERE form_id = 123;
```

## Troubleshooting

### Plugin lässt sich nicht deinstallieren

1. **FTP/SSH**: Dateien manuell löschen
2. **Datenbank**: Tabellen manuell löschen (siehe oben)
3. **wp_options**: Plugin-Einträge manuell löschen

### "Tabelle existiert nicht" Fehler

Normal wenn Plugin bereits teilweise deinstalliert wurde. Ignorieren oder:

```sql
DROP TABLE IF EXISTS wp_wsform_ml_translations;
```

### Multisite: Fehler bei Site-Wechsel

```php
// In uninstall.php auskommentieren:
// if (is_multisite()) { ... }
```

## Support

Bei Problemen:
- GitHub Issues
- WordPress Support Forum
- Debug-Script: `wp-content/plugins/wsform-ml/debug-info.php`
