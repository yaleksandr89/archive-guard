# Kontrollierte Extraktion

Dieser Leitfaden beschreibt die Extraktion mit eingebauten Prüfungen: was vorher vorbereitet
werden muss, welche Fehler auftreten können und was bei einem Schreibabbruch auf der Festplatte
zurückbleiben kann.

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) prüft das Archiv zuerst mit der
übergebenen Policy und beginnt erst nach erfolgreicher Prüfung mit dem Erstellen von Dateien.

## Vor der Extraktion

Bereite Folgendes vor:

- eine lesbare lokale ZIP-, TAR- oder TAR.GZ-Datei;
- eine [`ArchivePolicy`](../../src/ArchivePolicy.php) mit passenden Grenzen für die Anwendung;
- ein separates leeres Zielverzeichnis.

Das Zielverzeichnis muss **bereits existieren und leer sein**. Die aktuelle Version entpackt
nicht über bereits vorhandene Dateien.

Wenn die Anwendung einen gemeinsamen Ordner für alle Extraktionen verwendet, lege darin für
jeden Vorgang ein neues Unterverzeichnis an. So vermischen sich Inhalte verschiedener Archive
nicht und die Bibliothek kann das gesamte Ziel vor dem Schreiben prüfen.

Der PHP-Prozess benötigt Berechtigungen zum Erstellen von Dateien und Unterverzeichnissen in
diesem Verzeichnis.

## Grundlegendes Szenario

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// Wähle Grenzen passend zu den realen Archiven und Ressourcen der Anwendung.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

    echo 'Dateien: ' . $result->filesExtracted() . PHP_EOL;
    echo 'Verzeichnisse: ' . $result->directoriesCreated() . PHP_EOL;
    echo 'Geschriebene Bytes: ' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    // Das Archiv konnte gelesen werden, hat die konfigurierten Prüfungen aber nicht bestanden.
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // Die Quelldatei kann nicht geöffnet oder korrekt geparst werden.
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // Zielfehler oder ein Fehler während der Extraktion.
    echo $e->getMessage() . PHP_EOL;
}
```

## Was vor dem Schreiben passiert

`extract()` prüft den aktuellen Zustand des Archivs immer direkt vor dem Schreiben. Diese
Prüfung findet unabhängig davon statt, ob `inspect()` zuvor aufgerufen wurde.

Ablauf:

1. Die Bibliothek öffnet und prüft das Archiv.
2. Werden Verstöße gefunden, wird
   [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) ausgelöst,
   bevor das Schreiben begonnen hat.
3. Das Zielverzeichnis wird geprüft.
4. Jede Datei wird nur angelegt, wenn an ihrem Pfad noch nichts existiert; ein vorhandenes
   Objekt wird nicht ersetzt.
5. Während des Schreibens werden die tatsächliche Größe jeder Datei und das gesamte entpackte
   Volumen mit den konfigurierten Grenzen und den bei der Prüfung ermittelten Größen
   verglichen. Liefert das Archiv mehr Daten als erlaubt, wird die Extraktion gestoppt.
6. TAR und TAR.GZ werden zweimal gelesen: zuerst zur Prüfung, danach zur Extraktion. Beim
   zweiten Durchlauf werden Reihenfolge, Pfad, Typ und Größe jedes Elements mit der ersten
   Prüfung verglichen. Ändert sich das Archiv zwischen den Durchläufen, wird die Extraktion
   gestoppt.

## Ergebnis

[`ExtractionResult`](../../src/ExtractionResult.php) stellt bereit:

- `format()` — Archivformat;
- `filesExtracted()` — Anzahl der erstellten Dateien;
- `directoriesCreated()` — Anzahl der erstellten Verzeichnisse;
- `bytesWritten()` — Anzahl der in Dateien geschriebenen Bytes.

## Fehler

Während `extract()` sind drei Hauptgruppen von Fehlern möglich:

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — die Quelldatei
  kann nicht geöffnet oder korrekt geparst werden;
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — das Archiv
  ist strukturell gültig, hat aber Grenzen oder Prüfungen nicht bestanden;
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — ein Problem mit dem
  Zielverzeichnis, dem Zielnamen oder dem Schreiben selbst.

Verstoßcodes aus `ArchiveRejectedException` stehen in der
[Fehlerreferenz](../reference/policy-and-errors_de.md).

## Teilweiser Fehler und Atomarität

Daten werden derzeit direkt in das vorbereitete Zielverzeichnis geschrieben. Dadurch kann das
tatsächlich geschriebene Volumen kontrolliert und das Überschreiben vorhandener Dateien
vermieden werden, die Operation ist aber **nicht atomar**.

Wenn nach dem Erstellen mehrerer Dateien der Speicherplatz ausgeht, der Prozess Schreibrechte
verliert oder ein anderer I/O-Fehler auftritt, bleiben bereits erstellte Dateien und
Verzeichnisse bestehen. Auch die aktuelle Datei kann teilweise geschrieben zurückbleiben.

Ein vollständig atomarer Ansatz ist technisch möglich, wäre aber ein anderer Vertrag: Zuerst
müsste alles in ein temporäres Verzeichnis entpackt und anschließend das vollständige Ergebnis
in einem separaten letzten Schritt verschoben werden. Dieses Verschieben verhält sich je nach
Dateisystem und unter Windows unterschiedlich, deshalb verspricht die aktuelle Version keine
Atomarität, die sie nicht garantieren kann.

Wenn die Anwendung für einen Vorgang ein eigenes Verzeichnis erstellt und vollständig besitzt,
kann sie dieses nach einer `ExtractionException` nach ihren eigenen Regeln löschen.

## Windows

Windows hat zusätzliche Einschränkungen für Dateinamen, die im allgemeinen ZIP- oder TAR-
Format nicht existieren. Vor dem ersten Schreiben lehnt die Bibliothek zum Beispiel zusätzlich
ab:

- Gerätenamen wie `CON`, `NUL`, `PRN` und ähnliche;
- unter Windows verbotene Zeichen;
- Namen, die mit Punkt oder Leerzeichen enden;
- Pfade, die sich nur in der ASCII-Groß-/Kleinschreibung unterscheiden und unter Windows
  denselben Ort bezeichnen.

Dasselbe Archiv kann deshalb `inspect()` auf Formatebene bestehen und von `extract()` beim
Vorbereiten des Schreibens unter Windows abgelehnt werden.

## Dateimetadaten

Ein Archiv kann neben Dateiinhalten auch Berechtigungen, Besitzer und Änderungszeit speichern.
Die aktuelle Version extrahiert Dateiinhalte und Verzeichnisstruktur, wendet diese Metadaten
aus dem Archiv aber nicht an.

Weitere Grenzen und Empfehlungen stehen im
[Sicherheitsmodell](../security-model_de.md).

[← Zurück zum README](../readme/README_de.md)
