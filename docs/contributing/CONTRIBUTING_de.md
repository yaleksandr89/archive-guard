# Mitwirken

## Sprache auswählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_en.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_es.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_zh.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_fr.md) | **Ausgewählt** |

Vielen Dank für Ihr Interesse an Archive Guard. Änderungen betreffen hier die Verarbeitung nicht vertrauenswürdiger Archive und Schreibvorgänge im Dateisystem. Ein kleiner Scope, überprüfbares Verhalten und klare Sicherheitsgrenzen sind deshalb wichtig.

## Vor dem Start

- Reproduzierbare Fehler bitte über GitHub Issues melden.
- Fragen zur Nutzung und Ideen können in GitHub Discussions besprochen werden.
- Sicherheitsprobleme gemäß der [Sicherheitsrichtlinie](https://github.com/yaleksandr89/archive-guard/security/policy) melden, ohne Exploit-Code oder sensible Details öffentlich zu machen.
- Größere Änderungen an öffentlicher API, Archivformaten, Extraktionsmodell oder Sicherheitsgrenzen zuerst in einem Issue oder einer Discussion abstimmen.

## Paketvertrag

- Das Paket bleibt eine framework-unabhängige Bibliothek für PHP `^8.4`.
- Unterstützte Formate werden anhand des Inhalts erkannt: ZIP, TAR und TAR.GZ.
- Die wichtigsten öffentlichen Operationen sind `ArchiveGuard::inspect()` und `ArchiveGuard::extract()`.
- Ressourcenlimits werden über `ArchivePolicy` konfiguriert.
- Unsichere Pfade, symbolische Links, Hardlinks, spezielle Objekte und nicht unterstützte Formatfunktionen müssen abgelehnt werden.
- `extract()` führt vor dem Schreiben die erforderliche Prüfung aus, überschreibt keine vorhandenen Dateien und kontrolliert das tatsächlich geschriebene Datenvolumen.
- Bei TAR und TAR.GZ wird das Ergebnis des ersten Durchlaufs mit dem Extraktionsdurchlauf verglichen.
- Das Zielverzeichnis muss bereits existieren, leer sein und darf kein symbolischer Link sein.
- Die aktuelle Version verspricht keine atomare Extraktion, keine Verzeichnissperre, keine Extraktion verschlüsselter ZIPs und keine Wiederherstellung von Dateisystem-Metadaten aus dem Archiv.
- Keine Framework-Bundles/Service-Provider, Storage, Background Jobs, automatische Retry/Fallback-Mechanismen, Telemetrie oder andere unbezogene Funktionen hinzufügen.

Detaillierte Grenzen stehen im [Sicherheitsmodell](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security-model_de.md).

## Branches

Kurze Namen verwenden, die den Zweck beschreiben, zum Beispiel:

```text
fix/tar-size-validation
feat/zip-password
docs/security-policy
```

## Commits

Conventional Commits mit einer knappen Beschreibung verwenden:

```text
fix: TAR-Größenprüfung korrigieren
feat: ZIP-Passwortunterstützung hinzufügen
docs: Sicherheitsmodell präzisieren
test: Regression für Pfadkollision hinzufügen
```

Jeder Commit sollte eine zusammenhängende Änderung enthalten und keine unbezogenen Refactorings mitbringen.

## Lokale Prüfungen

Abhängigkeiten installieren und den vollständigen Prüfsatz ausführen:

```shell
composer install
composer check
```

Gezielte Prüfungen sind ebenfalls verfügbar:

```shell
composer test
composer analyse
composer cs:check
```

`composer coverage` ist ein Diagnosebericht und nicht für jede Änderung erforderlich.

## Tests und Fixtures

- Tests für konkretes Verhalten oder eine Regression hinzufügen, nicht für die Anzahl der Assertions.
- Nur synthetische ZIP/TAR/TAR.GZ-Fixtures und temporäre Verzeichnisse verwenden.
- Keine echten Benutzerarchive, privaten Dateien, Tokens oder Zugangsdaten hinzufügen.
- Änderungen an Pfadprüfung, Elementtypen, Limits oder Extraktion sollten Tests für die betroffene Sicherheitsgrenze enthalten.
- Bei Windows-spezifischem Verhalten die Windows-CI-Abdeckung beibehalten oder ergänzen, sofern relevant.
- Prüfungen nicht nur deshalb abschwächen, um ein einzelnes problematisches Archiv zu akzeptieren; zuerst den sicheren öffentlichen Vertrag definieren.

## Pull Request

In der Pull-Request-Beschreibung angeben:

- Problem und implementierte Änderung;
- Auswirkungen auf öffentliche API und Abwärtskompatibilität;
- betroffene Sicherheits-/Dateisystemgrenze;
- hinzugefügte oder aktualisierte Tests;
- ausgeführte Prüfungen;
- Dokumentationsänderungen und Synchronisierung der Übersetzungen, wenn sich öffentliches Verhalten geändert hat.

Vor dem Absenden prüfen:

- der Diff enthält keine unbezogenen Änderungen;
- `git diff --check` besteht;
- `composer check` oder ein begründeter Satz relevanter Prüfungen besteht;
- `vendor/`, `composer.lock`, `.build/`, echte Archive mit privaten Daten und andere lokale Artefakte wurden nicht committed;
- öffentliche API und Dokumentation entsprechen dem tatsächlichen Verhalten;
- Sicherheitsdetails werden nicht vorzeitig in einem öffentlichen Issue oder Pull Request offengelegt.
