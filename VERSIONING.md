# Versionierungs-Guidelines

## Semantic Versioning (SemVer)

Dieses Projekt folgt [Semantic Versioning 2.0.0](https://semver.org/lang/de/)

Format: **MAJOR.MINOR.PATCH** (z.B. `1.2.0`)

### Version-Komponenten

- **MAJOR** (z.B. `2.0.0`): Breaking Changes
  - Inkompatible API-Änderungen
  - Datenbank-Schema-Änderungen die Migration erfordern
  - Entfernung von Features oder Funktionen
  - Änderungen die bestehende Installationen brechen könnten

- **MINOR** (z.B. `1.3.0`): Neue Features
  - Neue Funktionalität (abwärtskompatibel)
  - Neue Klassen oder Methoden
  - Neue Admin-UI-Features
  - Neue Hooks oder Filter
  - Performance-Verbesserungen

- **PATCH** (z.B. `1.2.1`): Bugfixes
  - Fehlerbehebungen (abwärtskompatibel)
  - Sicherheits-Patches
  - Kleine UI-Fixes
  - Dokumentations-Updates

## Workflow für neue Versionen

### 1. Änderungen implementieren

Arbeite an Features/Fixes in separaten Branches oder Commits.

### 2. Version bestimmen

Entscheide basierend auf den Änderungen:

- **Breaking Change?** → MAJOR erhöhen
- **Neues Feature?** → MINOR erhöhen
- **Nur Bugfixes?** → PATCH erhöhen

### 3. Dateien aktualisieren

**a) VERSION Datei:**

```bash
echo "1.3.0" > VERSION
```

**b) CHANGELOG.md:**

```markdown
## [1.3.0] - 2026-02-15

### Added

- Neue Feature-Beschreibung

### Fixed

- Bugfix-Beschreibung
```

**c) wsform-ml.php:**

```php
* Version: 1.3.0
...
define('WSFORM_ML_VERSION', '1.3.0');
```

### 4. Git Commit & Tag

```bash
# Alle Änderungen committen
git add VERSION CHANGELOG.md wsform-ml.php
git commit -m "Release v1.3.0"

# Version taggen
git tag -a v1.3.0 -m "Version 1.3.0 - Feature XYZ"

# Push mit Tags
git push origin main --tags
```

## Beispiele

### Beispiel 1: Bugfix (PATCH)

**Änderung:** Scan-Fehler behoben

```
1.2.0 → 1.2.1
```

**CHANGELOG.md:**

```markdown
## [1.2.1] - 2026-02-15

### Fixed

- **Scan-Fehler**: Output Buffer Handling korrigiert
```

### Beispiel 2: Neues Feature (MINOR)

**Änderung:** Export-Funktion für Übersetzungen

```
1.2.1 → 1.3.0
```

**CHANGELOG.md:**

```markdown
## [1.3.0] - 2026-02-16

### Added

- **Export-Funktion**: Übersetzungen als CSV/JSON exportieren
  - Neuer Button im Admin-Interface
  - REST API Endpoint für Export
```

### Beispiel 3: Breaking Change (MAJOR)

**Änderung:** Datenbank-Schema komplett überarbeitet

```
1.3.0 → 2.0.0
```

**CHANGELOG.md:**

```markdown
## [2.0.0] - 2026-03-01

### Changed

- **BREAKING**: Datenbank-Schema überarbeitet
  - Migration erforderlich
  - Alte Übersetzungen müssen neu importiert werden

### Migration Guide

1. Backup erstellen
2. Plugin deaktivieren
3. Plugin aktualisieren
4. Migration-Script ausführen
```

## Checkliste für Releases

- [ ] Alle Tests durchgeführt
- [ ] VERSION Datei aktualisiert
- [ ] CHANGELOG.md aktualisiert
- [ ] wsform-ml.php Header aktualisiert
- [ ] wsform-ml.php WSFORM_ML_VERSION Konstante aktualisiert
- [ ] Git Commit mit aussagekräftiger Message
- [ ] Git Tag erstellt (v1.x.x)
- [ ] Push mit Tags
- [ ] Release Notes auf GitHub (optional)

## Versionierungs-Regeln

1. **Nie zurück gehen**: Versionen werden nie verringert
2. **Keine Lücken**: Versionen folgen sequenziell (1.2.0 → 1.2.1 → 1.3.0)
3. **Pre-Release**: Nutze `-alpha`, `-beta`, `-rc` für Pre-Releases (z.B. `2.0.0-beta.1`)
4. **Dokumentation**: Jede Version MUSS im CHANGELOG dokumentiert sein
5. **Konsistenz**: Alle 3 Dateien (VERSION, CHANGELOG.md, wsform-ml.php) MÜSSEN synchron sein

## Pre-Release Versionen

Für Entwicklungs- und Test-Versionen:

- `1.3.0-alpha.1`: Erste Alpha-Version
- `1.3.0-beta.1`: Erste Beta-Version
- `1.3.0-rc.1`: Release Candidate

**Beispiel CHANGELOG:**

```markdown
## [1.3.0-beta.1] - 2026-02-20

### Added

- Experimentelles Feature XYZ (noch in Entwicklung)

**Warnung:** Dies ist eine Beta-Version. Nicht in Produktion verwenden!
```

## Hotfixes

Für dringende Bugfixes in Produktion:

```
1.2.0 (Produktion) → 1.2.1 (Hotfix)
```

Workflow:

1. Branch von main: `git checkout -b hotfix/1.2.1`
2. Fix implementieren
3. Version auf 1.2.1 erhöhen
4. CHANGELOG aktualisieren
5. Merge in main
6. Tag erstellen: `git tag v1.2.1`
7. Sofort deployen

## Fragen?

Bei Unsicherheit:

- **Kleine Änderung, unsicher?** → PATCH
- **Neues Feature, klein?** → MINOR
- **Bricht es bestehende Installationen?** → MAJOR
- **Nur Doku/Kommentare?** → Kein Release nötig (oder PATCH wenn wichtig)
