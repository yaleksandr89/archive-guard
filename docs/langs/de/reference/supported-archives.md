# Unterstützte Archive

Diese Referenz beschreibt, welche ZIP-, TAR- und TAR.GZ-Varianten das Paket versteht und
welche Formatfunktionen bewusst abgelehnt werden.

## Formaterkennung

Das Format wird anhand des Dateiinhalts und nicht anhand der Dateiendung erkannt:

- ZIP — anhand der ZIP-Signatur;
- TAR.GZ — anhand der GZIP-Signatur;
- TAR — anhand eines gültigen TAR-Headers oder eines leeren TAR-Blocks.

Die Quelle muss eine lesbare reguläre lokale Datei sein. Ein Pfad mit `://` wird abgelehnt.

Wenn der Inhalt nicht erkannt werden kann oder die Archivstruktur beschädigt ist, wird
[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) ausgelöst.

Kann ein akzeptierter unverschlüsselter ZIP-Eintrag nicht zuverlässig gelesen werden, folgt `ArchiveOpenException`; Entschlüsselungs-/Lesefehler verschlüsselter Inhalte mit Passwort ergeben dagegen `decryption_failed`.

## ZIP

Reguläre ZIP-Dateien und Verzeichnisse werden nach erfolgreicher Pfad-, Ressourcen-, Kompressionsmethoden- und tatsächlicher Lesbarkeitsprüfung akzeptiert.

Zusätzlich gilt:

- symbolische Links und besondere Eintragstypen werden abgelehnt;
- Verzeichniseinträge mit Daten ungleich null werden abgelehnt;
- das Kompressionsverfahren muss vom aktuellen `ZipArchive` unterstützt werden, sonst folgt `unsupported_compression`;
- das optionale `maxCompressionRatio` verwendet ZIP-Metadaten;
- akzeptierte reguläre Inhalte werden als Stream bis EOF gelesen, die tatsächliche entpackte Größe wird mit den akzeptierten Metadaten verglichen.

### Verschlüsselte ZIP-Archive

`inspect()` und `extract()` verwenden dasselbe optionale Argument `password`:

- verschlüsselter Eintrag ohne Passwort ergibt `encrypted_entry`;
- nicht unterstützte Verschlüsselungsmethode ergibt `unsupported_encryption`;
- Fehler beim Entschlüsseln oder Lesen verschlüsselter Inhalte ergibt `decryption_failed`;
- ein korrektes Passwort erlaubt verschlüsselte und gemischte ZIP-Archive, wenn die übrigen Prüfungen bestehen.

Ein angegebenes Passwort verhindert die Prüfung unverschlüsselter ZIP-Dateien nicht. Die leere Zeichenkette `''` gilt als angegebenes Passwort, nicht als `null`. Die tatsächliche Unterstützung hängt von `ZipArchive`/`libzip` in der Laufzeit ab.

## TAR

Reguläre Dateien und Verzeichnisse, USTAR-Header und ein begrenzter Satz GNU/PAX-Erweiterungen
werden unterstützt, soweit sie zur korrekten Bestimmung von Name, Größe und anderen erlaubten
Metadaten benötigt werden.

Wichtig:

- symbolische Links und Hardlinks werden abgelehnt;
- spezielle und Sparse-Elemente werden abgelehnt;
- ein Verzeichnis mit Daten ungleich null wird abgelehnt;
- unbekannte PAX-Felder und nicht unterstützte Erweiterungen werden abgelehnt;
- kollidierende oder wiederholte lokale Erweiterungen werden abgelehnt;
- Metadatenelemente von Erweiterungen zählen ebenfalls zu den Grenzen für Elementanzahl und
  Datenvolumen.

Ein beschädigter Header oder eine ungültige TAR-Struktur führt zu `ArchiveOpenException` und
nicht zu einem normalen Policy-Verstoß.

`maxCompressionRatio` wird bei unkomprimiertem TAR nicht angewendet.

TAR kennt keine integrierte Passwortverschlüsselung. `password` muss `null` sein; sonst wird `InvalidArgumentException` ausgelöst.

## TAR.GZ

Die von der GZIP-Dekompression erzeugten Daten werden nach denselben Regeln wie normales TAR
geprüft.

Wenn `maxCompressionRatio` gesetzt ist, wird die tatsächliche vom GZIP-Dekompressor erzeugte
Datenmenge gezählt. Dadurch kann eine übermäßige Expansion komprimierter Daten begrenzt werden,
bevor Dateien auf die Festplatte geschrieben werden.

Zusätzliche Daten nach dem abgeschlossenen GZIP-Stream sowie mehrere aneinandergehängte
GZIP-Streams werden nicht unterstützt und gelten als Fehler der Archivstruktur.

Wie TAR bietet TAR.GZ keine integrierte Passwortverschlüsselung. Ein `password` ungleich `null` führt zu `InvalidArgumentException`.

## Plattformkompatibilität von Pfaden

Allgemeine Regeln für sichere Pfade gelten bei allen Formaten. `inspect()` prüft zusätzlich deterministische Namensbeschränkungen der aktuellen Plattform.

Unter Windows gehören dazu:

- gültiges UTF-8 in Pfadkomponenten;
- verbotene Win32- und Steuerzeichen;
- abschließende Punkte oder Leerzeichen;
- reservierte Gerätenamen;
- ASCII-Groß-/Kleinschreibungskollisionen.

Ein inkompatibler Name ergibt `platform_incompatible_path`, eine plattformbedingte Pfadkollision eventuell `path_collision`. Diese Regeln werden in `inspect()` geprüft; dateisystem- und zielabhängige Bedingungen in `extract()`.

## Format- und Funktionsfehler

Verwendet ein unterstütztes Format eine nicht verarbeitbare Funktion, gibt die Prüfung einen Code wie `unsupported_encryption`, `unsupported_compression` oder `unsupported_feature` zurück.

Beschädigte Struktur oder akzeptierte, aber unlesbare unverschlüsselte ZIP-Daten führen zu `ArchiveOpenException`. Die [Verstoßreferenz](policy-and-errors.md) enthält sämtliche Codes.

[← Zurück zur README](../README.md)
