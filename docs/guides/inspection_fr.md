# Vérification d'une archive

Ce guide explique comment vérifier une archive avant la décompression, comment lire le résultat
et en quoi une violation normale des limites diffère d'un problème du fichier d'archive lui-même.

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) n'extrait rien sur le disque. La méthode
analyse uniquement l'archive et renvoie un
[`InspectionResult`](../../src/InspectionResult.php).

## Scénario de base

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;

$archivePath = '/path/to/archive.zip';

// Choisissez des limites adaptées aux archives réelles et aux ressources de l'application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->inspect($archivePath, $policy);

    if ($result->isAccepted()) {
        echo 'L\'archive a réussi la vérification.' . PHP_EOL;
    } else {
        foreach ($result->violations() as $violation) {
            // code indique la cause du rejet ; message contient sa description textuelle.
            echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
        }
    }
} catch (ArchiveOpenException $e) {
    echo 'Impossible de lire l\'archive : ' . $e->getMessage() . PHP_EOL;
}
```

## Limites de vérification

[`ArchivePolicy`](../../src/ArchivePolicy.php) définit des **limites maximales autorisées**,
et non des valeurs exactes attendues :

- `maxArchiveBytes` — taille maximale du fichier d'archive lui-même ;
- `maxEntries` — nombre maximal de fichiers et dossiers dans l'archive ; pour TAR, certains
  éléments de métadonnées du format sont comptés dans la même limite ;
- `maxEntryUncompressedBytes` — taille maximale d'un fichier après décompression ;
- `maxTotalUncompressedBytes` — volume total maximal des données décompressées ;
- `maxCompressionRatio` — limite supplémentaire facultative du rapport entre taille
  décompressée et taille compressée.

Par exemple, si `maxArchiveBytes` vaut `50_000_000`, un fichier de 20 Mo n'a pas besoin
d'avoir une taille exacte : il doit simplement rester sous la limite configurée.

Les règles détaillées de chaque paramètre sont regroupées dans la
[référence `ArchivePolicy`](../reference/policy-and-errors_fr.md).

## Résultat de la vérification

[`InspectionResult`](../../src/InspectionResult.php) fournit trois méthodes principales :

- `format()` — format détecté : `zip`, `tar` ou `tar.gz` ;
- `isAccepted()` — indique si l'archive a réussi toutes les vérifications ;
- `violations()` — liste des raisons du rejet.

Si `isAccepted()` renvoie `false`, l'archive peut néanmoins être structurellement valide. Par
exemple, elle peut contenir un fichier trop volumineux ou un lien symbolique que la politique
du paquet n'autorise pas à extraire.

Chaque [`Violation`](../../src/Violation.php) contient :

- `code` — code stable de la raison, adapté à la logique de l'application ;
- `message` — description de diagnostic ;
- `entryName` — nom du fichier, du répertoire ou d'un autre élément de l'archive si la
  violation lui est associée.

La liste complète des codes figure dans la
[référence des violations](../reference/policy-and-errors_fr.md).

## Erreurs d'ouverture et de structure

[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) ne signifie pas que
l'archive a échoué à la politique choisie, mais qu'elle ne peut pas être lue correctement.

Cas principaux :

- le fichier n'existe pas, n'est pas un fichier local ordinaire ou n'est pas lisible ;
- le chemin fourni contient `://` ;
- le contenu ne peut pas être reconnu comme ZIP, TAR ou TAR.GZ ;
- ZIP, TAR ou GZIP est endommagé ou possède une structure invalide.

Dans ces cas, aucun `InspectionResult` n'est renvoyé.

## Ce qui est vérifié

### Chemins

La bibliothèque convertit les antislashs en `/`, supprime les segments vides et `.`, et
rejette :

- `..`, qui permettrait de remonter vers un répertoire parent ;
- les chemins absolus ;
- les chemins avec lettre de lecteur ;
- les chemins UNC ;
- les noms contenant un octet NUL ;
- les doublons et conflits de chemins après normalisation.

### Types de contenu

Les fichiers ordinaires et les répertoires sont autorisés. Les liens symboliques, les liens
physiques TAR, les objets spéciaux et les fonctionnalités de format non prises en charge sont
renvoyés comme violations.

### Limites de ressources

Sont vérifiés :

- la taille du fichier d'archive ;
- le nombre de fichiers, dossiers et éléments de métadonnées de format comptabilisés ;
- la taille d'un fichier après décompression ;
- le volume total de données décompressées ;
- si configuré, le rapport entre taille compressée et taille décompressée.

### Particularités de ZIP, TAR et TAR.GZ

Pour ZIP, le chiffrement et la méthode de compression sont également vérifiés.

Si un ZIP contient un élément chiffré, `inspect()` renvoie la violation `encrypted_entry` et
`isAccepted()` vaut `false`. La version actuelle du paquet ne demande pas de mot de passe et
n'extrait pas le contenu chiffré.

TAR et TAR.GZ ne prennent en charge que l'ensemble implémenté d'en-têtes ordinaires et
d'extensions GNU/PAX. Les détails figurent dans la
[référence des archives prises en charge](../reference/supported-archives_fr.md).

[← Retour au README](../readme/README_fr.md)
