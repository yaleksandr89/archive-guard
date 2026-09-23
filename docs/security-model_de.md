# Sicherheitsmodell

Diese Seite erklärt, vor welchen typischen Problemen bei der Archivverarbeitung das Paket
schützt, welche Prüfungen es durchführt und welche Risiken weiterhin von der Anwendung
kontrolliert werden müssen.

Beschrieben wird das Verhalten der Bibliothek, nicht das Ergebnis eines formalen
Sicherheitsaudits.

## Welche Archivdaten als potenziell gefährlich gelten

Daten aus dem Archiv selbst werden als potenziell gefährlich behandelt. Geprüft werden:

- Dateinamen und Pfade;
- Inhaltstyp: reguläre Datei, Verzeichnis, Link oder spezielles Objekt;
- deklarierte Dateigrößen;
- gesamtes entpacktes Datenvolumen;
- Kompressionsinformationen;
- zusätzliche Funktionen der Formate ZIP, TAR und TAR.GZ.

Der Grund ist einfach: Ein Archiv kann strukturell gültig sein und trotzdem einen Pfad wie
`../file`, einen symbolischen Link, eine riesige entpackte Datei oder eine Formatfunktion
enthalten, die die Anwendung nicht erwartet.

## Was `inspect()` macht

[`ArchiveGuard::inspect()`](../src/ArchiveGuard.php) prüft ein Archiv, ohne Dateien zu
extrahieren.

Die Prüfung umfasst:

- Normalisierung und Validierung von Pfaden;
- Erkennung kollidierender Pfade;
- Prüfung des Typs jedes Elements: Datei, Verzeichnis, Link oder spezielles Objekt;
- Grenzen aus [`ArchivePolicy`](../src/ArchivePolicy.php);
- ZIP-, TAR- und TAR.GZ-spezifische Prüfungen.

Wenn ein Archiv strukturell gültig ist, aber gegen eine Regel verstößt, erhält die Anwendung
ein [`InspectionResult`](../src/InspectionResult.php) mit den Gründen. Kann die Datei selbst
nicht zuverlässig gelesen oder geparst werden, wird `ArchiveOpenException` ausgelöst.

## Was `extract()` vor dem Schreiben macht

[`ArchiveGuard::extract()`](../src/ArchiveGuard.php) prüft den aktuellen Zustand des Archivs
immer unmittelbar vor der Extraktion. Ein früher erhaltenes `InspectionResult` gilt nicht als
Schreibfreigabe.

Danach:

1. wird geprüft, dass das Zielverzeichnis bereits existiert und leer ist;
2. wird geprüft, dass das Zielverzeichnis selbst kein symbolischer Link ist;
3. werden vorhandene Dateien nicht überschrieben;
4. werden die tatsächliche Größe jeder Datei und das gesamte entpackte Volumen mit den
   konfigurierten Grenzen und den bei der Prüfung ermittelten Größen verglichen. Wenn mehr
   Daten erscheinen als erlaubt, wird die Extraktion gestoppt;
5. werden TAR und TAR.GZ für die Extraktion ein zweites Mal gelesen. Reihenfolge, Pfad, Typ
   und Größe jedes Elements werden mit der ersten Prüfung verglichen; ändert sich das Archiv
   zwischen den Durchläufen, wird die Extraktion gestoppt.

Das Zielverzeichnis wird von der Anwendung erstellt, nicht von der Bibliothek. Die aktuelle
Version arbeitet nur mit einem separaten leeren Verzeichnis. Wenn eine Anwendung
Extraktionsergebnisse in einem gemeinsamen Ordner speichert, muss sie für jeden Vorgang ein
neues leeres Unterverzeichnis anlegen.

## Ressourcenlimits

Vier Pflichtgrenzen beschränken:

- Größe der Quellarchivdatei;
- Anzahl von Dateien, Ordnern und berücksichtigten Format-Metadatenelementen;
- Größe einer Datei oder eines anderen Elements nach dem Entpacken;
- gesamtes entpacktes Datenvolumen.

Das optionale `maxCompressionRatio` fügt eine weitere Prüfung gegen übermäßige Expansion
komprimierter Daten hinzu:

- ZIP verwendet Größen aus Metadaten;
- TAR.GZ verwendet die tatsächlich vom Dekompressor erzeugte Datenmenge;
- normales TAR verwendet diese Grenze nicht.

Das ist eine zusätzliche Heuristik und kein Ersatz für die Hauptgrößenlimits.

## Besonderheiten unter Windows

Einige Namen sind in ZIP oder TAR gültig, können unter Windows aber nicht sicher angelegt
werden.

Vor dem Schreiben lehnt das Paket zusätzlich zum Beispiel ab:

- reservierte Gerätenamen wie `CON`, `NUL`, `PRN` und ähnliche;
- unter Windows unzulässige Zeichen;
- Namen mit abschließendem Punkt oder Leerzeichen;
- Pfade, die sich nur in der ASCII-Groß-/Kleinschreibung unterscheiden, wenn Windows sie als
  denselben Pfad behandelt.

Daher kann `inspect()` ein Archiv allgemein akzeptieren, während `extract()` unter Windows
vor dem ersten Schreiben mit `ExtractionException` stoppt.

## Einschränkungen und ihre Gründe

### Die Extraktion ist nicht atomar

Dateien werden direkt in das vorbereitete Zielverzeichnis geschrieben. Wenn der Vorgang nach
mehreren erfolgreich geschriebenen Dateien abbricht, bleiben diese Dateien auf der Festplatte.

Ein vollständig atomarer Ablauf würde erfordern, zunächst alles in ein temporäres Verzeichnis
zu entpacken und das vollständige Ergebnis anschließend in einem einzigen letzten Schritt zu
ersetzen oder zu verschieben. Ein solches Verschieben hat je nach Dateisystem und unter
Windows unterschiedliche Einschränkungen und würde einen eigenen öffentlichen Vertrag
erfordern. Die aktuelle Version verspricht daher keine Atomarität.

### Das Verzeichnis wird nicht gegen andere Prozesse gesperrt

Die aktuelle Version setzt keine Systemsperre auf das Zielverzeichnis. Wenn ein anderer
Prozess dessen Inhalt gleichzeitig verändert, garantieren die Prüfungen der Bibliothek keinen
Schutz vor diesen Änderungen.

Verwende für die Extraktion ein separates Verzeichnis, auf das während des Vorgangs nur deine
Anwendung zugreifen kann.

### Berechtigungen und Besitzer aus dem Archiv werden nicht wiederhergestellt

Ein Archiv kann eigene Berechtigungen, Besitzer und Zeitstempel enthalten. Die aktuelle
Version extrahiert Dateiinhalte und Verzeichnisstruktur, wendet diese Werte aus dem Archiv
aber nicht an.

### Nicht jede ZIP- oder TAR-Funktion wird unterstützt

Wenn die Bibliothek eine Formatfunktion nicht zuverlässig genug verstehen kann, lehnt sie das
Archiv ab, statt das Verhalten zu erraten. Die aktuelle Version extrahiert zum Beispiel keine
verschlüsselten ZIP-Elemente.

Die genaue Liste der Einschränkungen steht in der
[Referenz zu unterstützten Archiven](reference/supported-archives_de.md).

## Praktische Empfehlungen

- wähle `ArchivePolicy`-Grenzen passend zu den realen Archivgrößen deiner Anwendung;
- verwende für jeden Extraktionsvorgang ein separates leeres Unterverzeichnis;
- verhindere, dass nicht vertrauenswürdige Prozesse dieses Verzeichnis gleichzeitig ändern;
- behandle Prüfverstöße, Öffnungsfehler und Schreibfehler getrennt;
- wenn die Anwendung ein temporäres Verzeichnis selbst erstellt und vollständig besitzt, kann
  sie es nach einer fehlgeschlagenen Extraktion nach ihren eigenen Regeln löschen.

[← Zurück zum README](readme/README_de.md)
