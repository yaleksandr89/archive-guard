# Archivprüfung

Dieser Leitfaden erklärt, wie ein Archiv vor dem Entpacken geprüft wird, wie das Ergebnis zu
lesen ist und wie sich ein normaler Grenzwertverstoß von einem Fehler der Archivdatei selbst
unterscheidet.

[`ArchiveGuard::inspect()`](../../../../src/ArchiveGuard.php) extrahiert nichts auf die Festplatte.
Die Methode analysiert nur das Archiv und liefert ein
[`InspectionResult`](../../../../src/InspectionResult.php).

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

## Passwortgeschütztes ZIP

Für ein verschlüsseltes ZIP übergeben Sie das Passwort als benanntes Argument:

```php
$result = new ArchiveGuard()->inspect(
    $archivePath,
    $policy,
    password: $password,
);
```

Die Werte haben unterschiedliche Bedeutung:

- `null`: kein Passwort angegeben;
- `''`: ausdrücklich angegebene leere Zeichenkette, die nicht zu `null` wird;
- jeder angegebene Wert, einschließlich `''`, wird als Passwort an `ZipArchive` übergeben.

Auch bei einem unverschlüsselten ZIP darf ein Passwort angegeben werden. TAR und TAR.GZ haben keine integrierte Passwortverschlüsselung; ein `password` ungleich `null` löst `InvalidArgumentException` mit `Password is only valid for ZIP archives.` aus.

Mögliche Ablehnungsgründe verschlüsselter Einträge:

- `encrypted_entry`: verschlüsselter Eintrag ohne Passwort;
- `unsupported_encryption`: Passwort vorhanden, aber Verschlüsselungsmethode von der Laufzeit nicht unterstützt;
- `decryption_failed`: verschlüsselter Inhalt konnte nicht entschlüsselt oder gelesen werden; das beweist nicht, dass allein das Passwort falsch war.

Das Passwort ist als sensibler Parameter markiert und darf nicht in Ausnahmen, Verstößen oder Diagnosen erscheinen.

## Prüfgrenzen

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) definiert **maximal zulässige Grenzen** und
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
[`ArchivePolicy`-Referenz](../reference/policy-and-errors.md).

Die Größe des Quellarchivs wird vor der Zulässigkeit eines Passworts für das Format geprüft. Daher hat `archive_too_large` Vorrang vor einem TAR/TAR.GZ-Passwortfehler.

## Prüfergebnis

[`InspectionResult`](../../../../src/InspectionResult.php) stellt drei zentrale Methoden bereit:

- `format()` — erkanntes Format: `zip`, `tar` oder `tar.gz`;
- `isAccepted()` — ob das Archiv alle Prüfungen bestanden hat;
- `violations()` — Gründe, aus denen das Archiv abgelehnt wurde.

Wenn `isAccepted()` `false` zurückgibt, kann das Archiv selbst trotzdem strukturell gültig
sein. Es kann zum Beispiel eine zu große Datei oder einen symbolischen Link enthalten, den die
Paketregeln nicht extrahieren lassen.

Jede [`Violation`](../../../../src/Violation.php) enthält:

- `code` — stabiler Grundcode für die Anwendungslogik;
- `message` — Diagnosebeschreibung;
- `entryName` — Name der Datei, des Verzeichnisses oder eines anderen Archivelements, wenn
  der Verstoß einem konkreten Element zugeordnet ist.

Die vollständige Codeliste steht in der
[Verstoßreferenz](../reference/policy-and-errors.md).

## Öffnungs- und Strukturfehler

[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) bedeutet nicht, dass
das Archiv die gewählte Policy verletzt. Die Ausnahme bedeutet, dass das Archiv nicht korrekt
gelesen werden kann.

Wichtige Fälle:

- die Datei existiert nicht, ist keine reguläre lokale Datei oder ist nicht lesbar;
- der übergebene Pfad enthält `://`;
- der Inhalt kann nicht als ZIP, TAR oder TAR.GZ erkannt werden;
- ZIP, TAR oder GZIP ist beschädigt oder strukturell ungültig.

In diesen Fällen wird kein `InspectionResult` zurückgegeben.

Eine akzeptierte unverschlüsselte ZIP-Datei kann trotz geprüfter Metadaten unlesbar sein; dann entsteht `ArchiveOpenException`. Scheitert dagegen das Entschlüsseln oder Lesen verschlüsselter Daten mit Passwort, wird ein abgelehntes `InspectionResult` mit `decryption_failed` statt dieser Ausnahme zurückgegeben.

## Was geprüft wird

### Pfade

Die Bibliothek wandelt Rückwärtsschrägstriche in `/` um, entfernt leere Segmente und `.`, und lehnt ab:

- `..`-Segmente, die das Elternverzeichnis erreichen;
- absolute Pfade, Pfade mit Laufwerksbuchstaben und UNC-Pfade;
- Namen mit NUL-Byte;
- Duplikate und Konflikte nach der Normalisierung.

Unter Windows gelten bereits bei dieser Prüfung deterministische Regeln für ungültiges UTF-8, verbotene Win32- und Steuerzeichen, abschließende Punkte/Leerzeichen, reservierte Gerätenamen und ASCII-Groß-/Kleinschreibungskollisionen.

Deterministische Namensregeln prüft `inspect()`; Bedingungen des konkreten Zielverzeichnisses und Dateisystems prüft `extract()`.

### Inhaltstypen

Reguläre Dateien und Verzeichnisse sind erlaubt. Symbolische Links, TAR-Hardlinks, besondere Objekte und nicht unterstützte Formatfunktionen werden als Verstöße gemeldet.

### Ressourcenlimits

Geprüft werden Archivdateigröße, Anzahl der Dateien/Verzeichnisse und gezählten Formatelemente, entpackte Größe jedes Elements, gesamtes entpacktes Volumen und optional das Kompressionsverhältnis.

### ZIP-Inhalt

Nach der Metadatenprüfung werden akzeptierte reguläre ZIP-Einträge bis EOF als Streams gelesen. Die tatsächliche entpackte Byteanzahl wird mit den akzeptierten Metadaten verglichen. `inspect()` prüft daher nicht nur das zentrale ZIP-Verzeichnis, sondern auch die Lesbarkeit; beschädigte Daten werden nicht wegen scheinbar gültiger Metadaten akzeptiert.

### Zusammenhang zwischen `inspect()` und `extract()`

Beide Methoden verwenden dieselben Eingaben zur Archivprüfung:

```text
archivePath
ArchivePolicy
password
```

Bei identischem Archivinhalt, `ArchivePolicy` und `password` gelten dieselben Annahmeregeln. Erreicht `extract()` die Archivprüfung und lehnt diese das Archiv ab, wird vor Atomic-Veröffentlichung oder Merge-Anwendung eine `ArchiveRejectedException` mit dem zugehörigen `InspectionResult` ausgelöst.

Fehler von Arbeitsbereich, Ziel oder Sperre können zuvor auftreten. `destinationPath` und `ExtractionOptions` steuern die Dateisystemanwendung, nicht die Archivannahme. Rechte, Merge-Konflikte, Sperrstatus, Dateisystem-Aliase und gleichzeitige Änderungen werden erst bei `extract()` geprüft.

### Besonderheiten von TAR und TAR.GZ

TAR und TAR.GZ unterstützen die implementierte Teilmenge regulärer Header und GNU/PAX-Erweiterungen. Einzelheiten stehen in der [Formatreferenz](../reference/supported-archives.md).
[← Zurück zum README](../README.md)
