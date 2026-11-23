# Mitwirken

## Sprache auswählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/CONTRIBUTING.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/CONTRIBUTING.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/CONTRIBUTING.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/CONTRIBUTING.md) | **Ausgewählt** |

Vielen Dank für Ihr Interesse an Archive Guard. Änderungen betreffen hier die Verarbeitung nicht vertrauenswürdiger Archive und Schreibvorgänge im Dateisystem. Ein kleiner Scope, überprüfbares Verhalten und klare Sicherheitsgrenzen sind deshalb wichtig.

## Vor dem Start

- Reproduzierbare Fehler bitte über GitHub Issues melden.
- Fragen zur Nutzung und Ideen können in GitHub Discussions besprochen werden.
- Sicherheitsprobleme gemäß der [Sicherheitsrichtlinie](https://github.com/yaleksandr89/archive-guard/security/policy) melden, ohne Exploit-Code oder sensible Details öffentlich zu machen.
- Größere Änderungen an öffentlicher API, Archivformaten, Extraktionsmodell oder Sicherheitsgrenzen zuerst in einem Issue oder einer Discussion abstimmen.

## Paketvertrag

- Das Paket bleibt eine frameworkunabhängige PHP-`^8.4`-Bibliothek.
- ZIP, TAR und TAR.GZ werden anhand des Inhalts erkannt.
- Die öffentlichen Hauptoperationen sind `ArchiveGuard::inspect()` und `ArchiveGuard::extract()`.
- Ressourcenlimits kommen aus `ArchivePolicy`.
- `inspect()` und `extract()` teilen die Archivannahmeregeln für `archivePath`, `ArchivePolicy` und das optionale ZIP-`password`.
- Unsichere Pfade, normalisierte Kollisionen, plattforminkompatible Namen, symbolische Links/Hardlinks, besondere Objekte und nicht unterstützte Funktionen müssen abgelehnt werden.
- Bei ZIP prüft `inspect()` die Streaming-Lesbarkeit akzeptierter Inhalte, nicht nur Metadaten.
- `extract()` benötigt `ExtractionOptions`: `atomic()` für ein neues, zuvor nicht vorhandenes Ziel oder `merge(strategy)` für ein bestehendes Verzeichnis.
- Atomic legt automatisch temporären Bereich im Ziel-Elternverzeichnis an, bereitet alles vor und veröffentlicht das vollständige Ergebnis per `rename()`.
- Merge bereitet temporär alles vor, prüft Konflikte und wendet `Reject`, `Skip` oder `Overwrite` an.
- `Overwrite` ersetzt nur reguläre Dateien durch reguläre Dateien; Typkonflikte, Links und besondere Objekte werden abgelehnt.
- Archive-Guard-Operationen auf dasselbe Ziel koordinieren sich über `.archive-guard-locks`; beliebige externe Schreiber werden nicht ausgeschlossen.
- ZIP kann mit optionalem Passwort geprüft und extrahiert werden. Das Originalpasswort darf nicht in Ergebnissen, Zustand oder Diagnosen landen. TAR/TAR.GZ haben keine integrierte Passwortverschlüsselung.
- TAR/TAR.GZ vergleichen Prüfung und separaten Extraktionsdurchlauf.
- Fügen Sie keine Frameworkintegration, Speicher, Hintergrundjobs, automatische Retry/Fallback-Logik, Telemetrie oder sachfremden Funktionen hinzu.

Siehe das [Sicherheitsmodell](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/security-model.md).

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
fix: исправить проверку размера TAR
feat: добавить поддержку пароля ZIP
docs: уточнить модель безопасности
test: добавить регрессию для коллизии путей
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

- Ergänzen Sie Tests für echtes Verhalten und Regressionen, nicht für hohe Assertion-Zahlen.
- Verwenden Sie nur synthetische ZIP/TAR/TAR.GZ-Archive und temporäre Verzeichnisse.
- Nutzen Sie in Passworttests nur synthetische Werte und prüfen Sie, dass sie nicht in Diagnosen erscheinen.
- Fügen Sie keine echten Benutzerarchive, privaten Dateien, Tokens oder Zugangsdaten hinzu.
- Änderungen an Pfadprüfung, Verschlüsselung, Eintragstypen, Grenzen, Atomic/Merge oder Dateisystemanwendung müssen die betroffene Sicherheitsgrenze testen.
- Wenn Verhalten von Windows oder libzip abhängt, berücksichtigen Sie die Fähigkeiten der Umgebung und relevante Windows-CI-Tests.
- Schwächen Sie Prüfungen nicht ab, nur um ein problematisches Archiv zu akzeptieren; definieren Sie zuerst den sicheren öffentlichen Vertrag.

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
