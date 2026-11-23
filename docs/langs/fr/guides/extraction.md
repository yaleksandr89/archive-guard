# Extraction contrôlée

Ce guide présente deux modes explicites : **Atomic**, qui publie un nouveau répertoire complet, et **Merge**, qui applique une archive à un répertoire existant.

[`ArchiveGuard::extract()`](../../../../src/ArchiveGuard.php) ne considère pas le résultat d'un appel antérieur à `inspect()` comme une autorisation de modifier le disque. Il vérifie de nouveau l'archive pendant l'opération ; le mode est choisi avec [`ExtractionOptions`](../../../../src/ExtractionOptions.php).

## Avant l'extraction

Préparez :

- un fichier ZIP, TAR ou TAR.GZ local et lisible ;
- une [`ArchivePolicy`](../../../../src/ArchivePolicy.php) adaptée aux limites de l'application ;
- des [`ExtractionOptions`](../../../../src/ExtractionOptions.php) sélectionnant explicitement Atomic ou Merge ;
- un chemin de destination répondant aux exigences du mode choisi.

Avec `ExtractionOptions::atomic()`, le répertoire final **ne doit pas exister**. La bibliothèque crée automatiquement un répertoire temporaire dans le répertoire parent de la destination ; il ne faut pas le créer à la main. Ce parent doit permettre de créer le répertoire temporaire et le répertoire des verrous coopératifs.

Avec `ExtractionOptions::merge(...)`, la destination doit au contraire être un **véritable répertoire existant et lisible**, non un lien symbolique. L'archive est intégralement préparée dans un répertoire temporaire créé automatiquement dans le parent, puis appliquée à la destination.

## Scénario de base : Atomic

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/result';

// Choisissez des limites adaptées aux archives et aux ressources réelles de votre application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract(
        $archivePath,
        $destinationPath,
        $policy,
        ExtractionOptions::atomic(),
    );

    echo 'Fichiers appliqués : ' . $result->filesExtracted() . PHP_EOL;
    echo 'Répertoires créés : ' . $result->directoriesCreated() . PHP_EOL;
    echo 'Octets écrits : ' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException | ExtractionException $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

Atomic utilise un répertoire temporaire créé automatiquement dans le parent de la destination. Après l'extraction et toutes les vérifications, `rename()` le publie sous le chemin final qui n'existait pas auparavant.

## Merge et stratégies de conflit

Pour appliquer une archive à un répertoire existant, utilisez `ExtractionOptions::merge()` :

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract(
    '/path/to/archive.zip',
    '/path/to/existing-directory',
    $policy,
    ExtractionOptions::merge(ExtractionConflictStrategy::Reject),
);
```

Trois stratégies gèrent le conflit entre un fichier de l'archive et un **fichier ordinaire existant** :

- `Reject` : refus lors de la vérification préalable, avant toute application Merge ;
- `Skip` : conservation du fichier existant et omission du fichier préparé ;
- `Overwrite` : remplacement du fichier ordinaire existant par un fichier ordinaire préparé.

Les répertoires suivent une autre règle : si l'archive et la destination possèdent chacune un répertoire au même chemin, celui de la destination est réutilisé. Un conflit fichier/répertoire dans un sens ou l'autre est toujours une erreur. Les liens symboliques et objets spéciaux sur les chemins de destination concernés sont également refusés.

`Overwrite` autorise uniquement le remplacement d'un fichier ordinaire par un autre fichier ordinaire, jamais celui d'un répertoire, lien ou autre objet.

## Avant l'application des changements

`extract()` revérifie les valeurs courantes de `archivePath`, `ArchivePolicy` et `password`. Un `inspect()` antérieur n'autorise jamais l'écriture.

Séquence générale :

1. Vérifier la source et détecter le format d'après son contenu.
2. Contrôler `maxArchiveBytes`.
3. Vérifier si le format accepte l'argument `password`.
4. Acquérir le verrou coopératif Archive Guard pour la destination.
5. Créer un répertoire temporaire dans le parent de la destination.
6. Extraire et vérifier intégralement l'archive dans ce répertoire.
7. En mode Atomic, publier le répertoire complet sous un chemin final inexistant jusque-là.
8. En mode Merge, vérifier préalablement tous les conflits, puis appliquer les fichiers selon la stratégie choisie.
9. Nettoyer le répertoire temporaire et libérer le verrou.

Le contenu ZIP est traité par lecture en flux : le volume réellement décompressé est vérifié pendant la lecture sans charger une entrée entière en mémoire.

TAR et TAR.GZ passent par une extraction distincte. L'ordre, le chemin, le type et la taille de chaque élément sont comparés au résultat de l'inspection ; toute modification de la source entre ces passes interrompt l'extraction.

## Mot de passe ZIP

Passez le mot de passe comme argument nommé distinct :

```php
$result = new ArchiveGuard()->extract(
    $archivePath,
    $destinationPath,
    $policy,
    ExtractionOptions::atomic(),
    password: $password,
);
```

`null` signifie qu'aucun mot de passe n'a été fourni. Une chaîne vide `''` est bien un mot de passe fourni et n'est pas convertie en `null`.

Il est permis de transmettre un mot de passe pour un ZIP non chiffré. Une entrée chiffrée sans mot de passe produit `encrypted_entry` ; une méthode de chiffrement non prise en charge par l'environnement produit `unsupported_encryption` ; un échec du déchiffrement ou de la lecture du contenu chiffré produit `decryption_failed`.

`decryption_failed` signale un échec de déchiffrement ou de lecture. `ZipArchive`/`libzip` ne peut pas toujours distinguer un mauvais mot de passe d'une autre erreur de lecture des données chiffrées.

TAR et TAR.GZ ne possèdent pas de chiffrement par mot de passe intégré. Pour ces formats, `password` doit valoir `null` ; toute autre valeur provoque `InvalidArgumentException`.

Le mot de passe ZIP est marqué comme sensible et n'est pas stocké dans `ExtractionOptions`, `ExtractionResult`, l'espace de travail ou les messages de diagnostic.

## Résultat

[`ExtractionResult`](../../../../src/ExtractionResult.php) décrit les modifications réellement appliquées :

- `format()` : format de l'archive ;
- `filesExtracted()` : fichiers nouveaux et remplacés réellement appliqués à la destination ;
- `directoriesCreated()` : répertoires réellement créés ;
- `bytesWritten()` : octets de contenu des fichiers nouveaux et remplacés appliqués ;
- `filesSkipped()` : fichiers effectivement ignorés avec `Skip` ;
- `filesOverwritten()` : fichiers existants effectivement remplacés avec `Overwrite`.

En mode Atomic, `filesSkipped()` et `filesOverwritten()` valent toujours zéro.

## Erreurs

Trois catégories principales d'erreurs existent pendant `extract()` :

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) : impossibilité d'ouvrir ou d'analyser fiablement la source, ou de lire un contenu ZIP non chiffré pourtant accepté ;
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php) : rejet par les contrôles de l'archive ; `inspectionResult()` en donne les raisons. Ni la publication Atomic ni l'application Merge n'ont commencé ;
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php) : problème de destination, verrou, répertoire temporaire, vérification préalable, application Merge, publication Atomic ou E/S du système de fichiers.

Un `password` non nul pour TAR/TAR.GZ provoque séparément `InvalidArgumentException`. Les codes sont décrits dans la [référence des erreurs](../reference/policy-and-errors.md).

## Application des modes Atomic et Merge

### Atomic

Atomic extrait et vérifie complètement le résultat dans un répertoire temporaire créé automatiquement dans le parent de la destination. Après toutes les vérifications, `rename()` le publie sous un nouveau chemin final. Une erreur avant la publication met fin à l'opération et supprime le répertoire temporaire.

### Merge

Merge cible un répertoire existant. L'archive complète est préparée dans l'espace temporaire et tous les conflits sont vérifiés avant toute modification de la destination. La stratégie `Reject`, `Skip` ou `Overwrite` est ensuite appliquée de manière séquentielle.

Une erreur du système de fichiers pendant l'application déclenche `ExtractionException` et arrête les modifications suivantes. **Les changements déjà appliqués restent présents**. Le répertoire temporaire est supprimé à la fin. Merge **n'est pas transactionnel**.

### Verrou coopératif

Les deux modes utilisent `.archive-guard-locks` pour coordonner les opérations Archive Guard concurrentes visant la même destination. La bibliothèque crée et utilise automatiquement ce verrou. Il ne bloque pas les écritures de processus externes arbitraires.

## Permissions d'accès pour Merge

Les permissions Merge ne se résument pas à « tout le répertoire de destination doit être accessible en écriture » :

- Avec `Skip`, un fichier en conflit laissé inchangé n'exige pas de permission d'écriture uniquement pour lui.
- Pour créer un fichier ou répertoire ou utiliser `Overwrite`, le **parent de l'objet concerné** doit être accessible en écriture.
- Sous Windows, le fichier remplacé doit aussi être accessible en écriture.
- Sous POSIX, un fichier en lecture seule peut être remplacé si son répertoire parent est accessible en écriture.
- La racine Merge peut être en lecture seule si aucune modification n'exige d'y écrire directement.

Le parent du répertoire de destination doit également permettre la création de l'espace temporaire et des verrous coopératifs.

Les métadonnées de l'archive peuvent contenir des permissions, un propriétaire et des dates. Archive Guard extrait contenus et répertoires sans appliquer ces valeurs enregistrées.

## Windows

Les règles déterministes de compatibilité des noms sont déjà vérifiées pendant l'inspection. Sous Windows sont notamment rejetés :

- l'UTF-8 invalide dans un composant de chemin ;
- les caractères Win32 interdits et les caractères de contrôle ;
- les noms se terminant par un point ou un espace ;
- les noms de périphériques réservés `CON`, `NUL`, `PRN`, `AUX`, `COM1...`, `LPT1...` ;
- les collisions de chemins insensibles à la casse ASCII.

Les vérifications dépendant du répertoire de destination concret et de son système de fichiers sont réalisées par `extract()`.

Pour les détails, consultez le [modèle de sécurité](../security-model.md).

[← Retour au README](../README.md)
