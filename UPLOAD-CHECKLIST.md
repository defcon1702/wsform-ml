# Upload-Checkliste für v1.2.0

## ⚠️ WICHTIG: Diese Dateien MÜSSEN hochgeladen werden!

### 1. Sprachfeld-Integration (fehlt auf Server)

**Neue Datei:**
```
includes/class-language-field-manager.php
```
**Warum:** Diese Klasse ist NEU und existiert nur lokal. Ohne sie funktioniert die Sprachfeld-Integration nicht.

**Geänderte Dateien:**
```
admin/class-settings-page.php
wsform-ml.php
```
**Warum:** Diese Dateien laden und nutzen die neue Language Field Manager Klasse.

### 2. Akkordion-Markierung & Toggle-Button

**Geänderte Dateien:**
```
admin/assets/js/admin.js
admin/assets/css/admin.css
```
**Warum:** 
- Akkordion-Markierung für unübersetzte Felder
- "Alle ausklappen/einklappen" Button
- Korrektes Icon (dashicons-database-export)

### 3. Tabs-Problem Fix

**Geänderte Datei:**
```
includes/class-field-scanner.php
```
**Warum:** Fix für verschwindende Tabs beim Scannen

### 4. Scan-Fehler Fix

**Geänderte Datei:**
```
admin/class-rest-api.php
```
**Warum:** Output Buffer Handling für saubere JSON-Responses

### 5. Versionierung

**Neue Dateien:**
```
VERSION
CHANGELOG.md
VERSIONING.md
```
**Warum:** Dokumentation und Versionsverwaltung

---

## 📋 Vollständige Upload-Liste

### NEUE Dateien (MÜSSEN hochgeladen werden):
1. ✅ `includes/class-language-field-manager.php`
2. ✅ `VERSION`
3. ✅ `CHANGELOG.md`
4. ✅ `VERSIONING.md`

### GEÄNDERTE Dateien (MÜSSEN hochgeladen werden):
1. ✅ `wsform-ml.php` (Version 1.2.0 + Language Field Manager laden)
2. ✅ `admin/class-settings-page.php` (Sprachfeld-Integration UI)
3. ✅ `admin/class-rest-api.php` (Scan-Fehler Fix)
4. ✅ `includes/class-field-scanner.php` (Tabs-Fix)
5. ✅ `admin/assets/js/admin.js` (Akkordion-Markierung + Toggle + Icon)
6. ✅ `admin/assets/css/admin.css` (Akkordion-Styling)

---

## 🔍 Warum siehst du keine Änderungen?

### Problem 1: Sprachfeld-Integration nicht sichtbar
**Ursache:** `includes/class-language-field-manager.php` fehlt auf Server
**Lösung:** Datei hochladen

### Problem 2: Tabs verschwinden weiterhin
**Ursache:** `includes/class-field-scanner.php` nicht aktualisiert
**Lösung:** Datei hochladen

### Problem 3: Akkordion-Markierung nicht sichtbar
**Ursache:** `admin/assets/js/admin.js` und `admin/assets/css/admin.css` nicht aktualisiert
**Lösung:** Beide Dateien hochladen

---

## ✅ Nach dem Upload prüfen:

1. **Browser-Cache leeren** (Strg+F5 / Cmd+Shift+R)
2. **WSForm ML → Einstellungen öffnen**
   - Scrolle nach unten
   - "Sprachfeld-Integration" sollte sichtbar sein
3. **Form scannen**
   - Tabs sollten konsistent bleiben
   - Keine Duplicate Entry Fehler
4. **Übersetzungen öffnen**
   - Unübersetzte Akkordions haben orange Border + ⚠ Symbol
   - "Alle ausklappen/einklappen" Button vorhanden
   - Speicher-Button hat Disketten-Symbol

---

## 🐛 Debugging

Falls nach Upload immer noch Probleme:

### Check 1: Datei wirklich hochgeladen?
```bash
# Auf Server prüfen:
ls -la includes/class-language-field-manager.php
```

### Check 2: PHP-Fehler prüfen
```bash
# WordPress Debug Log:
tail -f wp-content/debug.log
```

### Check 3: Browser-Console prüfen
```
F12 → Console → Fehler?
```

### Check 4: Plugin-Version prüfen
```
WordPress Admin → Plugins → WSForm Multilingual
Version sollte sein: 1.2.0
```

---

## 📞 Support

Falls weiterhin Probleme:
1. Prüfe ob ALLE 10 Dateien hochgeladen wurden
2. Leere Browser-Cache komplett
3. Deaktiviere/Aktiviere Plugin neu
4. Prüfe PHP-Fehlerlog
