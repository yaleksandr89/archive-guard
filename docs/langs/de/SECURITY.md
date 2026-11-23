# Sicherheitsrichtlinie

## Sprache auswählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/SECURITY.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/SECURITY.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/SECURITY.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/SECURITY.md) | **Ausgewählt** |

## Unterstützte Versionen

Sicherheitskorrekturen werden für die aktuelle stabile `1.x`-Linie veröffentlicht.

| Version | Unterstützt |
|---|---|
| `1.x` | Ja |

## Was als Sicherheitslücke gilt

Sicherheitsprobleme sind insbesondere:

- Umgehen der Pfadprüfung mit Schreibzugriff außerhalb des erwarteten Ziels;
- Extrahieren von symbolischen Links, Hardlinks, besonderen Objekten oder anderen abzulehnenden Einträgen;
- Umgehen von `ArchivePolicy`, wodurch Daten über die Limits hinaus angewendet werden;
- Unterschiede zwischen geprüftem und tatsächlich angewendetem Inhalt;
- Umgehen deterministischer Plattformnamens- oder Pfadkollisionsregeln mit unerwartetem Schreibort;
- Verletzung des Atomic-Vertrags, wenn das endgültige Ziel vor Abschluss der Prüfungen als erfolgreich veröffentlicht sichtbar wird;
- Umgehen der Merge-Vorprüfung oder Konfliktstrategie mit unerwartetem Ersetzen eines Objekts;
- Umgehen einer kooperativen Sperre zwischen zwei protokollkonformen Archive-Guard-Operationen auf dasselbe Ziel;
- Offenlegung eines ZIP-Passworts über Ausnahme, Verstoß, Ergebnis, Zustand oder Diagnose;
- ZIP-, TAR- oder TAR.GZ-Parsing-/Lesefehler mit konkreter Sicherheitsauswirkung;
- Kompromittierung von Quellcode, CI, Release-Tag, Paket oder anderen Supply-Chain-Komponenten.

## Grenzen des Sicherheitsmodells

Atomic veröffentlicht das vollständig vorbereitete Ergebnis in einem neuen Verzeichnis. Merge bereitet zunächst alles temporär vor, prüft Konflikte und wendet danach die gewählte Strategie an. Gleichzeitig laufende Archive-Guard-Operationen auf demselben Ziel koordinieren sich über `.archive-guard-locks`.

Die unterstützten ZIP-Verschlüsselungsverfahren hängen von `ZipArchive`/`libzip` in der aktuellen Laufzeit ab. Das Umgehen dieser Prüfungen, Anwendungsmodi oder des Passwortschutzes ist als potenzielle Sicherheitslücke zu behandeln.

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

- Extraktionsmodus: Atomic oder Merge und ggf. die Konfliktstrategie;
- ob ein ZIP-Passwort verwendet wird, **ohne das echte Passwort mitzuteilen**;
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
