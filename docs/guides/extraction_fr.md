# Extraction contrôlée

Ce guide décrit l'extraction avec les vérifications intégrées : ce qu'il faut préparer à
l'avance, les erreurs possibles et ce qui peut rester sur le disque si l'écriture est interrompue.

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) vérifie d'abord l'archive selon la
politique fournie et ne commence à créer les fichiers qu'après une vérification réussie.

## Avant l'extraction

Préparez :

- un fichier local ZIP, TAR ou TAR.GZ lisible ;
- une [`ArchivePolicy`](../../src/ArchivePolicy.php) avec des limites adaptées à l'application ;
- un répertoire de destination vide et séparé.

Le répertoire de destination doit **déjà exister et être vide**. La version actuelle ne
décompresse pas une archive par-dessus des fichiers existants.

Si l'application utilise un dossier commun pour toutes les extractions, créez un nouveau
sous-répertoire pour chaque opération. Le contenu des différentes archives ne se mélange ainsi
pas et la bibliothèque peut vérifier l'ensemble de la destination avant le début de l'écriture.

Le processus PHP doit avoir l'autorisation de créer des fichiers et des sous-répertoires dans
ce répertoire.

## Scénario de base

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// Choisissez des limites adaptées aux archives réelles et aux ressources de l'application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

    echo 'Fichiers : ' . $result->filesExtracted() . PHP_EOL;
    echo 'Répertoires : ' . $result->directoriesCreated() . PHP_EOL;
    echo 'Octets écrits : ' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    // L'archive a été lue, mais elle n'a pas réussi les vérifications configurées.
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // Le fichier source ne peut pas être ouvert ou analysé correctement.
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // Erreur de destination ou échec pendant l'extraction.
    echo $e->getMessage() . PHP_EOL;
}
```

## Ce qui se passe avant l'écriture

`extract()` vérifie toujours l'état actuel de l'archive immédiatement avant l'écriture. Cette
vérification a lieu même si `inspect()` a été appelé auparavant.

La séquence est la suivante :

1. La bibliothèque ouvre et vérifie l'archive.
2. Si des violations sont trouvées,
   [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) est levée
   avant le début de l'écriture.
3. Le répertoire de destination est vérifié.
4. Chaque fichier n'est créé que si son chemin est encore libre ; un objet existant n'est pas
   remplacé.
5. Pendant l'écriture, la taille réelle de chaque fichier et le volume total décompressé sont
   comparés aux limites configurées et aux tailles obtenues lors de la vérification. Si
   l'archive commence à produire plus de données que prévu, l'extraction s'arrête.
6. TAR et TAR.GZ sont lus deux fois : d'abord pour la vérification, puis pour l'extraction.
   Lors du second passage, l'ordre, le chemin, le type et la taille de chaque élément sont
   comparés à la première vérification. Si l'archive change entre les deux passages,
   l'extraction s'arrête.

## Résultat

[`ExtractionResult`](../../src/ExtractionResult.php) fournit :

- `format()` — format de l'archive ;
- `filesExtracted()` — nombre de fichiers créés ;
- `directoriesCreated()` — nombre de répertoires créés ;
- `bytesWritten()` — nombre d'octets écrits dans les fichiers.

## Erreurs

Trois grands groupes d'erreurs peuvent survenir pendant `extract()` :

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — le fichier source
  ne peut pas être ouvert ou analysé correctement ;
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — l'archive
  est structurellement valide mais n'a pas respecté les limites ou vérifications ;
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — problème lié au
  répertoire de destination, au nom cible ou à l'écriture elle-même.

Les codes de violation disponibles via `ArchiveRejectedException` sont listés dans la
[référence des erreurs](../reference/policy-and-errors_fr.md).

## Échec partiel et atomicité

Les données sont actuellement écrites directement dans le répertoire de destination préparé.
Cela permet de contrôler le volume réellement écrit et d'éviter d'écraser des fichiers
existants, mais signifie aussi que l'opération **n'est pas atomique**.

Si l'espace disque vient à manquer après la création de plusieurs fichiers, si le processus
perd ses droits d'écriture ou si une autre erreur d'entrée/sortie survient, les fichiers et
répertoires déjà créés restent en place. Le fichier en cours peut aussi rester partiellement
écrit.

Une approche entièrement atomique est techniquement possible, mais il s'agirait d'un autre
contrat : tout le contenu devrait d'abord être décompressé dans un répertoire temporaire, puis
le résultat complet devrait être déplacé dans une étape finale distincte. Ce déplacement se
comporte différemment selon les systèmes de fichiers et sous Windows ; la version actuelle ne
prétend donc pas fournir une atomicité qu'elle ne peut pas garantir.

Si l'application crée un répertoire dédié à une seule opération et le contrôle entièrement,
elle peut le supprimer après une `ExtractionException` selon ses propres règles.

## Windows

Windows impose des restrictions supplémentaires sur les noms de fichiers qui n'existent pas
dans les formats ZIP ou TAR généraux. Avant la première écriture, la bibliothèque rejette
également, par exemple :

- les noms de périphériques `CON`, `NUL`, `PRN` et similaires ;
- les caractères interdits par Windows ;
- les noms se terminant par un point ou un espace ;
- les chemins qui ne diffèrent que par la casse ASCII et que Windows considère comme le même
  chemin.

Une même archive peut donc passer `inspect()` au niveau du format mais être rejetée par
`extract()` lors de la préparation de l'écriture sous Windows.

## Métadonnées des fichiers

Une archive peut stocker, en plus du contenu des fichiers, leurs permissions, leur propriétaire
et leur date de modification. La version actuelle extrait le contenu des fichiers et la
structure des répertoires, mais n'applique pas ces métadonnées depuis l'archive.

Les autres limites et recommandations sont décrites dans le
[modèle de sécurité](../security-model_fr.md).

[← Retour au README](../readme/README_fr.md)
