# Modèle de sécurité

Cette page explique contre quels problèmes courants liés aux archives le paquet protège,
quelles vérifications il effectue et quels risques doivent encore être contrôlés par
l'application.

Il s'agit d'une description du comportement de la bibliothèque, et non du résultat d'un audit
de sécurité formel.

## Données d'archive considérées comme potentiellement dangereuses

Les données provenant de l'archive elle-même sont considérées comme potentiellement
dangereuses. Sont vérifiés :

- les noms et chemins de fichiers ;
- le type de contenu : fichier ordinaire, répertoire, lien ou objet spécial ;
- les tailles de fichiers déclarées ;
- le volume total des données décompressées ;
- les informations de compression ;
- les fonctionnalités supplémentaires des formats ZIP, TAR et TAR.GZ.

La raison est simple : une archive peut être structurellement valide tout en contenant un
chemin tel que `../file`, un lien symbolique, un fichier énorme après décompression ou une
fonctionnalité de format que l'application ne s'attend pas à traiter.

## Ce que fait `inspect()`

[`ArchiveGuard::inspect()`](../src/ArchiveGuard.php) vérifie l'archive sans extraire de
fichiers.

La vérification comprend :

- la normalisation et la validation des chemins ;
- la détection des chemins en conflit ;
- la vérification du type de chaque élément : fichier, répertoire, lien ou objet spécial ;
- les limites de [`ArchivePolicy`](../src/ArchivePolicy.php) ;
- les vérifications propres à ZIP, TAR et TAR.GZ.

Si une archive est structurellement valide mais enfreint une règle, l'application reçoit un
[`InspectionResult`](../src/InspectionResult.php) contenant les raisons. Si le fichier
lui-même ne peut pas être lu ou analysé de manière fiable, `ArchiveOpenException` est levée.

## Ce que fait `extract()` avant l'écriture

[`ArchiveGuard::extract()`](../src/ArchiveGuard.php) vérifie toujours l'état actuel de
l'archive juste avant l'extraction. Un `InspectionResult` obtenu auparavant n'est pas considéré
comme une autorisation d'écrire.

Le paquet :

1. vérifie que le répertoire de destination existe déjà et est vide ;
2. vérifie que le répertoire de destination lui-même n'est pas un lien symbolique ;
3. n'écrase pas les fichiers existants ;
4. compare la taille réelle de chaque fichier et le volume total décompressé aux limites
   configurées et aux tailles obtenues lors de la vérification. Si davantage de données
   apparaissent que ce qui était autorisé, l'extraction s'arrête ;
5. lit TAR et TAR.GZ une seconde fois pour l'extraction. L'ordre, le chemin, le type et la
   taille de chaque élément sont comparés à la première vérification ; si l'archive change entre
   les deux passages, l'extraction s'arrête.

Le répertoire de destination est créé par l'application, pas par la bibliothèque. La version
actuelle ne fonctionne qu'avec un répertoire vide séparé. Si une application stocke les
résultats d'extraction dans un dossier commun, elle doit créer un nouveau sous-répertoire vide
pour chaque opération.

## Limites de ressources

Quatre limites obligatoires encadrent :

- la taille du fichier d'archive source ;
- le nombre de fichiers, dossiers et éléments de métadonnées de format comptabilisés ;
- la taille d'un fichier ou autre élément après décompression ;
- le volume total des données décompressées.

Le `maxCompressionRatio` facultatif ajoute une vérification contre une expansion excessive
des données compressées :

- ZIP utilise les tailles des métadonnées ;
- TAR.GZ utilise la quantité réelle de données produite par le décompresseur ;
- TAR non compressé n'utilise pas cette limite.

Il s'agit d'une heuristique supplémentaire, pas d'un remplacement des limites principales de
taille.

## Particularités de Windows

Certains noms sont valides dans ZIP ou TAR mais ne peuvent pas être créés en toute sécurité
sous Windows.

Avant l'écriture, le paquet rejette également, par exemple :

- les noms de périphériques réservés `CON`, `NUL`, `PRN` et similaires ;
- les caractères interdits par Windows ;
- les noms terminant par un point ou un espace ;
- les chemins qui ne diffèrent que par la casse ASCII lorsque Windows les considère comme un
  même chemin.

Ainsi, un appel général à `inspect()` peut accepter une archive tandis que `extract()` sous
Windows s'arrête avec `ExtractionException` avant la première écriture.

## Limitations et raisons

### L'extraction n'est pas atomique

Les fichiers sont écrits directement dans le répertoire de destination préparé. Si l'opération
s'arrête après l'écriture réussie de plusieurs fichiers, ceux-ci restent sur le disque.

Une méthode entièrement atomique nécessiterait d'abord de tout décompresser dans un répertoire
temporaire, puis de remplacer ou déplacer le résultat complet en une seule opération finale.
Ce déplacement présente des contraintes différentes selon les systèmes de fichiers et sous
Windows et nécessiterait un contrat public séparé. La version actuelle ne promet donc pas
d'atomicité.

### Le répertoire n'est pas verrouillé contre les autres processus

La version actuelle n'applique pas de verrou système au répertoire de destination. Si un autre
processus modifie son contenu au même moment, les vérifications de la bibliothèque ne
garantissent pas une protection contre ces changements.

Utilisez un répertoire séparé auquel seule votre application peut accéder pendant l'extraction.

### Les permissions et le propriétaire de l'archive ne sont pas restaurés

Une archive peut contenir ses propres permissions, son propriétaire et ses horodatages. La
version actuelle extrait le contenu des fichiers et la structure des répertoires, mais
n'applique pas ces valeurs depuis l'archive.

### Toutes les fonctionnalités ZIP ou TAR ne sont pas prises en charge

Si la bibliothèque ne peut pas comprendre une fonctionnalité de format de manière suffisamment
fiable, elle rejette l'archive plutôt que de deviner comment la traiter. Par exemple, la
version actuelle n'extrait pas les éléments ZIP chiffrés.

La liste exacte des limitations figure dans la
[référence des archives prises en charge](reference/supported-archives_fr.md).

## Recommandations pratiques

- choisissez des limites `ArchivePolicy` adaptées aux tailles réelles des archives de votre
  application ;
- utilisez un sous-répertoire vide séparé pour chaque extraction ;
- ne permettez pas à des processus non fiables de modifier ce répertoire simultanément ;
- traitez séparément les violations de vérification, les erreurs d'ouverture et les erreurs
  d'écriture ;
- si l'application a créé un répertoire temporaire et le contrôle entièrement, elle peut le
  supprimer après un échec d'extraction selon ses propres règles.

[← Retour au README](readme/README_fr.md)
