# Archivprüfung

Dieser Leitfaden erklärt, wie ein Archiv vor dem Entpacken geprüft wird, wie das Ergebnis zu
lesen ist und wie sich ein normaler Grenzwertverstoß von einem Fehler der Archivdatei selbst
unterscheidet.

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) extrahiert nichts auf die Festplatte.
Die Methode analysiert nur das Archiv und liefert ein
[`InspectionResult`](../../src/InspectionResult.php).

## Grundlegendes Szenario

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;

$archivePath = '/path/to/archive.zip';

// Wähle Grenzen passend zu den realen Archiven und Ressourcen der Anwendung.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->inspect($archivePath, $policy);

    if ($result->isAccepted()) {
        echo 'Archiv hat die Prüfung bestanden.' . PHP_EOL;
    } else {
        foreach ($result->violations() as $violation) {
            // code kennzeichnet den Ablehnungsgrund; message enthält die Textbeschreibung.
            echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
        }
    }
} catch (ArchiveOpenException $e) {
    echo 'Archiv konnte nicht gelesen werden: ' . $e->getMessage() . PHP_EOL;
}
```

## Prüfgrenzen

[`ArchivePolicy`](../../src/ArchivePolicy.php) definiert **maximal zulässige Grenzen** und
keine exakt erwarteten Werte:

- `maxArchiveBytes` — maximale Größe der Archivdatei selbst;
- `maxEntries` — maximale Anzahl von Dateien und Ordnern im Archiv; bei TAR zählen einige
  Metadatenelemente des Formats zur selben Grenze;
- `maxEntryUncompressedBytes` — maximale Größe einer Datei nach dem Entpacken;
- `maxTotalUncompressedBytes` — maximales Gesamtvolumen der entpackten Daten;
- `maxCompressionRatio` — optionale zusätzliche Grenze für das Verhältnis von entpackter zu
  komprimierter Größe.

Wenn `maxArchiveBytes` zum Beispiel `50_000_000` beträgt, muss eine 20-MB-Datei keinen exakten
Wert treffen; sie darf nur die konfigurierte Grenze nicht überschreiten.

Ausführliche Regeln für jeden Parameter stehen in der
[`ArchivePolicy`-Referenz](../reference/policy-and-errors_de.md).

## Prüfergebnis

[`InspectionResult`](../../src/InspectionResult.php) stellt drei zentrale Methoden bereit:

- `format()` — erkanntes Format: `zip`, `tar` oder `tar.gz`;
- `isAccepted()` — ob das Archiv alle Prüfungen bestanden hat;
- `violations()` — Gründe, aus denen das Archiv abgelehnt wurde.

Wenn `isAccepted()` `false` zurückgibt, kann das Archiv selbst trotzdem strukturell gültig
sein. Es kann zum Beispiel eine zu große Datei oder einen symbolischen Link enthalten, den die
Paketregeln nicht extrahieren lassen.

Jede [`Violation`](../../src/Violation.php) enthält:

- `code` — stabiler Grundcode für die Anwendungslogik;
- `message` — Diagnosebeschreibung;
- `entryName` — Name der Datei, des Verzeichnisses oder eines anderen Archivelements, wenn
  der Verstoß einem konkreten Element zugeordnet ist.

Die vollständige Codeliste steht in der
[Verstoßreferenz](../reference/policy-and-errors_de.md).

## Öffnungs- und Strukturfehler

[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) bedeutet nicht, dass
das Archiv die gewählte Policy verletzt. Die Ausnahme bedeutet, dass das Archiv nicht korrekt
gelesen werden kann.

Wichtige Fälle:

- die Datei existiert nicht, ist keine reguläre lokale Datei oder ist nicht lesbar;
- der übergebene Pfad enthält `://`;
- der Inhalt kann nicht als ZIP, TAR oder TAR.GZ erkannt werden;
- ZIP, TAR oder GZIP ist beschädigt oder strukturell ungültig.

In diesen Fällen wird kein `InspectionResult` zurückgegeben.

## Was geprüft wird

### Pfade

Die Bibliothek wandelt Backslashes in `/` um, entfernt leere Segmente und `.`, und lehnt ab:

- `..`, womit in ein übergeordnetes Verzeichnis gewechselt werden könnte;
- absolute Pfade;
- Pfade mit Laufwerksbuchstaben;
- UNC-Pfade;
- Namen mit NUL-Byte;
- doppelte und kollidierende Pfade nach der Normalisierung.

### Inhaltstypen

Reguläre Dateien und Verzeichnisse sind erlaubt. Symbolische Links, TAR-Hardlinks, spezielle
Objekte und nicht unterstützte Formatfunktionen werden als Verstöße zurückgegeben.

### Ressourcenlimits

Geprüft werden:

- Größe der Archivdatei;
- Anzahl von Dateien, Ordnern und berücksichtigten Format-Metadatenelementen;
- Größe einer Datei nach dem Entpacken;
- gesamtes entpacktes Datenvolumen;
- falls konfiguriert, das Verhältnis von komprimierter zu entpackter Größe.

### Besonderheiten von ZIP, TAR und TAR.GZ

Bei ZIP werden zusätzlich Verschlüsselung und Kompressionsmethode geprüft.

Enthält ein ZIP ein verschlüsseltes Element, liefert `inspect()` den Verstoß
`encrypted_entry`, und `isAccepted()` ist `false`. Die aktuelle Paketversion fragt kein
Passwort ab und extrahiert keine verschlüsselten Inhalte.

TAR und TAR.GZ unterstützen nur den implementierten Satz regulärer Header und GNU/PAX-
Erweiterungen. Details stehen in der
[Referenz zu unterstützten Archiven](../reference/supported-archives_de.md).

[← Zurück zum README](../readme/README_de.md)
