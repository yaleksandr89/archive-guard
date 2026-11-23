# Archives prises en charge

Cette référence décrit les variantes ZIP, TAR et TAR.GZ comprises par le paquet et les
fonctionnalités de format qu'il rejette volontairement.

## Détection du format

Le format est détecté à partir du contenu du fichier et non de son extension :

- ZIP — par la signature ZIP ;
- TAR.GZ — par la signature GZIP ;
- TAR — par un en-tête TAR valide ou un bloc TAR vide.

La source doit être un fichier local ordinaire et lisible. Un chemin contenant `://` est
rejeté.

Si le contenu ne peut pas être reconnu ou si la structure de l'archive est endommagée,
[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) est levée.

Un contenu ZIP non chiffré accepté mais illisible provoque `ArchiveOpenException` ; un échec de déchiffrement ou lecture du contenu chiffré avec mot de passe produit plutôt `decryption_failed`.

## ZIP

Les fichiers ordinaires et répertoires ZIP sont acceptés s'ils passent les contrôles des chemins, des limites, de la méthode de compression et de la lisibilité réelle du contenu.

En outre :

- les liens symboliques et types spéciaux sont rejetés ;
- un répertoire contenant des données non nulles est rejeté ;
- la méthode de compression doit être prise en charge par `ZipArchive`, sinon `unsupported_compression` est renvoyé ;
- `maxCompressionRatio`, lorsqu'il est configuré, utilise les métadonnées ZIP ;
- le contenu des fichiers ordinaires acceptés est lu en flux jusqu'à EOF et la taille réellement décompressée est comparée aux métadonnées acceptées.

### ZIP chiffrés

Le même argument optionnel `password` sert dans `inspect()` et `extract()` :

- une entrée chiffrée sans mot de passe produit `encrypted_entry` ;
- une méthode de chiffrement non prise en charge produit `unsupported_encryption` ;
- un échec du déchiffrement ou de la lecture du contenu chiffré produit `decryption_failed` ;
- un mot de passe correct permet les ZIP chiffrés ou mixtes, sous réserve des autres contrôles.

Un mot de passe fourni n'empêche pas la vérification d'un ZIP non chiffré. La chaîne vide `''` est considérée comme fournie, contrairement à `null`. La prise en charge effective dépend des capacités de `ZipArchive`/`libzip` dans l'environnement actuel.

## TAR

Les fichiers et répertoires ordinaires, les en-têtes USTAR et un ensemble limité d'extensions
GNU/PAX sont pris en charge lorsqu'ils sont nécessaires pour déterminer correctement le nom,
la taille et les autres métadonnées autorisées.

Points importants :

- les liens symboliques et physiques sont rejetés ;
- les éléments spéciaux et clairsemés sont rejetés ;
- un répertoire contenant des données non nulles est rejeté ;
- les champs PAX inconnus et les extensions non prises en charge sont rejetés ;
- les extensions locales répétées ou en conflit sont rejetées ;
- les éléments de métadonnées des extensions comptent aussi dans les limites de nombre
  d'éléments et de volume de données.

Un en-tête endommagé ou une structure TAR invalide provoque `ArchiveOpenException` et non une
violation normale de la politique.

`maxCompressionRatio` ne s'applique pas à TAR non compressé.

TAR n’intègre pas de chiffrement par mot de passe ; `password` doit être `null` et toute autre valeur provoque `InvalidArgumentException`.

## TAR.GZ

Les données produites par la décompression GZIP sont vérifiées selon les mêmes règles que TAR
ordinaire.

Si `maxCompressionRatio` est configuré, la quantité réelle de données produite par le
décompresseur GZIP est comptabilisée. Cela permet de limiter une expansion excessive des
données compressées avant l'écriture de fichiers sur le disque.

Les données supplémentaires après la fin du flux GZIP ainsi que plusieurs flux GZIP concaténés
ne sont pas pris en charge et sont considérés comme une erreur de structure de l'archive.

Comme TAR, TAR.GZ ne propose pas de chiffrement par mot de passe intégré. Un `password` non nul provoque `InvalidArgumentException`.

## Compatibilité des chemins avec la plateforme

Les règles générales de chemins sûrs s'appliquent à tous les formats. `inspect()` contrôle aussi les restrictions déterministes de nommage de la plateforme.

Sous Windows sont vérifiés :

- la validité UTF-8 des composants ;
- les caractères Win32 interdits et caractères de contrôle ;
- les points et espaces finaux ;
- les noms de périphériques réservés ;
- les collisions de chemins insensibles à la casse ASCII.

Un nom incompatible produit `platform_incompatible_path` ; une collision peut produire `path_collision`. Ces vérifications ont lieu dans `inspect()` ; celles liées au répertoire et au système de fichiers réels ont lieu dans `extract()`.

## Erreurs de format et de fonctionnalités

Lorsqu'un format pris en charge utilise une fonctionnalité non gérée, l'inspection renvoie une violation, par exemple `unsupported_encryption`, `unsupported_compression` ou `unsupported_feature`.

Une structure endommagée ou un contenu ZIP non chiffré accepté mais illisible déclenche `ArchiveOpenException`. Consultez la [référence des violations](policy-and-errors.md) pour tous les codes.

[← Retour au README](../README.md)
