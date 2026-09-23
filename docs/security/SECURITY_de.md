# Sicherheitsrichtlinie

## Sprache auswählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_en.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_es.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_zh.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_fr.md) | **Ausgewählt** |

## Unterstützte Versionen

Sicherheitskorrekturen werden für die aktuelle stabile `1.x`-Linie veröffentlicht.

| Version | Unterstützt |
|---|---|
| `1.x` | Ja |

## Was als Sicherheitslücke gilt

Zu Sicherheitsproblemen gehören insbesondere:

- das Umgehen der Pfadvalidierung, sodass eine Datei außerhalb des Zielverzeichnisses geschrieben werden kann;
- das Extrahieren eines symbolischen Links, Hardlinks, speziellen Objekts oder eines anderen Elements, das vom Paket abgelehnt werden sollte;
- das Umgehen von `ArchivePolicy`-Grenzen, sodass die Extraktion nach Überschreiten der erlaubten Größe eines Elements, des gesamten Datenvolumens oder einer anderen konfigurierten Grenze fortgesetzt wird;
- eine Abweichung zwischen geprüftem und tatsächlich extrahiertem Inhalt, durch die ungeprüfte Daten geschrieben werden können;
- das Umgehen von Windows-Namens- oder Pfadkollisionsprüfungen, sodass Daten an einen unerwarteten Ort geschrieben werden;
- ein Fehler beim Parsen von ZIP, TAR oder TAR.GZ mit konkreter Sicherheitsauswirkung, zum Beispiel ein unbeabsichtigtes Schreiben einer Datei;
- die Kompromittierung von Quellcode, CI, Release-Tags, veröffentlichten Paketen oder einem anderen Teil der Lieferkette.

## Was für sich allein keine Sicherheitslücke ist

Folgendes Verhalten ist eine dokumentierte Grenze der aktuellen Version:

- die Extraktion ist nicht atomar, und bereits erstellte Dateien können nach einem Fehler bestehen bleiben;
- das Zielverzeichnis wird nicht gegen gleichzeitige Änderungen durch andere Prozesse gesperrt;
- das Zielverzeichnis muss bereits existieren, leer sein und darf kein symbolischer Link sein;
- vorhandene Dateien und Verzeichnisse werden nicht überschrieben;
- verschlüsselte ZIP-Archive werden nicht extrahiert und es wird kein Passwort abgefragt;
- im Archiv gespeicherte Berechtigungen, Besitzer und Zeitstempel werden nicht wiederhergestellt;
- nicht unterstützte Formatfunktionen werden abgelehnt, statt teilweise verarbeitet zu werden.

Wenn das tatsächliche Verhalten eine dokumentierte Garantie verletzt oder eine Prüfung trotz Einhaltung der dokumentierten Nutzungsbedingungen umgangen werden kann, kann dennoch ein Sicherheitsproblem vorliegen.

## Sicherheitslücke melden

GitHub Private Vulnerability Reporting ist der bevorzugte Kanal, wenn er für das Repository verfügbar ist:

1. Öffnen Sie den Tab **Security** des Repositories.
2. Wechseln Sie zu **Advisories**.
3. Wählen Sie **Report a vulnerability**.
4. Senden Sie den Bericht, ohne Details in einem öffentlichen Issue zu veröffentlichen.

Wenn das private Formular nicht verfügbar ist, erstellen Sie ein minimales öffentliches Issue ohne Exploit-Code oder sensible Details und bitten Sie um einen privaten Kommunikationskanal.

Veröffentlichen Sie vor einem Fix nicht:

- einen direkt ausführbaren Exploit oder ein Archiv, das eine Schutzumgehung unmittelbar reproduziert;
- echte Geheimnisse, Tokens, Zugangsdaten oder private Daten;
- Produktionspfade, Dateiinhalte oder Logs mit sensiblen Informationen.

## Was der Bericht enthalten sollte

Wenn möglich, geben Sie an:

- betroffene Paketversion oder Commit-SHA;
- PHP-Version und Betriebssystem;
- Archivformat: ZIP, TAR oder TAR.GZ;
- Sicherheitsauswirkung;
- minimale Reproduktionsschritte;
- ein minimales synthetisches Archiv oder eine Anleitung zu dessen Erstellung;
- erwartetes und tatsächliches Verhalten;
- einen möglichen Fix, falls bekannt.

Verwenden Sie synthetische Daten. Hängen Sie keine echten Geheimnisse oder privaten Benutzerdateien an.

## Weiterer Ablauf

Das Projekt wird von einem Autor gepflegt, daher wird kein fester SLA garantiert. Der Bericht wird nach Möglichkeit geprüft; bei bestätigtem Problem werden ein Fix und eine Regressionprüfung vorbereitet.

Stimmen Sie die öffentliche Offenlegung technischer Details bitte vorher mit dem Maintainer ab. Ein Bug-Bounty-Programm wird nicht zugesichert.
