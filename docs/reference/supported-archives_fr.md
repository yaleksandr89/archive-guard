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
[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) est levée.

## ZIP

Le paquet accepte les fichiers et répertoires ZIP ordinaires lorsqu'ils passent les
vérifications communes de chemins et de limites de ressources.

En plus :

- les liens symboliques et les types d'éléments spéciaux sont rejetés ;
- un répertoire contenant des données non nulles est rejeté comme fonctionnalité non prise en
  charge ;
- un élément chiffré produit la violation `encrypted_entry` ;
- le paquet **ne demande pas de mot de passe** et n'extrait pas le contenu chiffré ;
- la méthode de compression doit être prise en charge par le `ZipArchive` courant, sinon
  `unsupported_compression` est renvoyé ;
- si `maxCompressionRatio` est configuré, le rapport est évalué à partir des métadonnées ZIP.

La vérification du rapport de compression reste une heuristique supplémentaire. Le volume
réellement écrit est à nouveau contrôlé pendant l'extraction.

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

## TAR.GZ

Les données produites par la décompression GZIP sont vérifiées selon les mêmes règles que TAR
ordinaire.

Si `maxCompressionRatio` est configuré, la quantité réelle de données produite par le
décompresseur GZIP est comptabilisée. Cela permet de limiter une expansion excessive des
données compressées avant l'écriture de fichiers sur le disque.

Les données supplémentaires après la fin du flux GZIP ainsi que plusieurs flux GZIP concaténés
ne sont pas pris en charge et sont considérés comme une erreur de structure de l'archive.

## Lorsqu'une fonctionnalité de format n'est pas prise en charge

Le paquet préfère rejeter une variante inconnue ou non prise en charge plutôt que d'essayer
de l'extraire partiellement.

Selon la situation, il s'agit soit :

- d'une violation structurée telle que `encrypted_entry`, `symlink_entry` ou
  `unsupported_feature` ;
- d'une `ArchiveOpenException` si la structure de l'archive est endommagée et ne peut pas être
  analysée de manière fiable.

Tous les codes de violation sont listés dans la
[référence des violations](policy-and-errors_fr.md).

[← Retour au README](../readme/README_fr.md)
