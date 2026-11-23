# Sicherheitsmodell

Diese Seite beschreibt die Prüfungen bei nicht vertrauenswürdigen Archiven, die Garantien von Atomic und Merge sowie Risiken, die die aufrufende Anwendung selbst beherrschen muss.

Sie beschreibt das Bibliotheksverhalten, **keinen** förmlichen Sicherheitsprüfbericht.

## Welche Archivdaten als potenziell gefährlich gelten

Archivdaten gelten als nicht vertrauenswürdig. Geprüft werden:

- Datei- und Pfadnamen, Kollisionen nach Normalisierung und deterministische Plattformregeln;
- Eintragstypen: reguläre Datei, Verzeichnis, Link oder besonderes Objekt;
- deklarierte und tatsächlich gelesene Größen;
- gesamte entpackte Datenmenge und Kompressionsangaben;
- ZIP-, TAR- und TAR.GZ-Funktionen;
- ZIP-Verschlüsselung und tatsächliche Lesbarkeit akzeptierter Inhalte.

Das Zielverzeichnis ist eine weitere Vertrauensgrenze: Beim Anwenden können dort Dateien, Verzeichnisse, Links oder andere Objekte existieren, die bei einer früheren Prüfung fehlten.

Auch ein strukturell gültiges Archiv kann `../file`, einen symbolischen Link, übermäßige entpackte Daten oder eine nicht unterstützte Funktion enthalten. Lesbare Struktur bedeutet nicht sichere Anwendung.

## Gemeinsame Semantik der Archivannahme

`inspect()` und `extract()` prüfen dieselben Archiveingaben:

```text
archivePath
ArchivePolicy
password
```

Beide Methoden wenden dafür dieselben Annahmeregeln an:

- `inspect()` liefert ein `InspectionResult` mit Ablehnungsgründen;
- `extract()` löst vor Atomic-Veröffentlichung oder Merge-Anwendung eine `ArchiveRejectedException` mit dem zugehörigen `InspectionResult` aus.

Fehler von Arbeitsbereich, Ziel oder kooperativer Sperre können auftreten, bevor die vollständige Archivannahme beobachtbar ist. `destinationPath` und `ExtractionOptions` legen nur fest, wie das Ergebnis auf das Dateisystem angewendet wird, nicht ob das Archiv akzeptiert wird.

## Was `inspect()` macht

[`ArchiveGuard::inspect()`](../../../src/ArchiveGuard.php) schreibt keine extrahierten Dateien auf die Festplatte. Es prüft:

- Pfadnormalisierung und Kollisionen;
- deterministische Namensbeschränkungen der Plattform;
- Typen aller Einträge;
- [`ArchivePolicy`](../../../src/ArchivePolicy.php)-Grenzwerte;
- unterstützte ZIP-, TAR- und TAR.GZ-Funktionen;
- für ZIP: Kompression, Verschlüsselung und Streaming-Lesbarkeit akzeptierter regulärer Dateien.

Unter Windows ergeben ungültige Komponenten `platform_incompatible_path`, ASCII-Groß-/Kleinschreibungskollisionen `path_collision`. Deterministische Namensregeln gelten bereits bei `inspect()`; ziel- und dateisystemabhängige Prüfungen erfolgen bei `extract()`.

## ZIP-Passwörter

Das optionale `password` ist ein sensibler Parameter:

- `null` bedeutet kein Passwort, `''` ein ausdrücklich angegebenes leeres Passwort;
- ein Passwort ist auch für unverschlüsselte ZIP-Dateien erlaubt;
- verschlüsselte Einträge ohne Passwort ergeben `encrypted_entry`;
- nicht unterstützte Verschlüsselungsmethoden ergeben `unsupported_encryption`;
- Entschlüsselungs- oder Lesefehler verschlüsselter Daten ergeben `decryption_failed`.

`decryption_failed` bezeichnet Entschlüsselungs-/Lesefehler; `ZipArchive`/`libzip` kann nicht immer feststellen, ob das Passwort falsch war. Das Argument trägt `#[SensitiveParameter]` und wird nicht in `ExtractionOptions`, `ExtractionResult`, Arbeitsbereich, Prüfdaten oder Diagnosemeldungen gespeichert.

TAR und TAR.GZ verfügen über keine integrierte Passwortverschlüsselung. `password` muss dort `null` sein; andere Werte lösen `InvalidArgumentException` aus.

## Was `extract()` macht

[`ArchiveGuard::extract()`](../../../src/ArchiveGuard.php) verlangt ausdrückliche [`ExtractionOptions`](../../../src/ExtractionOptions.php). Vor der Anwendung:

1. Quelle prüfen und Format erkennen.
2. `maxArchiveBytes` prüfen.
3. Passwortzulässigkeit für das Format prüfen.
4. `ExtractionWorkspace` initialisieren: Zielbedingungen prüfen, kooperative Sperre holen und temporäres Verzeichnis im Elternverzeichnis des Ziels anlegen.
5. Mit `ZipExtractor` oder `TarExtractor` das ganze Archiv temporär scannen, extrahieren und prüfen.
6. Atomic veröffentlichen oder Merge vorprüfen und anwenden.
7. Arbeitsbereich bereinigen und Sperre freigeben.

Fehler von Ziel, Sperre oder Arbeitsbereich können vor dem vollständigen Archivergebnis auftreten, ohne die gemeinsamen Annahmeregeln zu ändern.

Bei TAR/TAR.GZ werden Reihenfolge, Pfad, Typ und Größe jedes Eintrags mit dem Prüfdurchlauf verglichen. Änderungen der Quelle zwischen den Durchläufen stoppen die Extraktion.

## Atomic

`ExtractionOptions::atomic()` erfordert ein neues endgültiges Verzeichnis, das **noch nicht existieren darf**. Die Bibliothek extrahiert und prüft alles temporär im Elternverzeichnis und veröffentlicht es dann mit `rename()` unter dem zuvor fehlenden Zielpfad. Ein Fehler vor der Veröffentlichung entfernt das temporäre Verzeichnis.

## Merge

`ExtractionOptions::merge(...)` gilt für ein **bestehendes echtes Verzeichnis**. Das Archiv wird erst vollständig temporär vorbereitet und alle Konflikte werden geprüft, bevor Zieländerungen beginnen. Die gewählte Strategie behandelt vorhandene reguläre Dateien so:

- `Reject`: Konflikt vor Anwendung ablehnen;
- `Skip`: vorhandene reguläre Datei beibehalten;
- `Overwrite`: vorhandene reguläre Datei durch die vorbereitete ersetzen.

Übereinstimmende Verzeichnisse werden wiederverwendet; Datei-/Verzeichniskonflikte, Links und besondere Objekte auf betroffenen Pfaden werden abgelehnt.

Merge wendet Änderungen nacheinander an. Ein Dateisystemfehler löst `ExtractionException` aus und stoppt weitere Änderungen; **bereits angewendete Änderungen bleiben erhalten**. Merge ist somit nicht transaktional. Das temporäre Verzeichnis wird anschließend entfernt.

## Kooperative Sperre

Archive Guard nutzt `.archive-guard-locks` automatisch zur Koordination eigener gleichzeitiger Operationen auf demselben Ziel. Diese **kooperative Sperre** verhindert nicht, dass beliebige externe Prozesse das Ziel verändern.

## Ressourcenlimits

Vier verbindliche Grenzen betreffen Archivdateigröße, Eintragszahl (einschließlich gezählter Metadatenelemente), entpackte Einzelgröße und gesamte entpackte Datenmenge. Optionales `maxCompressionRatio` ergänzt:

- ZIP nutzt Metadatengrößen;
- TAR.GZ nutzt die tatsächliche Ausgabe des Dekompressors;
- unkomprimiertes TAR nutzt dieses Verhältnis nicht.

Akzeptierte ZIP-Einträge werden außerdem als Stream auf tatsächliche Lesbarkeit und Größe geprüft, nicht nur anhand von Metadaten.

## Praktische Empfehlungen

- Wählen Sie `ArchivePolicy`-Grenzen passend zu echten Daten und Ressourcen.
- Nutzen Sie Atomic für ein neues Verzeichnis und Merge für ein bestehendes mit bewusst gewählter Konfliktstrategie.
- Geben Sie ZIP-Passwörter direkt im Aufruf an und protokollieren Sie sie niemals.
- Behandeln Sie `InspectionResult`, `ArchiveOpenException`, `ArchiveRejectedException` und `ExtractionException` getrennt.
- Für Merge brauchen die Elternverzeichnisse konkret veränderter Objekte Schreibrechte; das Elternverzeichnis des Ziels muss temporären Bereich und Sperren erlauben. `Skip` braucht für eine unveränderte Konfliktdatei keinen zusätzlichen Schreibzugriff. Unter Windows muss eine überschriebene Datei selbst beschreibbar sein; unter POSIX kann das beschreibbare Elternverzeichnis den Ersatz einer schreibgeschützten Datei erlauben.
- Verhindern Sie gleichzeitige Änderungen durch nicht kooperierende Prozesse. Die Bibliothek extrahiert Inhalte und Verzeichnisse, stellt aber **keine** Archivberechtigungen, Eigentümer oder Zeitstempel wieder her. Windows hat zusätzliche Namensbeschränkungen bei der Prüfung.

Details stehen in den [unterstützten Formaten](reference/supported-archives.md) und der [Extraktionsanleitung](guides/extraction.md).

[← Zurück zur README](README.md)
