# Kontrollierte Extraktion

Diese Anleitung beschreibt zwei ausdrücklich gewählte Modi: **Atomic** veröffentlicht ein vollständig neues Verzeichnis, **Merge** wendet ein Archiv auf ein bestehendes Verzeichnis an.

[`ArchiveGuard::extract()`](../../../../src/ArchiveGuard.php) versteht ein früheres `inspect()`-Ergebnis nicht als Erlaubnis zum Anwenden von Änderungen. Das aktuelle Archiv wird beim Extrahieren erneut geprüft; der Modus wird über [`ExtractionOptions`](../../../../src/ExtractionOptions.php) gewählt.

## Vor der Extraktion

Benötigt werden:

- eine lesbare lokale ZIP-, TAR- oder TAR.GZ-Datei;
- eine [`ArchivePolicy`](../../../../src/ArchivePolicy.php) mit passenden Anwendungsgrenzen;
- [`ExtractionOptions`](../../../../src/ExtractionOptions.php), die Atomic oder Merge ausdrücklich auswählen;
- ein Zielpfad, der die Bedingungen des gewählten Modus erfüllt.

Bei `ExtractionOptions::atomic()` darf das endgültige Zielverzeichnis **noch nicht existieren**. Die Bibliothek erstellt automatisch ein temporäres Verzeichnis im übergeordneten Zielverzeichnis. Erstellen Sie es nicht selbst. Das übergeordnete Verzeichnis muss sowohl das temporäre Verzeichnis als auch das Verzeichnis für kooperative Sperren erlauben.

Bei `ExtractionOptions::merge(...)` muss das Ziel hingegen ein **bereits vorhandenes, lesbares, echtes Verzeichnis** und kein symbolischer Link sein. Das Archiv wird vollständig in einem automatisch angelegten temporären Verzeichnis im Ziel-Elternverzeichnis vorbereitet und erst danach angewendet.

## Grundlegendes Szenario: Atomic

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/result';

// Wählen Sie Grenzwerte, die zu den tatsächlichen Archiven und Ressourcen Ihrer Anwendung passen.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract(
        $archivePath,
        $destinationPath,
        $policy,
        ExtractionOptions::atomic(),
    );

    echo 'Angewendete Dateien: ' . $result->filesExtracted() . PHP_EOL;
    echo 'Erstellte Verzeichnisse: ' . $result->directoriesCreated() . PHP_EOL;
    echo 'Geschriebene Bytes: ' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException | ExtractionException $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

Atomic arbeitet mit einem automatisch angelegten temporären Verzeichnis im Elternverzeichnis des Ziels. Nach vollständiger Extraktion und Prüfung veröffentlicht `rename()` dieses Verzeichnis unter dem bislang nicht vorhandenen endgültigen Zielpfad.

## Merge und Konfliktstrategien

Zum Anwenden eines Archivs auf ein bestehendes Verzeichnis verwenden Sie `ExtractionOptions::merge()`:

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract(
    '/path/to/archive.zip',
    '/path/to/existing-directory',
    $policy,
    ExtractionOptions::merge(ExtractionConflictStrategy::Reject),
);
```

Drei Strategien regeln einen Konflikt zwischen einer Archivdatei und einer **bereits vorhandenen regulären Datei**:

- `Reject`: Fehler bei der Vorprüfung, bevor Merge Änderungen anwendet;
- `Skip`: vorhandene Datei beibehalten, vorbereitete Datei auslassen;
- `Overwrite`: vorhandene reguläre Datei durch eine vorbereitete reguläre Datei ersetzen.

Für Verzeichnisse gilt eine andere Regel: Existieren auf beiden Seiten Verzeichnisse desselben Pfads, wird das Zielverzeichnis wiederverwendet. Datei-/Verzeichniskonflikte in beiden Richtungen sind immer Fehler. Symbolische Links und besondere Objekte auf betroffenen Zielpfaden werden ebenfalls abgelehnt.

`Overwrite` erlaubt ausschließlich den Ersatz einer regulären Datei durch eine reguläre Datei, niemals den Ersatz von Verzeichnissen, Links oder anderen Objekttypen.

## Was vor dem Anwenden geschieht

`extract()` prüft die aktuellen Werte `archivePath`, `ArchivePolicy` und `password` erneut. Ein früheres `inspect()` gilt nicht als Schreibfreigabe.

Der Ablauf:

1. Quelle prüfen und Format anhand des Inhalts erkennen.
2. `maxArchiveBytes` prüfen.
3. Prüfen, ob ein Passwort für das Format zulässig ist.
4. Eine kooperative Archive-Guard-Sperre für das Ziel anfordern.
5. Ein temporäres Verzeichnis im Elternverzeichnis des Ziels anlegen.
6. Das Archiv darin vollständig extrahieren und überprüfen.
7. Bei Atomic das fertige Verzeichnis unter einem zuvor nicht vorhandenen endgültigen Pfad veröffentlichen.
8. Bei Merge alle Konflikte vorab prüfen und die Dateien gemäß der gewählten Strategie anwenden.
9. Das temporäre Verzeichnis aufräumen und die Sperre freigeben.

ZIP-Inhalte werden mit einem gemeinsamen Streaming-Verfahren gelesen. Die tatsächliche entpackte Datenmenge wird während des Lesens geprüft, ohne einen ganzen Eintrag in den Speicher zu laden.

Bei TAR und TAR.GZ erfolgt ein separater Extraktionsdurchlauf. Reihenfolge, Pfad, Typ und Größe jedes Eintrags werden mit dem Prüfergebnis verglichen. Änderungen der Quelle zwischen beiden Durchläufen stoppen die Extraktion.

## ZIP-Passwort

Das Passwort wird als gesondertes benanntes Argument übergeben:

```php
$result = new ArchiveGuard()->extract(
    $archivePath,
    $destinationPath,
    $policy,
    ExtractionOptions::atomic(),
    password: $password,
);
```

`null` bedeutet, dass kein Passwort angegeben wurde. Auch die leere Zeichenkette `''` gilt als angegebenes Passwort und wird nicht in `null` umgewandelt.

Ein Passwort ist auch für unverschlüsselte ZIP-Dateien erlaubt. Ein verschlüsselter Eintrag ohne Passwort ergibt `encrypted_entry`, ein von der Laufzeit nicht unterstütztes Verschlüsselungsverfahren `unsupported_encryption`, und ein Fehler beim Entschlüsseln oder Lesen verschlüsselter Daten `decryption_failed`.

`decryption_failed` bezeichnet einen Entschlüsselungs- oder Lesefehler. `ZipArchive`/`libzip` kann ein falsches Passwort nicht immer von anderen Lesefehlern verschlüsselter Daten unterscheiden.

TAR und TAR.GZ bieten keine integrierte Passwortverschlüsselung. Für sie muss `password` den Wert `null` haben; andernfalls entsteht `InvalidArgumentException`.

Das ZIP-Passwort ist als sensibler Parameter markiert und wird nicht in `ExtractionOptions`, `ExtractionResult`, dem Arbeitsbereich oder Diagnosemeldungen gespeichert.

## Ergebnis

[`ExtractionResult`](../../../../src/ExtractionResult.php) beschreibt die tatsächlich angewendeten Änderungen:

- `format()`: Archivformat;
- `filesExtracted()`: tatsächlich angewendete neue und überschriebene Dateien;
- `directoriesCreated()`: tatsächlich erstellte Verzeichnisse;
- `bytesWritten()`: Inhaltsbytes der angewendeten neuen und überschriebenen Dateien;
- `filesSkipped()`: tatsächlich durch `Skip` ausgelassene Dateien;
- `filesOverwritten()`: tatsächlich durch `Overwrite` ersetzte vorhandene Dateien.

Bei Atomic sind `filesSkipped()` und `filesOverwritten()` immer null (0).

## Fehler

Bei `extract()` gibt es drei wesentliche Fehlergruppen:

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php): Quelle lässt sich nicht zuverlässig öffnen oder analysieren beziehungsweise akzeptierter unverschlüsselter ZIP-Inhalt kann nicht zuverlässig gelesen werden;
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php): Archivprüfung wurde nicht bestanden; `inspectionResult()` enthält die Gründe. Atomic-Veröffentlichung beziehungsweise Merge-Anwendung haben noch nicht begonnen;
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php): Fehler bei Zielverzeichnis, Sperre, temporärem Verzeichnis, Vorprüfung, Merge-Anwendung, Atomic-Veröffentlichung oder Dateisystem-E/A.

Ein Passwort ungleich `null` für TAR/TAR.GZ führt gesondert zu `InvalidArgumentException`. Die Codes stehen in der [Fehlerreferenz](../reference/policy-and-errors.md).

## Anwendung von Atomic und Merge

### Atomic

Atomic extrahiert und überprüft das vollständige Ergebnis in einem automatisch erstellten temporären Verzeichnis im Elternverzeichnis des Ziels. Nach erfolgreichen Prüfungen veröffentlicht `rename()` es als neues endgültiges Zielverzeichnis. Ein Fehler vor der Veröffentlichung beendet den Vorgang und entfernt das temporäre Verzeichnis.

### Merge

Merge ist für ein bestehendes Zielverzeichnis vorgesehen. Das Archiv wird zuerst vollständig temporär vorbereitet, und alle Konflikte werden vor Änderungen am Ziel geprüft. Anschließend werden die Änderungen nacheinander gemäß `Reject`, `Skip` oder `Overwrite` angewendet.

Ein Dateisystemfehler bei der Anwendung löst `ExtractionException` aus und stoppt weitere Änderungen. **Bereits angewendete Änderungen bleiben erhalten**. Das temporäre Verzeichnis wird anschließend gelöscht. Merge ist **nicht transaktional**.

### Kooperative Sperre

Beide Modi verwenden `.archive-guard-locks`, um parallele Archive-Guard-Operationen auf demselben Ziel zu koordinieren. Die Bibliothek legt die Sperre automatisch an und verwendet sie. Beliebige externe Schreiber werden dadurch nicht ausgeschlossen.

## Zugriffsrechte bei Merge

Merge benötigt nicht pauschal Schreibzugriff auf „das gesamte Zielverzeichnis“:

- Bei `Skip` ist für eine unverändert gelassene Konfliktdatei allein deswegen kein Schreibzugriff nötig.
- Für eine neue Datei, ein neues Verzeichnis oder `Overwrite` muss das **Elternverzeichnis des konkret betroffenen Objekts** beschreibbar sein.
- Unter Windows muss auch die zu überschreibende Datei selbst beschreibbar sein.
- Unter POSIX kann eine schreibgeschützte Datei ersetzt werden, wenn ihr Elternverzeichnis beschreibbar ist.
- Das Merge-Wurzelverzeichnis darf schreibgeschützt sein, sofern keine Änderung direkt darin Schreibzugriff erfordert.

Das Elternverzeichnis des Ziels muss außerdem die automatisch angelegten temporären und kooperativen Sperrverzeichnisse erlauben.

Archivmetadaten können Berechtigungen, Besitzer und Zeitstempel enthalten. Archive Guard extrahiert Inhalte und Verzeichnisse, wendet diese gespeicherten Metadaten jedoch nicht an.

## Windows

Deterministische Namensregeln der aktuellen Plattform werden bereits bei der Archivprüfung angewendet. Unter Windows werden beispielsweise abgelehnt:

- ungültiges UTF-8 in Pfadkomponenten;
- verbotene Win32-Zeichen und Steuerzeichen;
- Namen mit abschließendem Punkt oder Leerzeichen;
- reservierte Gerätenamen wie `CON`, `NUL`, `PRN`, `AUX`, `COM1...`, `LPT1...`;
- Pfadkollisionen, die nur bei ASCII-Groß-/Kleinschreibung unterschiedlich sind.

Prüfungen, die vom konkreten Zielverzeichnis und dessen Dateisystem abhängen, erfolgen erst in `extract()`.

Weitere Details stehen im [Sicherheitsmodell](../security-model.md).

[← Zurück zur README](../README.md)
