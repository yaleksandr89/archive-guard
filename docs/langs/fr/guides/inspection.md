# Vérification d'une archive

Ce guide explique comment vérifier une archive avant la décompression, comment lire le résultat
et en quoi une violation normale des limites diffère d'un problème du fichier d'archive lui-même.

[`ArchiveGuard::inspect()`](../../../../src/ArchiveGuard.php) n'extrait rien sur le disque. La méthode
analyse uniquement l'archive et renvoie un
[`InspectionResult`](../../../../src/InspectionResult.php).

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

## ZIP protégé par mot de passe

Pour une archive ZIP chiffrée, passez le mot de passe comme argument nommé :

```php
$result = new ArchiveGuard()->inspect(
    $archivePath,
    $policy,
    password: $password,
);
```

La distinction entre les valeurs importe :

- `null` signifie qu'aucun mot de passe n'a été fourni ;
- `''` signifie qu'une chaîne vide a été fournie explicitement et n'est pas convertie en `null` ;
- toute valeur fournie, y compris `''`, est transmise à `ZipArchive` comme mot de passe.

Un mot de passe peut aussi être fourni pour un ZIP non chiffré. TAR et TAR.GZ ne disposent pas de chiffrement par mot de passe intégré ; un `password` non nul déclenche `InvalidArgumentException` avec `Password is only valid for ZIP archives.`

Les violations possibles pour une entrée chiffrée sont :

- `encrypted_entry` : entrée chiffrée sans mot de passe ;
- `unsupported_encryption` : mot de passe fourni, mais méthode de chiffrement non prise en charge par l'environnement ;
- `decryption_failed` : impossibilité de déchiffrer ou lire le contenu ; cela ne prouve pas que le mot de passe était erroné.

Le mot de passe est marqué comme paramètre sensible et ne doit pas apparaître dans les exceptions, violations ou diagnostics.

## Limites de vérification

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) définit des **limites maximales autorisées**,
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
[référence `ArchivePolicy`](../reference/policy-and-errors.md).

La taille de l'archive source est contrôlée avant la validité du mot de passe pour le format. `archive_too_large` a donc priorité sur une erreur de mot de passe TAR/TAR.GZ.

## Résultat de la vérification

[`InspectionResult`](../../../../src/InspectionResult.php) fournit trois méthodes principales :

- `format()` — format détecté : `zip`, `tar` ou `tar.gz` ;
- `isAccepted()` — indique si l'archive a réussi toutes les vérifications ;
- `violations()` — liste des raisons du rejet.

Si `isAccepted()` renvoie `false`, l'archive peut néanmoins être structurellement valide. Par
exemple, elle peut contenir un fichier trop volumineux ou un lien symbolique que la politique
du paquet n'autorise pas à extraire.

Chaque [`Violation`](../../../../src/Violation.php) contient :

- `code` — code stable de la raison, adapté à la logique de l'application ;
- `message` — description de diagnostic ;
- `entryName` — nom du fichier, du répertoire ou d'un autre élément de l'archive si la
  violation lui est associée.

La liste complète des codes figure dans la
[référence des violations](../reference/policy-and-errors.md).

## Erreurs d'ouverture et de structure

[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) ne signifie pas que
l'archive a échoué à la politique choisie, mais qu'elle ne peut pas être lue correctement.

Cas principaux :

- le fichier n'existe pas, n'est pas un fichier local ordinaire ou n'est pas lisible ;
- le chemin fourni contient `://` ;
- le contenu ne peut pas être reconnu comme ZIP, TAR ou TAR.GZ ;
- ZIP, TAR ou GZIP est endommagé ou possède une structure invalide.

Dans ces cas, aucun `InspectionResult` n'est renvoyé.

La lecture d'une entrée ZIP non chiffrée acceptée peut échouer malgré la validation des métadonnées : il s'agit alors d'une `ArchiveOpenException`. En revanche, l'échec du déchiffrement ou de la lecture d'un contenu chiffré avec mot de passe donne un `InspectionResult` rejeté avec `decryption_failed`, pas cette exception.

## Ce qui est vérifié

### Chemins

La bibliothèque transforme les barres obliques inverses en `/`, supprime les segments vides et `.`, et refuse :

- les segments `..` permettant de remonter au parent ;
- les chemins absolus, ceux avec lettre de lecteur et les chemins UNC ;
- les noms contenant un octet NUL ;
- les doublons et conflits après normalisation.

Sous Windows, la même inspection applique les règles déterministes : UTF-8 invalide, caractères Win32 interdits et de contrôle, points/espaces finaux, noms de périphériques réservés et collisions de casse ASCII.

Les règles de nommage déterministes sont vérifiées par `inspect()`. Les conditions dépendant du répertoire concret de destination et de son système de fichiers sont contrôlées dans `extract()`.

### Types de contenu

Les fichiers ordinaires et répertoires sont acceptés. Liens symboliques, liens physiques TAR, objets spéciaux et fonctionnalités non prises en charge sont signalés comme violations.

### Limites de ressources

L'inspection contrôle la taille de l'archive, le nombre de fichiers/répertoires et d'éléments de métadonnées comptabilisés, la taille décompressée de chaque élément, le volume total et éventuellement le rapport de compression.

### Contenu ZIP

Après validation des métadonnées, les entrées ZIP ordinaires acceptées sont lues en flux jusqu'à EOF. Le nombre réel d'octets décompressés est comparé aux métadonnées acceptées. `inspect()` vérifie donc aussi la lisibilité effective, pas seulement le répertoire central ZIP ; des données corrompues ne sont pas acceptées sur la seule apparence de métadonnées correctes.

### Relation entre `inspect()` et `extract()`

Les deux méthodes utilisent les mêmes entrées de vérification de l'archive :

```text
archivePath
ArchivePolicy
password
```

À contenu, `ArchivePolicy` et `password` identiques, la sémantique d'acceptation est la même. Si `extract()` atteint le contrôle de l'archive et la refuse, il déclenche `ArchiveRejectedException` avec l'`InspectionResult` correspondant avant la publication Atomic ou l'application Merge.

Les erreurs d'espace de travail, destination ou verrou peuvent survenir plus tôt. `destinationPath` et `ExtractionOptions` déterminent la manière d'appliquer le résultat sans changer son acceptation. Permissions, conflits Merge, état du verrou, alias du système de fichiers et modifications concurrentes ne sont vérifiés que dans `extract()`.

### Particularités de TAR et TAR.GZ

TAR et TAR.GZ prennent en charge le sous-ensemble implémenté d'en-têtes ordinaires et d'extensions GNU/PAX. Voir la [référence des archives prises en charge](../reference/supported-archives.md).
[← Retour au README](../README.md)
