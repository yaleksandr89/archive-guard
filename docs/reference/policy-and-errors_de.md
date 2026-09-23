# Policy, Verstöße und Fehler

Diese Referenz beschreibt Prüfparameter, das Ergebnis von `inspect()`, Verstoßcodes und
Ausnahmen, die eine Anwendung bei der Nutzung des Pakets erhalten kann.

## ArchivePolicy

[`ArchivePolicy`](../../src/ArchivePolicy.php) wird an jeden `inspect()`- oder `extract()`-
Aufruf übergeben und definiert **maximal zulässige Werte**, nicht exakt erwartete Werte.

```php
<?php

use Yaleksandr\ArchiveGuard\ArchivePolicy;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
    maxCompressionRatio: 100.0,
);
```

| Parameter | Was begrenzt wird | Zulässiger Wert |
| --- | --- | --- |
| `maxArchiveBytes` | Maximale Größe der Archivdatei selbst | Ganze Zahl größer als null |
| `maxEntries` | Maximale Anzahl von Dateien und Ordnern im Archiv; bei TAR zählen einige Metadatenelemente des Formats zur selben Grenze | Ganze Zahl größer als null |
| `maxEntryUncompressedBytes` | Maximale Größe einer Datei oder eines anderen Elements nach dem Entpacken | Ganze Zahl größer als null |
| `maxTotalUncompressedBytes` | Maximales Gesamtvolumen der entpackten Daten | Ganze Zahl größer als null |
| `maxCompressionRatio` | Zusätzliche Grenze für das Verhältnis von entpackter zu komprimierter Größe | `null` oder endliche Zahl größer als null |

Die ersten vier Parameter haben keine Standardwerte: Die Anwendung muss sie explizit wählen.
`maxCompressionRatio` ist optional und standardmäßig `null`.

Die Prüfung des Kompressionsverhältnisses unterscheidet sich je nach Format:

- ZIP verwendet Größen aus den Metadaten des Elements;
- TAR.GZ verwendet die tatsächlich vom GZIP-Dekompressor erzeugte Datenmenge;
- normales TAR verwendet diese Grenze nicht.

Ungültige Konstruktorwerte lösen die standardmäßige `InvalidArgumentException` aus.

## InspectionResult

[`InspectionResult`](../../src/InspectionResult.php) wird nur zurückgegeben, wenn die Quelle
geöffnet und als unterstütztes Archiv geparst werden konnte.

Verfügbare Methoden:

- `format()` — liefert [`ArchiveFormat`](../../src/ArchiveFormat.php): `zip`, `tar` oder
  `tar.gz`;
- `isAccepted()` — liefert `true`, wenn keine Verstöße vorliegen;
- `violations()` — liefert eine Liste von [`Violation`](../../src/Violation.php)-Objekten.

Kann die Datei nicht geöffnet, erkannt oder korrekt geparst werden, wird statt eines Ergebnisses
`ArchiveOpenException` ausgelöst.

## Violation

[`Violation`](../../src/Violation.php) hat drei öffentliche Eigenschaften:

- `code` — ein [`ViolationCode`](../../src/ViolationCode.php)-Wert für die programmgesteuerte
  Verarbeitung;
- `message` — kurze Diagnosebeschreibung;
- `entryName` — Name der Datei, des Verzeichnisses oder eines anderen Archivelements, oder
  `null`, wenn der Verstoß das gesamte Archiv betrifft.

Für Bedingungen im Code sollte `code` verwendet werden, nicht ein Vergleich des Texts in
`message`.

## ViolationCode

| Wert | Bedeutung |
| --- | --- |
| `unsafe_path` | Der Elementpfad kann die erlaubte Verzeichnisstruktur verlassen oder hat eine ungültige Form |
| `path_collision` | Zwei normalisierte Pfade sind identisch oder kollidieren als Datei und Verzeichnis |
| `symlink_entry` | Im Archiv wurde ein symbolischer Link gefunden |
| `hardlink_entry` | In TAR wurde ein Hardlink gefunden |
| `special_entry` | Ein spezielles Objekt, zum Beispiel ein Gerät, wurde gefunden |
| `archive_too_large` | Die Größe der Archivdatei überschreitet `maxArchiveBytes` |
| `too_many_entries` | Die Anzahl von Dateien, Ordnern und berücksichtigten Format-Metadatenelementen überschreitet `maxEntries` |
| `entry_too_large` | Eine Datei oder ein anderes Element überschreitet nach dem Entpacken `maxEntryUncompressedBytes` |
| `total_size_exceeded` | Das gesamte entpackte Datenvolumen überschreitet `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | Das konfigurierte Verhältnis von entpackter zu komprimierter Größe wurde überschritten |
| `encrypted_entry` | ZIP enthält ein verschlüsseltes Element; die aktuelle Version fragt kein Passwort ab |
| `unsupported_compression` | Die ZIP-Kompressionsmethode wird von der Umgebung für die Extraktion nicht unterstützt |
| `unsupported_feature` | Das Archiv verwendet eine Formatfunktion, die das Paket nicht unterstützt |

`entryName` ist nicht bei jedem Verstoß vorhanden. Eine Überschreitung der Gesamtgröße der
Archivdatei betrifft zum Beispiel das gesamte Archiv, daher gibt es keinen einzelnen
Elementnamen.

## Ausnahmen

Alle Paketausnahmen erben von
[`ArchiveGuardException`](../../src/Exception/ArchiveGuardException.php), die wiederum von
`RuntimeException` erbt.

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — die Quelldatei
  kann nicht geöffnet werden, das Format wird nicht erkannt oder die Archivstruktur ist
  beschädigt.
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — das Archiv
  hat die verpflichtende Prüfung vor der Extraktion nicht bestanden. `inspectionResult()`
  liefert die Gründe.
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — ein Problem mit dem
  Zielverzeichnis, dem Zielpfad oder dem Schreibvorgang.

`InvalidArgumentException` bei ungültigen `ArchivePolicy`-Werten ist eine standardmäßige
PHP-Ausnahme und erbt nicht von `ArchiveGuardException`.

## Ergebnis und Ausnahme unterscheiden

### `inspect()`

- Wenn das Archiv gelesen werden kann und die Prüfung besteht, liefert die Methode
  `InspectionResult` mit `isAccepted() === true`.
- Wenn das Archiv gelesen werden kann, aber Verstöße gefunden werden, liefert die Methode
  weiterhin `InspectionResult`; `isAccepted()` ist jedoch `false`, und die Gründe stehen in
  `violations()`.
- Wenn die Datei selbst nicht geöffnet oder korrekt geparst werden kann, wird
  `ArchiveOpenException` ausgelöst.

### `extract()`

- Wenn das Archiv die verpflichtende Prüfung vor der Extraktion nicht besteht, wird
  `ArchiveRejectedException` ausgelöst. Die Verstöße sind über `inspectionResult()` verfügbar.
- Wenn die Quelldatei vor Beginn der Extraktion nicht geöffnet oder erkannt werden kann, wird
  `ArchiveOpenException` ausgelöst.
- Tritt das Problem beim Vorbereiten des Ziels oder beim Schreiben auf, wird
  `ExtractionException` ausgelöst.
- Bei Erfolg wird [`ExtractionResult`](../../src/ExtractionResult.php) zurückgegeben.

Praktische Beispiele gibt es im
[`inspect()`-Leitfaden](../guides/inspection_de.md) und
[`extract()`-Leitfaden](../guides/extraction_de.md).

[← Zurück zum README](../readme/README_de.md)
