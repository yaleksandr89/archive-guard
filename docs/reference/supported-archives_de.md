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
[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) ausgelöst.

## ZIP

Das Paket akzeptiert reguläre ZIP-Dateien und -Verzeichnisse, wenn sie die allgemeinen Pfad-
und Ressourcenprüfungen bestehen.

Zusätzlich gilt:

- symbolische Links und spezielle Elementtypen werden abgelehnt;
- ein Verzeichnis mit Daten ungleich null wird als nicht unterstützte Funktion abgelehnt;
- ein verschlüsseltes Element erzeugt den Verstoß `encrypted_entry`;
- das Paket **fragt kein Passwort ab** und extrahiert keine verschlüsselten Inhalte;
- die Kompressionsmethode muss vom aktuellen `ZipArchive` unterstützt werden, sonst wird
  `unsupported_compression` zurückgegeben;
- wenn `maxCompressionRatio` gesetzt ist, wird das Verhältnis anhand der ZIP-Metadaten
  bewertet.

Die Prüfung des Kompressionsverhältnisses bleibt eine zusätzliche Heuristik. Das tatsächlich
geschriebene Volumen wird während der Extraktion erneut kontrolliert.

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

## TAR.GZ

Die von der GZIP-Dekompression erzeugten Daten werden nach denselben Regeln wie normales TAR
geprüft.

Wenn `maxCompressionRatio` gesetzt ist, wird die tatsächliche vom GZIP-Dekompressor erzeugte
Datenmenge gezählt. Dadurch kann eine übermäßige Expansion komprimierter Daten begrenzt werden,
bevor Dateien auf die Festplatte geschrieben werden.

Zusätzliche Daten nach dem abgeschlossenen GZIP-Stream sowie mehrere aneinandergehängte
GZIP-Streams werden nicht unterstützt und gelten als Fehler der Archivstruktur.

## Wenn eine Formatfunktion nicht unterstützt wird

Das Paket lehnt eine unbekannte oder nicht unterstützte Archivvariante lieber ab, statt sie
nur teilweise zu extrahieren.

Je nach Situation ist das entweder:

- ein strukturierter Verstoß wie `encrypted_entry`, `symlink_entry` oder
  `unsupported_feature`;
- `ArchiveOpenException`, wenn die Archivstruktur beschädigt ist und nicht zuverlässig
  geparst werden kann.

Alle Verstoßcodes stehen in der
[Verstoßreferenz](policy-and-errors_de.md).

[← Zurück zum README](../readme/README_de.md)
