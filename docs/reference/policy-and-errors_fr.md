# Politique, violations et erreurs

Cette référence décrit les paramètres de vérification, le résultat de `inspect()`, les codes de
violation et les exceptions qu'une application peut recevoir en utilisant le paquet.

## ArchivePolicy

[`ArchivePolicy`](../../src/ArchivePolicy.php) est passé à chaque appel de `inspect()` ou
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

[`InspectionResult`](../../src/InspectionResult.php) n'est renvoyé que lorsque la source a pu
être ouverte et analysée comme une archive prise en charge.

Méthodes disponibles :

- `format()` — renvoie [`ArchiveFormat`](../../src/ArchiveFormat.php) : `zip`, `tar` ou
  `tar.gz` ;
- `isAccepted()` — renvoie `true` lorsqu'il n'y a aucune violation ;
- `violations()` — renvoie une liste d'objets [`Violation`](../../src/Violation.php).

Si le fichier ne peut pas être ouvert, reconnu ou analysé correctement,
`ArchiveOpenException` est levée à la place d'un résultat.

## Violation

[`Violation`](../../src/Violation.php) expose trois propriétés publiques :

- `code` — valeur [`ViolationCode`](../../src/ViolationCode.php) destinée au traitement
  programmatique ;
- `message` — courte description de diagnostic ;
- `entryName` — nom du fichier, du répertoire ou d'un autre élément, ou `null` si la
  violation concerne l'archive entière.

Dans le code, utilisez `code` plutôt que de comparer le texte de `message`.

## ViolationCode

| Valeur | Signification |
| --- | --- |
| `unsafe_path` | Le chemin de l'élément peut sortir de la structure de répertoires autorisée ou possède une forme invalide |
| `path_collision` | Deux chemins normalisés sont identiques ou entrent en conflit comme fichier et répertoire |
| `symlink_entry` | Un lien symbolique a été trouvé dans l'archive |
| `hardlink_entry` | Un lien physique a été trouvé dans TAR |
| `special_entry` | Un objet spécial, par exemple un périphérique, a été trouvé |
| `archive_too_large` | La taille du fichier d'archive dépasse `maxArchiveBytes` |
| `too_many_entries` | Le nombre de fichiers, dossiers et éléments de métadonnées comptabilisés dépasse `maxEntries` |
| `entry_too_large` | Un fichier ou autre élément dépasse `maxEntryUncompressedBytes` après décompression |
| `total_size_exceeded` | Le volume total décompressé dépasse `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | Le rapport configuré entre taille décompressée et taille compressée a été dépassé |
| `encrypted_entry` | ZIP contient un élément chiffré ; la version actuelle ne demande pas de mot de passe |
| `unsupported_compression` | La méthode de compression ZIP n'est pas prise en charge par l'environnement pour l'extraction |
| `unsupported_feature` | L'archive utilise une fonctionnalité de format que le paquet ne prend pas en charge |

`entryName` n'est pas présent pour toutes les violations. Par exemple, le dépassement de la
taille du fichier d'archive concerne l'archive entière ; aucun nom d'élément individuel n'est
donc disponible.

## Exceptions

Toutes les exceptions du paquet héritent de
[`ArchiveGuardException`](../../src/Exception/ArchiveGuardException.php), qui hérite elle-même
de `RuntimeException`.

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — le fichier source
  ne peut pas être ouvert, le format n'est pas reconnu ou la structure est endommagée.
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — l'archive
  n'a pas réussi la vérification obligatoire avant extraction. `inspectionResult()` renvoie
  les raisons.
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — problème concernant
  le répertoire de destination, le chemin cible ou l'écriture.

`InvalidArgumentException` pour des valeurs `ArchivePolicy` invalides est une exception PHP
standard et n'hérite pas de `ArchiveGuardException`.

## Comment distinguer un résultat d'une exception

### `inspect()`

- Si l'archive peut être lue et passe la vérification, la méthode renvoie `InspectionResult`
  avec `isAccepted() === true`.
- Si l'archive peut être lue mais que des violations sont trouvées, la méthode renvoie tout de
  même `InspectionResult`, mais `isAccepted()` vaut `false` et les raisons se trouvent dans
  `violations()`.
- Si le fichier lui-même ne peut pas être ouvert ou analysé correctement,
  `ArchiveOpenException` est levée.

### `extract()`

- Si l'archive échoue à la vérification obligatoire avant extraction,
  `ArchiveRejectedException` est levée. Les violations sont disponibles via
  `inspectionResult()`.
- Si le fichier source ne peut pas être ouvert ou reconnu avant le début de l'extraction,
  `ArchiveOpenException` est levée.
- Si le problème survient pendant la préparation de la destination ou pendant l'écriture,
  `ExtractionException` est levée.
- En cas de succès, [`ExtractionResult`](../../src/ExtractionResult.php) est renvoyé.

Des exemples pratiques sont disponibles dans le
[guide de `inspect()`](../guides/inspection_fr.md) et le
[guide de `extract()`](../guides/extraction_fr.md).

[← Retour au README](../readme/README_fr.md)
