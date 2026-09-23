# Archive Guard

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Farchive--guard-blue.svg?style=flat-square)](https://github.com/yaleksandr89/archive-guard)
[![CI](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](../../LICENSE)

![Archive Guard — inspection et extraction ZIP, TAR et TAR.GZ pour PHP](../assets/archive-guard-readme-cover.png)

## Choisir une langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../README.md) | [English](./README_en.md) | [Español](./README_es.md) | [中文](./README_zh.md) | **Sélectionné** | [Deutsch](./README_de.md) |

Archive Guard est une bibliothèque PHP permettant de vérifier les archives ZIP, TAR et TAR.GZ
avant leur décompression et d'extraire les fichiers avec des limites définies à l'avance.

## À quoi sert le paquet

Si une application reçoit une archive d'un utilisateur ou d'un service externe, il est utile
de vérifier avant la décompression qu'elle ne contient pas de chemins dangereux, de liens ou
d'éléments non pris en charge, et que sa taille ainsi que le volume des données décompressées
restent dans les limites autorisées par l'application.

Le paquet permet soit de vérifier une archive séparément avant de la décompresser, soit de la
vérifier et d'en extraire le contenu en une seule opération. Si l'archive échoue à la
vérification, l'application reçoit une raison précise.

## Ce que fait le paquet

- détecte le format à partir du contenu du fichier et non de son extension ;
- vérifie les chemins internes et empêche toute sortie hors du répertoire de destination ;
- rejette les liens symboliques, les liens physiques, les objets spéciaux et les éléments non
  pris en charge ;
- limite la taille maximale de l'archive, le nombre de fichiers et dossiers qu'elle contient
  et le volume total des données après décompression ;
- renvoie une liste structurée de violations que l'application peut traiter ;
- permet de vérifier une archive avec `inspect()` ou de la vérifier et l'extraire avec
  `extract()` ;
- sous Windows, vérifie également les noms que le système de fichiers ne peut pas créer en
  toute sécurité.

Les différences détaillées entre ZIP, TAR et TAR.GZ sont décrites dans la
[référence des archives prises en charge](../reference/supported-archives_fr.md).

## Prérequis

- PHP `^8.4` ;
- `ext-zip` ;
- `ext-zlib`.

## Démarrage rapide

### Vérifier une archive

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) vérifie l'archive sans extraire de
fichiers. Les limites sont configurées avec
[`ArchivePolicy`](../../src/ArchivePolicy.php).

<details>
<summary>Afficher l'exemple de vérification</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';

// Choisissez des limites adaptées aux archives réelles et aux ressources de l'application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$inspection = new ArchiveGuard()->inspect($archivePath, $policy);

if ($inspection->isAccepted()) {
    echo 'L\'archive a réussi la vérification.' . PHP_EOL;
} else {
    foreach ($inspection->violations() as $violation) {
        // code indique la cause du rejet ; message contient sa description textuelle.
        echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
    }
}
```

</details>

Les valeurs ci-dessus servent uniquement d'exemple. Choisissez-les en fonction de la taille
des archives réellement attendues par votre application. Le scénario de vérification est
décrit en détail dans le [guide de `inspect()`](../guides/inspection_fr.md).

### Extraire des fichiers

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) effectue la vérification obligatoire
juste avant d'écrire les fichiers. Le répertoire de destination est créé à l'avance par
l'application.

<details>
<summary>Afficher l'exemple d'extraction</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// Choisissez des limites adaptées aux archives réelles et aux ressources de l'application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

echo 'Fichiers : ' . $result->filesExtracted() . PHP_EOL;
echo 'Répertoires : ' . $result->directoriesCreated() . PHP_EOL;
echo 'Octets écrits : ' . $result->bytesWritten() . PHP_EOL;
```

</details>

Le résultat d'un appel antérieur à `inspect()` n'est pas utilisé comme autorisation d'écrire :
`extract()` vérifie l'état actuel du fichier juste avant l'extraction. Les exigences concernant
la destination et le comportement en cas d'erreur sont décrits dans le
[guide d'extraction](../guides/extraction_fr.md).

## Politique de vérification

[`ArchivePolicy`](../../src/ArchivePolicy.php) définit les limites supérieures au-delà
desquelles une archive est rejetée :

- taille maximale du fichier d'archive ;
- nombre maximal de fichiers et dossiers dans l'archive ; pour TAR, certains éléments de
  métadonnées du format sont comptés dans la même limite ;
- taille maximale d'un fichier après décompression ;
- taille totale maximale de toutes les données décompressées ;
- limite facultative du rapport entre taille décompressée et taille compressée.

Il n'existe pas de valeurs universelles : une archive acceptable pour l'envoi d'un avatar et
une archive de sauvegarde acceptable nécessiteront des limites différentes. Tous les paramètres
et leurs règles sont décrits dans la
[référence de la politique](../reference/policy-and-errors_fr.md).

## Violations et erreurs

Si une archive est reconnue mais ne passe pas les vérifications configurées,
[`inspect()`](../../src/ArchiveGuard.php) renvoie un
[`InspectionResult`](../../src/InspectionResult.php) contenant les violations.

Les exceptions correspondent à un autre type d'échec :

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — le fichier ne peut
  pas être ouvert, le format n'est pas reconnu ou la structure de l'archive est endommagée ;
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — l'archive
  n'a pas réussi la vérification obligatoire avant l'extraction, l'écriture n'a donc pas
  commencé ;
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — le problème concerne
  le répertoire de destination ou est survenu pendant l'extraction.

<details>
<summary>Afficher l'exemple de gestion des erreurs</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$guard = new ArchiveGuard();

try {
    $result = $guard->extract($archivePath, $destinationPath, $policy);
} catch (ArchiveRejectedException $e) {
    // L'archive a été lue, mais elle n'a pas réussi les vérifications configurées.
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // Le fichier ne peut pas être ouvert ou la structure de l'archive est invalide.
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // L'archive a réussi la vérification, mais l'extraction n'a pas pu être terminée.
    echo $e->getMessage() . PHP_EOL;
}
```

</details>

La liste complète des codes de violation et des exceptions est disponible dans la
[référence des erreurs](../reference/policy-and-errors_fr.md).

## Sécurité et limitations

Plusieurs conditions sont importantes lors de l'extraction :

- **Chaque extraction nécessite un répertoire vide séparé.** Si l'application utilise un
  dossier commun pour toutes les archives, créez un nouveau sous-répertoire pour chaque
  opération. La version actuelle ne décompresse pas une archive par-dessus des fichiers
  existants.
- **Un fichier ou répertoire existant n'est pas remplacé.** Si un objet apparaît au chemin
  attendu pendant l'extraction, l'opération échoue au lieu de l'écraser.
- **Les permissions, le propriétaire et la date de modification stockés dans l'archive ne sont
  pas restaurés.** Le contenu des fichiers et la structure des répertoires sont extraits, mais
  les métadonnées enregistrées dans l'archive ne sont pas appliquées.
- **Windows impose des restrictions supplémentaires sur les noms.** Par exemple, Windows
  n'autorise pas `CON`, `NUL`, les noms terminant par un point ni certains noms contenant des
  caractères spéciaux. Une archive peut donc passer la vérification générale puis être rejetée
  juste avant l'extraction sous Windows.
- **L'extraction n'est pas atomique.** Si l'espace disque est épuisé pendant l'écriture ou si
  une autre erreur d'entrée/sortie survient, certains fichiers peuvent déjà se trouver dans le
  répertoire de destination. Il n'existe actuellement aucun rollback automatique.
- **Le répertoire de destination n'est pas verrouillé contre les autres processus.** Les
  vérifications ne couvrent pas le cas où un autre processus modifie simultanément son contenu.
  Utilisez un répertoire séparé auquel seule votre application peut accéder pendant
  l'extraction.

Les raisons de ces limites et les responsabilités laissées à l'application sont décrites dans
le [modèle de sécurité](../security-model_fr.md).

## Retours

- bugs reproductibles — [GitHub Issues](https://github.com/yaleksandr89/archive-guard/issues) ;
- questions d'utilisation et idées — [GitHub Discussions](https://github.com/yaleksandr89/archive-guard/discussions).

---

<p align="center">
  Si le paquet vous a été utile, ajoutez une étoile sur GitHub pour aider d'autres développeurs à le trouver. 🤘
</p>
