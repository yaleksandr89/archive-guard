# Modèle de sécurité

Cette page explique les contrôles appliqués aux archives non fiables, les garanties des modes Atomic et Merge, et les risques que l'application appelante doit encore maîtriser.

Elle décrit le comportement de la bibliothèque, **pas** les résultats d'un audit formel de sécurité.

## Données d'archive considérées comme potentiellement dangereuses

Les données de l'archive ne sont pas fiables. Sont contrôlés :

- noms et chemins, collisions après normalisation et règles déterministes de la plateforme ;
- types d'éléments : fichier ordinaire, répertoire, lien ou objet spécial ;
- tailles déclarées et tailles réellement lues ;
- volume total décompressé et données de compression ;
- fonctionnalités ZIP, TAR et TAR.GZ ;
- chiffrement ZIP et lisibilité réelle des contenus acceptés.

La destination constitue une autre limite de confiance : au moment d'appliquer le résultat, elle peut contenir des fichiers, répertoires, liens ou objets absents d'une inspection antérieure.

Une archive structurellement valide peut encore contenir `../file`, un lien symbolique, trop de données décompressées ou une fonctionnalité non prise en charge. Une structure lisible n'est pas une garantie de sécurité.

## Sémantique commune d'acceptation des archives

`inspect()` et `extract()` utilisent les mêmes entrées de vérification :

```text
archivePath
ArchivePolicy
password
```

Les deux méthodes appliquent la même sémantique d'acceptation à ces entrées :

- `inspect()` renvoie un `InspectionResult` avec les raisons d'un rejet ;
- `extract()` déclenche `ArchiveRejectedException` avec l'`InspectionResult` correspondant avant publication Atomic ou application Merge.

Les erreurs d'espace de travail, destination ou verrou coopératif peuvent survenir avant que le résultat complet de la vérification soit observable. `destinationPath` et `ExtractionOptions` déterminent uniquement comment appliquer le résultat au système de fichiers, pas son acceptation.

## Ce que fait `inspect()`

[`ArchiveGuard::inspect()`](../../../src/ArchiveGuard.php) n'écrit aucun fichier extrait sur le disque. Il vérifie :

- normalisation des chemins et collisions ;
- règles déterministes de nommage de la plateforme ;
- types des éléments ;
- limites de [`ArchivePolicy`](../../../src/ArchivePolicy.php) ;
- fonctionnalités prises en charge de ZIP, TAR et TAR.GZ ;
- pour ZIP : compression, chiffrement et lecture en flux des fichiers ordinaires acceptés.

Sous Windows, les composants invalides produisent `platform_incompatible_path` et les collisions de casse ASCII `path_collision`. Les règles déterministes sont vérifiées dans `inspect()` ; les conditions liées au vrai répertoire et au système de fichiers le sont dans `extract()`.

## Mots de passe ZIP

Le paramètre optionnel `password` est sensible :

- `null` signifie l'absence de mot de passe, `''` une chaîne vide explicitement fournie ;
- un mot de passe peut aussi être transmis pour un ZIP non chiffré ;
- une entrée chiffrée sans mot de passe produit `encrypted_entry` ;
- une méthode de chiffrement non prise en charge produit `unsupported_encryption` ;
- l'impossibilité de déchiffrer ou lire le contenu chiffré produit `decryption_failed`.

`decryption_failed` est une erreur de déchiffrement ou lecture ; `ZipArchive`/`libzip` ne permet pas toujours de savoir si le mot de passe est incorrect. Le paramètre est marqué `#[SensitiveParameter]` et n'est pas stocké dans `ExtractionOptions`, `ExtractionResult`, l'espace de travail, les métadonnées d'inspection ou les diagnostics.

TAR et TAR.GZ ne possèdent pas de chiffrement par mot de passe intégré. Leur `password` doit être `null` ; toute autre valeur déclenche `InvalidArgumentException`.

## Ce que fait `extract()`

[`ArchiveGuard::extract()`](../../../src/ArchiveGuard.php) exige des [`ExtractionOptions`](../../../src/ExtractionOptions.php) explicites. Avant application :

1. Vérifier la source et détecter le format.
2. Contrôler `maxArchiveBytes`.
3. Vérifier la validité du mot de passe pour le format.
4. Initialiser `ExtractionWorkspace` : vérifier la destination, acquérir un verrou coopératif et créer un espace temporaire dans le parent de la destination.
5. `ZipExtractor` ou `TarExtractor` parcourt, extrait et vérifie intégralement l'archive dans l'espace temporaire.
6. Publier Atomic ou vérifier les conflits et appliquer Merge.
7. Nettoyer l'espace de travail et libérer le verrou.

Des erreurs de destination, verrou ou espace temporaire peuvent se produire avant que la vérification complète de l'archive soit observable, sans changer sa sémantique d'acceptation.

Pour TAR/TAR.GZ, l'ordre, le chemin, le type et la taille de chaque élément sont comparés à la passe d'inspection. Une source modifiée entre les passes interrompt l'extraction.

## Atomic

`ExtractionOptions::atomic()` cible un nouveau répertoire final qui **ne doit pas encore exister**. La bibliothèque extrait tout dans un espace temporaire du parent, vérifie le résultat, puis le publie par `rename()` au chemin final auparavant absent. Une erreur avant publication supprime l'espace temporaire.

## Merge

`ExtractionOptions::merge(...)` cible un **répertoire réel existant**. L'archive complète est d'abord préparée dans l'espace temporaire et les conflits vérifiés avant toute modification de la destination. Les fichiers ordinaires existants suivent la stratégie choisie :

- `Reject` : refuser un conflit avant application ;
- `Skip` : conserver le fichier ordinaire existant ;
- `Overwrite` : le remplacer par le fichier ordinaire préparé.

Les répertoires correspondants sont réutilisés ; les conflits fichier/répertoire, liens et objets spéciaux des chemins concernés sont refusés.

Les changements Merge sont appliqués séquentiellement. Une erreur du système de fichiers déclenche `ExtractionException` et arrête les changements suivants ; **ceux déjà appliqués restent présents**. Merge n'est donc pas transactionnel. L'espace temporaire est supprimé ensuite.

## Verrou coopératif

Archive Guard utilise automatiquement `.archive-guard-locks` pour coordonner ses propres opérations concurrentes visant la même destination. Ce **verrou coopératif** n'empêche pas des processus externes arbitraires de modifier les fichiers.

## Limites de ressources

Quatre plafonds obligatoires limitent la taille de l'archive source, le nombre d'éléments (y compris les métadonnées comptabilisées), la taille décompressée d'un élément et le volume total. `maxCompressionRatio` ajoute une limite optionnelle :

- ZIP utilise les tailles des métadonnées ;
- TAR.GZ utilise le volume réel produit par le décompresseur ;
- TAR non compressé n'applique pas ce rapport.

Les éléments ZIP acceptés sont également lus en flux pour vérifier leur taille et lisibilité réelles, et pas seulement les métadonnées.

## Recommandations pratiques

- Choisissez les limites `ArchivePolicy` selon les données et ressources réelles de l'application.
- Utilisez Atomic pour publier un répertoire nouveau et Merge pour un répertoire existant avec une stratégie de conflit explicite.
- Passez directement le mot de passe ZIP dans l'appel et ne le journalisez jamais.
- Traitez séparément `InspectionResult`, `ArchiveOpenException`, `ArchiveRejectedException` et `ExtractionException`.
- Pour Merge, vérifiez l'accès en écriture au parent de chaque objet modifié et au parent de la destination pour l'espace temporaire et les verrous. `Skip` ne requiert pas de permission d'écriture pour le fichier préservé ; sous Windows, le fichier remplacé doit aussi être accessible en écriture. Sous POSIX, un parent accessible en écriture permet de remplacer un fichier en lecture seule.
- Empêchez les processus non coopératifs de modifier simultanément la destination. La bibliothèque extrait les contenus et répertoires, mais ne restaure **pas** les permissions, le propriétaire ou les dates de l'archive. Windows ajoute des restrictions de noms lors de l'inspection.

Voir les [formats pris en charge](reference/supported-archives.md) et le [guide d'extraction](guides/extraction.md).

[← Retour au README](README.md)
