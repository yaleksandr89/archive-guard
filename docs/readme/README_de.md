# Archive Guard

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Farchive--guard-blue.svg?style=flat-square)](https://github.com/yaleksandr89/archive-guard)
[![CI](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/yaleksandr89/archive-guard.svg?style=flat-square)](https://packagist.org/packages/yaleksandr89/archive-guard)
[![Total Downloads](https://img.shields.io/packagist/dt/yaleksandr89/archive-guard.svg?style=flat-square)](https://packagist.org/packages/yaleksandr89/archive-guard)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](../../LICENSE)

![Archive Guard — Prüfung und Extraktion von ZIP, TAR und TAR.GZ für PHP](../assets/archive-guard-readme-cover.png)

## Sprache auswählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../README.md) | [English](./README_en.md) | [Español](./README_es.md) | [中文](./README_zh.md) | [Français](./README_fr.md) | **Ausgewählt** |

Archive Guard ist eine PHP-Bibliothek zum Prüfen von ZIP-, TAR- und TAR.GZ-Archiven vor dem
Entpacken und zum Extrahieren von Dateien mit vorgegebenen Grenzen.

## Wofür das Paket gedacht ist

Wenn eine Anwendung ein Archiv von einem Benutzer oder einem externen Dienst erhält, sollte
vor dem Entpacken geprüft werden, ob es keine gefährlichen Pfade, Links oder nicht unterstützten
Elemente enthält und ob Archivgröße sowie entpacktes Datenvolumen innerhalb der von der
Anwendung erlaubten Grenzen bleiben.

Das Paket kann ein Archiv vor dem Entpacken separat prüfen oder Prüfung und Extraktion in
einem Schritt durchführen. Besteht das Archiv die Prüfung nicht, erhält die Anwendung einen
konkreten Grund.

## Was das Paket macht

- erkennt das Format anhand des Dateiinhalts statt anhand der Dateiendung;
- prüft Pfade im Archiv und verhindert ein Verlassen des Zielverzeichnisses;
- lehnt symbolische Links, Hardlinks, spezielle Objekte und nicht unterstützte
  Archivelemente ab;
- begrenzt die maximale Archivgröße, die Anzahl der enthaltenen Dateien und Ordner sowie das
  gesamte Datenvolumen nach dem Entpacken;
- liefert eine strukturierte Liste von Verstößen, die die Anwendung verarbeiten kann;
- ermöglicht die separate Prüfung mit `inspect()` oder Prüfung und Extraktion mit `extract()`;
- prüft unter Windows zusätzlich Namen, die das Dateisystem nicht sicher anlegen kann.

Die genauen Unterschiede zwischen ZIP, TAR und TAR.GZ stehen in der
[Referenz zu unterstützten Archiven](../reference/supported-archives_de.md).

## Anforderungen

- PHP `^8.4`;
- `ext-zip`;
- `ext-zlib`.

## Schnellstart

### Archiv prüfen

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) prüft ein Archiv, ohne Dateien zu
extrahieren. Grenzen werden über [`ArchivePolicy`](../../src/ArchivePolicy.php) festgelegt.

<details>
<summary>Prüfbeispiel anzeigen</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';

// Wähle Grenzen passend zu den realen Archiven und Ressourcen der Anwendung.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$inspection = new ArchiveGuard()->inspect($archivePath, $policy);

if ($inspection->isAccepted()) {
    echo 'Archiv hat die Prüfung bestanden.' . PHP_EOL;
} else {
    foreach ($inspection->violations() as $violation) {
        // code kennzeichnet den Ablehnungsgrund; message enthält die Textbeschreibung.
        echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
    }
}
```

</details>

Die Werte oben dienen nur als Beispiel. Wähle sie passend zu den Archivgrößen, die deine
Anwendung tatsächlich erwartet. Der Prüfablauf wird im
[`inspect()`-Leitfaden](../guides/inspection_de.md) ausführlich beschrieben.

### Dateien extrahieren

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) führt die erforderliche Prüfung
unmittelbar vor dem Schreiben der Dateien durch. Das Zielverzeichnis wird von der Anwendung
vorher angelegt.

<details>
<summary>Extraktionsbeispiel anzeigen</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// Wähle Grenzen passend zu den realen Archiven und Ressourcen der Anwendung.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

echo 'Dateien: ' . $result->filesExtracted() . PHP_EOL;
echo 'Verzeichnisse: ' . $result->directoriesCreated() . PHP_EOL;
echo 'Geschriebene Bytes: ' . $result->bytesWritten() . PHP_EOL;
```

</details>

Das Ergebnis eines früheren `inspect()`-Aufrufs gilt nicht als Schreibfreigabe: `extract()`
prüft den aktuellen Zustand der Datei direkt vor der Extraktion. Anforderungen an das
Zielverzeichnis und das Verhalten bei Fehlern beschreibt der
[Extraktionsleitfaden](../guides/extraction_de.md).

## Prüfregeln

[`ArchivePolicy`](../../src/ArchivePolicy.php) definiert Obergrenzen, bei deren Überschreitung
ein Archiv abgelehnt wird:

- maximale Größe der Archivdatei;
- maximale Anzahl von Dateien und Ordnern im Archiv; bei TAR zählen einige Metadatenelemente
  des Formats zur selben Grenze;
- maximale Größe einer einzelnen Datei nach dem Entpacken;
- maximale Gesamtgröße aller entpackten Daten;
- optionale Grenze für das Verhältnis von entpackter zu komprimierter Größe.

Es gibt keine universellen Werte: Für Avatar-Uploads und für Sicherungsarchive sind
unterschiedliche Grenzen sinnvoll. Alle Parameter und Regeln werden in der
[Policy-Referenz](../reference/policy-and-errors_de.md) beschrieben.

## Verstöße und Fehler

Wenn ein Archiv erkannt wird, die konfigurierten Prüfungen aber nicht besteht, liefert
[`inspect()`](../../src/ArchiveGuard.php) ein
[`InspectionResult`](../../src/InspectionResult.php) mit Verstößen zurück.

Ausnahmen stehen für andere Fehlerarten:

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — die Datei kann
  nicht geöffnet werden, das Format wird nicht erkannt oder die Archivstruktur ist beschädigt;
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — das Archiv
  hat die verpflichtende Prüfung vor der Extraktion nicht bestanden, daher wurde noch nichts
  geschrieben;
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — das Problem betrifft
  das Zielverzeichnis oder trat während der Extraktion auf.

<details>
<summary>Beispiel zur Fehlerbehandlung anzeigen</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$guard = new ArchiveGuard();

try {
    $result = $guard->extract($archivePath, $destinationPath, $policy);
} catch (ArchiveRejectedException $e) {
    // Das Archiv konnte gelesen werden, hat die konfigurierten Prüfungen aber nicht bestanden.
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // Die Datei kann nicht geöffnet werden oder die Archivstruktur ist ungültig.
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // Das Archiv hat die Prüfung bestanden, die Extraktion konnte aber nicht abgeschlossen werden.
    echo $e->getMessage() . PHP_EOL;
}
```

</details>

Die vollständige Liste der Verstoßcodes und Ausnahmen steht in der
[Fehlerreferenz](../reference/policy-and-errors_de.md).

## Sicherheit und Einschränkungen

Bei der Extraktion sind mehrere Bedingungen wichtig:

- **Jede Extraktion benötigt ein separates leeres Verzeichnis.** Wenn die Anwendung einen
  gemeinsamen Ordner für alle Archive verwendet, lege darin für jeden Vorgang ein neues
  Unterverzeichnis an. Die aktuelle Version entpackt nicht über bereits vorhandene Dateien.
- **Vorhandene Dateien oder Verzeichnisse werden nicht ersetzt.** Wenn während der Extraktion
  am benötigten Pfad bereits ein Objekt existiert, schlägt der Vorgang fehl, statt es zu
  überschreiben.
- **Berechtigungen, Besitzer und Änderungszeit aus dem Archiv werden nicht wiederhergestellt.**
  Dateiinhalte und Verzeichnisstruktur werden extrahiert; im Archiv gespeicherte Metadaten
  werden nicht angewendet.
- **Windows hat zusätzliche Einschränkungen für Dateinamen.** Zum Beispiel erlaubt Windows
  `CON`, `NUL`, Namen mit abschließendem Punkt und manche Namen mit Sonderzeichen nicht.
  Ein Archiv kann deshalb die allgemeine Prüfung bestehen und direkt vor der Extraktion unter
  Windows trotzdem abgelehnt werden.
- **Die Extraktion ist nicht atomar.** Wenn während des Schreibens der Speicherplatz ausgeht
  oder ein anderer I/O-Fehler auftritt, können sich bereits einige Dateien im Zielverzeichnis
  befinden. Ein automatisches Rollback gibt es derzeit nicht.
- **Das Zielverzeichnis wird nicht gegen andere Prozesse gesperrt.** Die Prüfungen decken
  keinen Fall ab, in dem ein anderer Prozess den Inhalt des Zielverzeichnisses gleichzeitig
  verändert. Verwende für die Extraktion ein separates Verzeichnis, auf das während des
  Vorgangs nur deine Anwendung zugreifen kann.

Warum diese Grenzen bestehen und welche Verantwortung bei der Anwendung bleibt, beschreibt
das [Sicherheitsmodell](../security-model_de.md).

## Feedback

- reproduzierbare Fehler — [GitHub Issues](https://github.com/yaleksandr89/archive-guard/issues);
- Fragen zur Nutzung und Ideen — [GitHub Discussions](https://github.com/yaleksandr89/archive-guard/discussions).

---

<p align="center">
  Wenn das Paket hilfreich war, gib ihm auf GitHub einen Stern, damit andere Entwickler es leichter finden. 🤘
</p>
