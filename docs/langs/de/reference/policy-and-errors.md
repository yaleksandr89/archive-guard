# Policy, Verstöße und Fehler

Diese Referenz beschreibt Prüfgrenzen, Extraktionsmodi, Ergebnisse, Verstoßcodes und Ausnahmen für die Anwendung.

## ArchivePolicy

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) wird an jeden `inspect()`- oder `extract()`-
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

[`InspectionResult`](../../../../src/InspectionResult.php) wird nur zurückgegeben, wenn die Quelle
geöffnet und als unterstütztes Archiv geparst werden konnte.

Verfügbare Methoden:

- `format()` — liefert [`ArchiveFormat`](../../../../src/ArchiveFormat.php): `zip`, `tar` oder
  `tar.gz`;
- `isAccepted()` — liefert `true`, wenn keine Verstöße vorliegen;
- `violations()` — liefert eine Liste von [`Violation`](../../../../src/Violation.php)-Objekten.

Kann die Datei nicht geöffnet, erkannt oder korrekt geparst werden, wird statt eines Ergebnisses
`ArchiveOpenException` ausgelöst.

## ExtractionOptions

[`ExtractionOptions`](../../../../src/ExtractionOptions.php) ist für `extract()` erforderlich und wird über zwei benannte Factory-Methoden erzeugt:

```php
ExtractionOptions::atomic();

ExtractionOptions::merge(
    ExtractionConflictStrategy::Reject,
);
```

`atomic()` veröffentlicht in einem neuen Zielverzeichnis, das zuvor nicht existieren darf. `merge(...)` wendet das Ergebnis auf ein bestehendes Verzeichnis an und benötigt eine [`ExtractionConflictStrategy`](../../../../src/ExtractionConflictStrategy.php):

- `Reject`: vorhandene reguläre Datei gilt bei der Vorprüfung als Konflikt; keine Merge-Änderungen beginnen;
- `Skip`: vorhandene reguläre Datei beibehalten;
- `Overwrite`: vorhandene reguläre Datei durch eine reguläre Archivdatei ersetzen.

Ein vorhandenes Verzeichnis wird wiederverwendet, wenn auch das Archiv dort ein Verzeichnis hat. Datei-/Verzeichniskonflikte, Links und besondere Objekte auf betroffenen Pfaden werden bei jeder Strategie abgelehnt.

## ExtractionResult

[`ExtractionResult`](../../../../src/ExtractionResult.php) beschreibt tatsächlich angewendete Änderungen:

- `format()`: Archivformat;
- `filesExtracted()`: angewendete neue und überschriebene Dateien;
- `directoriesCreated()`: tatsächlich erstellte Verzeichnisse;
- `bytesWritten()`: Inhaltsbytes der angewendeten neuen und überschriebenen Dateien;
- `filesSkipped()`: tatsächlich ausgelassene Dateien;
- `filesOverwritten()`: tatsächlich ersetzte vorhandene Dateien.

Bei Atomic sind `filesSkipped()` und `filesOverwritten()` immer null (0).

## Violation

[`Violation`](../../../../src/Violation.php) hat drei öffentliche Eigenschaften:

- `code` — ein [`ViolationCode`](../../../../src/ViolationCode.php)-Wert für die programmgesteuerte
  Verarbeitung;
- `message` — kurze Diagnosebeschreibung;
- `entryName` — Name der Datei, des Verzeichnisses oder eines anderen Archivelements, oder
  `null`, wenn der Verstoß das gesamte Archiv betrifft.

Für Bedingungen im Code sollte `code` verwendet werden, nicht ein Vergleich des Texts in
`message`.

## ViolationCode

| Wert | Bedeutung |
| --- | --- |
| `unsafe_path` | Der Pfad kann die erlaubte Verzeichnisstruktur verlassen oder hat eine ungültige Form |
| `path_collision` | Zwei Pfade kollidieren nach Normalisierung oder plattformspezifischem Vergleich |
| `platform_incompatible_path` | Eine Pfadkomponente verletzt deterministische Namensregeln der Plattform |
| `symlink_entry` | Ein Archiveintrag ist ein symbolischer Link |
| `hardlink_entry` | Ein TAR-Eintrag ist ein Hardlink |
| `special_entry` | Ein spezielles Objekt, z. B. ein Gerät, wurde erkannt |
| `archive_too_large` | Die Archivdatei überschreitet `maxArchiveBytes` |
| `too_many_entries` | Dateien, Verzeichnisse und gezählte Metadatenelemente überschreiten `maxEntries` |
| `entry_too_large` | Ein entpacktes Element überschreitet `maxEntryUncompressedBytes` |
| `total_size_exceeded` | Die gesamte entpackte Datenmenge überschreitet `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | Das festgelegte Entpackt/Komprimiert-Verhältnis wurde überschritten |
| `encrypted_entry` | ZIP enthält einen verschlüsselten Eintrag ohne angegebenes Passwort |
| `unsupported_encryption` | Die Laufzeit unterstützt die Verschlüsselungsmethode trotz Passwort nicht |
| `decryption_failed` | Verschlüsselter Inhalt konnte nicht entschlüsselt oder gelesen werden |
| `unsupported_compression` | Die Laufzeit unterstützt das ZIP-Kompressionsverfahren nicht |
| `unsupported_feature` | Das Archiv verwendet eine nicht unterstützte Formatfunktion |

`decryption_failed` bedeutet Entschlüsselungs- oder Lesefehler; `ZipArchive`/`libzip` kann die genaue Ursache nicht immer bestimmen. `entryName` ist nicht bei jedem Verstoß gesetzt: ein insgesamt zu großes Archiv hat keinen einzelnen betroffenen Eintrag.

## Ausnahmen

Alle Paketausnahmen erben von [`ArchiveGuardException`](../../../../src/Exception/ArchiveGuardException.php), die wiederum von `RuntimeException` erbt.

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php): Quelle nicht zu öffnen, Format unbekannt, Struktur beschädigt oder akzeptierte unverschlüsselte ZIP-Daten nicht zuverlässig lesbar. Entschlüsselungs-/Lesefehler verschlüsselter Daten mit Passwort führen dagegen zu `decryption_failed` (und `ArchiveRejectedException` in `extract()`).
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php): Archivprüfung lehnt vor Atomic-Veröffentlichung oder Merge-Anwendung ab. `inspectionResult()` enthält die Gründe. Vor der erkennbaren endgültigen Ablehnung können Dateien im temporären Arbeitsbereich entstanden sein.
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php): Fehler bei Ziel, kooperativer Sperre, temporärem Verzeichnis, Vorprüfung, Merge-Anwendung, Atomic-Veröffentlichung oder Dateisystem-E/A.

`InvalidArgumentException` erbt nicht von `ArchiveGuardException`. Sie gilt für ungültige `ArchivePolicy`-Werte sowie ein Passwort ungleich `null` bei TAR/TAR.GZ ohne integrierte Passwortverschlüsselung.

## Ergebnis und Ausnahme unterscheiden

### `inspect()`

- Akzeptierte Archive liefern `InspectionResult` mit `isAccepted() === true`.
- Archive mit Verstößen liefern `InspectionResult` mit `isAccepted() === false`.
- Nicht lesbare/analysierbare Quelle oder akzeptierte, aber unlesbare unverschlüsselte ZIP-Daten lösen `ArchiveOpenException` aus.
- `password` ist bei ZIP zulässig; bei TAR/TAR.GZ führt ein Passwort ungleich `null` zu `InvalidArgumentException`.

### `extract()`

Argumente:

```text
archivePath
destinationPath
ArchivePolicy
ExtractionOptions
password = null
```

Für `archivePath`, `ArchivePolicy` und `password` gelten dieselben Archivannahmeregeln wie bei `inspect()`.

- Ablehnung führt vor Atomic-Veröffentlichung oder Merge-Anwendung zu `ArchiveRejectedException`. Eine vollständige Prüfung kann erst nach Anlegen des temporären Verzeichnisses enden; `inspectionResult()` liefert die Gründe.
- Quell-, Struktur- oder akzeptierter unverschlüsselter ZIP-Lesefehler führt zu `ArchiveOpenException`.
- Ziel-, Vorprüfungskonflikt- und Dateisystemfehler führen zu `ExtractionException`.
- Bei Erfolg wird `ExtractionResult` zurückgegeben.

Ein Archiv oberhalb `maxArchiveBytes` wird abgelehnt, bevor die Passwortzulässigkeit für TAR/TAR.GZ geprüft wird.

Beispiele: [`inspect()`](../guides/inspection.md) und [`extract()`](../guides/extraction.md).

[← Zurück zur README](../README.md)
