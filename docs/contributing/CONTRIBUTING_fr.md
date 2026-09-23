# Contribuer

## Choisir une langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_en.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_es.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_zh.md) | **Sélectionné** | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_de.md) |

Merci de vouloir améliorer Archive Guard. Les changements ici touchent au traitement d'archives non fiables et aux écritures sur le système de fichiers ; un périmètre limité, un comportement vérifiable et des frontières de sécurité explicites sont donc importants.

## Avant de commencer

- Signalez les bugs reproductibles via GitHub Issues.
- Les questions d'utilisation et les idées peuvent être discutées dans GitHub Discussions.
- Signalez les problèmes de sécurité selon la [politique de sécurité](https://github.com/yaleksandr89/archive-guard/security/policy), sans publier de code d'exploitation ni de détails sensibles.
- Discutez d'abord dans une Issue ou une Discussion des changements importants d'API publique, de formats d'archive, de modèle d'extraction ou de frontière de sécurité.

## Contrat du paquet

- Le paquet reste une bibliothèque indépendante des frameworks pour PHP `^8.4`.
- Les formats pris en charge sont détectés à partir du contenu : ZIP, TAR et TAR.GZ.
- Les principales opérations publiques sont `ArchiveGuard::inspect()` et `ArchiveGuard::extract()`.
- Les limites de ressources sont configurées avec `ArchivePolicy`.
- Les chemins dangereux, les liens symboliques et physiques, les objets spéciaux et les fonctionnalités de format non prises en charge doivent être rejetés.
- `extract()` effectue la vérification obligatoire avant l'écriture, n'écrase pas les fichiers existants et contrôle le volume réellement écrit.
- Pour TAR et TAR.GZ, le résultat du premier passage est comparé au passage d'extraction.
- Le répertoire de destination doit déjà exister, être vide et ne pas être un lien symbolique.
- La version actuelle ne promet ni extraction atomique, ni verrouillage du répertoire, ni extraction des ZIP chiffrés, ni restauration des métadonnées du système de fichiers stockées dans l'archive.
- N'ajoutez pas de bundle/service provider de framework, de stockage, de tâches en arrière-plan, de retry/fallback automatiques, de télémétrie ou de fonctions sans rapport avec la tâche.

Les frontières détaillées sont décrites dans le [modèle de sécurité](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security-model_fr.md).

## Branches

Utilisez un nom court décrivant le changement, par exemple :

```text
fix/tar-size-validation
feat/zip-password
docs/security-policy
```

## Commits

Utilisez Conventional Commits avec une description concise :

```text
fix: corriger la validation de taille TAR
feat: ajouter la prise en charge du mot de passe ZIP
docs: clarifier le modèle de sécurité
test: ajouter une régression de collision de chemins
```

Chaque commit doit contenir un changement cohérent sans refactorisation sans rapport.

## Vérifications locales

Installez les dépendances et exécutez l'ensemble complet :

```shell
composer install
composer check
```

Des vérifications ciblées sont également disponibles :

```shell
composer test
composer analyse
composer cs:check
```

`composer coverage` est un rapport de diagnostic et n'est pas obligatoire pour chaque changement.

## Tests et fixtures

- Ajoutez des tests pour un comportement concret ou une régression, pas pour augmenter le nombre d'assertions.
- Utilisez uniquement des fixtures ZIP/TAR/TAR.GZ synthétiques et des répertoires temporaires.
- N'ajoutez pas d'archives utilisateur réelles, de fichiers privés, de jetons ou d'identifiants.
- Les changements de validation des chemins, de types d'éléments, de limites ou d'extraction doivent couvrir la frontière de sécurité concernée.
- Si le comportement dépend de Windows, conservez ou ajoutez une couverture CI Windows lorsque c'est pertinent.
- N'affaiblissez pas une vérification uniquement pour accepter une archive problématique ; définissez d'abord le contrat public sûr.

## Pull Request

Dans la description de la Pull Request, indiquez :

- le problème et la modification mise en œuvre ;
- l'impact sur l'API publique et la compatibilité ascendante ;
- la frontière sécurité/système de fichiers concernée ;
- les tests ajoutés ou mis à jour ;
- les vérifications exécutées ;
- les changements de documentation et la synchronisation des traductions lorsque le comportement public a changé.

Avant l'envoi, vérifiez :

- que le diff ne contient aucun changement sans rapport ;
- que `git diff --check` passe ;
- que `composer check` ou un ensemble justifié de vérifications pertinentes passe ;
- que `vendor/`, `composer.lock`, `.build/`, des archives réelles contenant des données privées ou d'autres artefacts locaux ne sont pas commités ;
- que l'API publique et la documentation correspondent au comportement réel ;
- que les détails de sécurité ne sont pas divulgués prématurément dans une Issue ou une Pull Request publique.
