# Politique, violations et erreurs

Cette référence présente les limites de vérification, les modes d’extraction, les résultats, les codes de violation et les exceptions possibles.

## ArchivePolicy

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) est passé à chaque appel de `inspect()` ou
`extract()` et définit des **valeurs maximales autorisées**, pas des valeurs exactes attendues.

```php
<?php

use Yaleksandr\ArchiveGuard\ArchivePolicy;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
    maxCompressionRatio: 100.0,
);
```

| Paramètre | Ce qu'il limite | Valeur autorisée |
| --- | --- | --- |
| `maxArchiveBytes` | Taille maximale du fichier d'archive lui-même | Entier supérieur à zéro |
| `maxEntries` | Nombre maximal de fichiers et dossiers dans l'archive ; pour TAR, certains éléments de métadonnées du format sont comptés dans la même limite | Entier supérieur à zéro |
| `maxEntryUncompressedBytes` | Taille maximale d'un fichier ou autre élément après décompression | Entier supérieur à zéro |
| `maxTotalUncompressedBytes` | Volume total maximal des données décompressées | Entier supérieur à zéro |
| `maxCompressionRatio` | Limite supplémentaire du rapport entre taille décompressée et taille compressée | `null` ou nombre fini supérieur à zéro |

Les quatre premiers paramètres n'ont pas de valeur par défaut : l'application doit les choisir
explicitement. `maxCompressionRatio` est facultatif et vaut `null` par défaut.

La vérification du rapport de compression dépend du format :

- ZIP utilise les tailles présentes dans les métadonnées de l'élément ;
- TAR.GZ utilise la quantité réelle de données produite par le décompresseur GZIP ;
- TAR non compressé n'utilise pas cette limite.

Des valeurs de constructeur invalides déclenchent l'`InvalidArgumentException` standard.

## InspectionResult

[`InspectionResult`](../../../../src/InspectionResult.php) n'est renvoyé que lorsque la source a pu
être ouverte et analysée comme une archive prise en charge.

Méthodes disponibles :

- `format()` — renvoie [`ArchiveFormat`](../../../../src/ArchiveFormat.php) : `zip`, `tar` ou
  `tar.gz` ;
- `isAccepted()` — renvoie `true` lorsqu'il n'y a aucune violation ;
- `violations()` — renvoie une liste d'objets [`Violation`](../../../../src/Violation.php).

Si le fichier ne peut pas être ouvert, reconnu ou analysé correctement,
`ArchiveOpenException` est levée à la place d'un résultat.

## ExtractionOptions

[`ExtractionOptions`](../../../../src/ExtractionOptions.php) est obligatoire pour `extract()` et possède deux fabriques nommées :

```php
ExtractionOptions::atomic();

ExtractionOptions::merge(
    ExtractionConflictStrategy::Reject,
);
```

`atomic()` publie le résultat dans un nouveau répertoire qui ne doit pas exister auparavant. `merge(...)` applique le résultat à un répertoire existant et exige une [`ExtractionConflictStrategy`](../../../../src/ExtractionConflictStrategy.php) :

- `Reject` : un fichier ordinaire existant est un conflit identifié avant toute application Merge ;
- `Skip` : conserver le fichier ordinaire existant ;
- `Overwrite` : remplacer ce fichier par un fichier ordinaire provenant de l'archive.

Un répertoire existant est réutilisé si l'archive possède elle aussi un répertoire au même chemin. Les conflits de type fichier/répertoire, liens et objets spéciaux concernés sont rejetés quelle que soit la stratégie.

## ExtractionResult

[`ExtractionResult`](../../../../src/ExtractionResult.php) décrit les modifications réellement appliquées :

- `format()` : format de l'archive ;
- `filesExtracted()` : fichiers nouveaux et remplacés réellement appliqués ;
- `directoriesCreated()` : répertoires réellement créés ;
- `bytesWritten()` : octets de contenu des fichiers nouveaux et remplacés appliqués ;
- `filesSkipped()` : fichiers effectivement ignorés ;
- `filesOverwritten()` : fichiers existants réellement remplacés.

En mode Atomic, `filesSkipped()` et `filesOverwritten()` valent zéro.

## Violation

[`Violation`](../../../../src/Violation.php) expose trois propriétés publiques :

- `code` — valeur [`ViolationCode`](../../../../src/ViolationCode.php) destinée au traitement
  programmatique ;
- `message` — courte description de diagnostic ;
- `entryName` — nom du fichier, du répertoire ou d'un autre élément, ou `null` si la
  violation concerne l'archive entière.

Dans le code, utilisez `code` plutôt que de comparer le texte de `message`.

## ViolationCode

| Valeur | Signification |
| --- | --- |
| `unsafe_path` | Le chemin peut sortir de la structure autorisée ou présenter une forme invalide |
| `path_collision` | Deux chemins sont en conflit après normalisation ou comparaison propre à la plateforme |
| `platform_incompatible_path` | Un composant ne respecte pas les règles déterministes de nommage de la plateforme |
| `symlink_entry` | Une entrée est un lien symbolique |
| `hardlink_entry` | Une entrée TAR est un lien physique |
| `special_entry` | Un objet spécial, tel qu’un périphérique, a été détecté |
| `archive_too_large` | Le fichier d’archive dépasse `maxArchiveBytes` |
| `too_many_entries` | Le nombre de fichiers, répertoires et métadonnées comptabilisées dépasse `maxEntries` |
| `entry_too_large` | Un élément décompressé dépasse `maxEntryUncompressedBytes` |
| `total_size_exceeded` | Le volume décompressé total dépasse `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | Le rapport taille décompressée/compressée dépasse la limite |
| `encrypted_entry` | Le ZIP contient une entrée chiffrée sans mot de passe fourni |
| `unsupported_encryption` | Le mot de passe est fourni mais l’environnement ne prend pas en charge la méthode de chiffrement |
| `decryption_failed` | Le contenu chiffré n’a pas pu être déchiffré ou lu |
| `unsupported_compression` | L’environnement ne prend pas en charge la méthode de compression ZIP |
| `unsupported_feature` | L’archive utilise une fonctionnalité de format non prise en charge |

`decryption_failed` signifie échec de déchiffrement ou de lecture ; `ZipArchive`/`libzip` ne peut pas toujours en déterminer la cause exacte. `entryName` n’existe pas pour toute violation : le dépassement de taille de l’archive entière ne désigne aucune entrée particulière.

## Exceptions

Toutes les exceptions du paquet héritent de [`ArchiveGuardException`](../../../../src/Exception/ArchiveGuardException.php), elle-même dérivée de `RuntimeException`.

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) : source impossible à ouvrir, format inconnu, structure corrompue ou contenu ZIP non chiffré accepté mais illisible. Une erreur de déchiffrement/lecture de données chiffrées avec mot de passe produit plutôt `decryption_failed` (et `ArchiveRejectedException` dans `extract()`).
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php) : l'archive est refusée avant publication Atomic ou application Merge. `inspectionResult()` donne les raisons. Des fichiers peuvent déjà avoir été créés dans l'espace temporaire avant que le refus complet soit observable.
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php) : erreur de destination, verrou, préparation temporaire, vérification préalable, application Merge, publication Atomic ou E/S du système de fichiers.

`InvalidArgumentException` n'hérite pas de `ArchiveGuardException` ; elle signale des paramètres `ArchivePolicy` invalides ou un `password` non nul avec TAR/TAR.GZ, formats sans chiffrement par mot de passe intégré.

## Comment distinguer un résultat d'une exception

### `inspect()`

- Si l'archive est acceptée, la méthode renvoie `InspectionResult` avec `isAccepted() === true`.
- En présence de violations, elle renvoie `InspectionResult` avec `isAccepted() === false`.
- Une source impossible à ouvrir/analyser ou des données ZIP non chiffrées acceptées mais illisibles provoquent `ArchiveOpenException`.
- `password` est autorisé pour ZIP ; un mot de passe non nul pour TAR/TAR.GZ provoque `InvalidArgumentException`.

### `extract()`

Arguments :

```text
archivePath
destinationPath
ArchivePolicy
ExtractionOptions
password = null
```

Pour `archivePath`, `ArchivePolicy` et `password`, les règles d'acceptation sont identiques à celles de `inspect()`.

- Un rejet déclenche `ArchiveRejectedException` avant publication Atomic ou application Merge ; une vérification complète peut se terminer après création de l'espace temporaire. `inspectionResult()` fournit les raisons.
- Une erreur de source, structure ou lecture ZIP non chiffrée acceptée déclenche `ArchiveOpenException`.
- Une erreur de destination, de conflit préalable ou du système de fichiers déclenche `ExtractionException`.
- En cas de réussite, `ExtractionResult` est renvoyé.

Un dépassement de `maxArchiveBytes` est rejeté avant le contrôle de validité du mot de passe TAR/TAR.GZ.

Exemples : [`inspect()`](../guides/inspection.md) et [`extract()`](../guides/extraction.md).

[← Retour au README](../README.md)
