# Contribuer

## Choisir une langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/CONTRIBUTING.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/CONTRIBUTING.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/CONTRIBUTING.md) | **Sélectionné** | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/CONTRIBUTING.md) |

Merci de vouloir améliorer Archive Guard. Les changements ici touchent au traitement d'archives non fiables et aux écritures sur le système de fichiers ; un périmètre limité, un comportement vérifiable et des frontières de sécurité explicites sont donc importants.

## Avant de commencer

- Signalez les bugs reproductibles via GitHub Issues.
- Les questions d'utilisation et les idées peuvent être discutées dans GitHub Discussions.
- Signalez les problèmes de sécurité selon la [politique de sécurité](https://github.com/yaleksandr89/archive-guard/security/policy), sans publier de code d'exploitation ni de détails sensibles.
- Discutez d'abord dans une Issue ou une Discussion des changements importants d'API publique, de formats d'archive, de modèle d'extraction ou de frontière de sécurité.

## Contrat du paquet

- Le paquet reste une bibliothèque indépendante des frameworks pour PHP `>=8.4 <8.6`.
- ZIP, TAR et TAR.GZ sont détectés d'après leur contenu.
- Les opérations publiques principales sont `ArchiveGuard::inspect()` et `ArchiveGuard::extract()`.
- `ArchivePolicy` fixe les limites de ressources.
- `inspect()` et `extract()` appliquent les mêmes règles d'acceptation pour `archivePath`, `ArchivePolicy` et `password` ZIP optionnel.
- Les chemins dangereux, collisions normalisées, noms incompatibles avec la plateforme, liens symboliques/physiques, objets spéciaux et fonctionnalités non prises en charge doivent être rejetés.
- Pour ZIP, `inspect()` lit en flux les contenus acceptés, au-delà des seules métadonnées.
- `extract()` exige `ExtractionOptions` : `atomic()` pour un nouveau répertoire absent ou `merge(strategy)` pour un répertoire existant.
- Atomic crée automatiquement l'espace temporaire dans le parent de la destination, prépare tout et publie le résultat avec `rename()`.
- Merge prépare le résultat dans l'espace temporaire, vérifie les conflits puis applique `Reject`, `Skip` ou `Overwrite`.
- `Overwrite` remplace uniquement un fichier ordinaire par un autre. Conflits de types, liens et objets spéciaux sont refusés.
- Les opérations Archive Guard sur une même destination sont coordonnées par `.archive-guard-locks`, sans exclure les écritures de processus externes arbitraires.
- ZIP accepte un mot de passe optionnel à la vérification/extraction ; il ne doit jamais être stocké dans résultats, état ou diagnostics. TAR/TAR.GZ n'ont pas de chiffrement par mot de passe intégré.
- TAR/TAR.GZ comparent la passe d'inspection à une passe d'extraction distincte.
- N'ajoutez ni intégration de framework, ni stockage, tâches d'arrière-plan, retry/fallback automatique, télémétrie ou fonctionnalités hors périmètre.

Voir le [modèle de sécurité](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/security-model.md).

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
fix: исправить проверку размера TAR
feat: добавить поддержку пароля ZIP
docs: уточнить модель безопасности
test: добавить регрессию для коллизии путей
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

- Ajoutez des tests pour des comportements concrets ou des régressions, pas pour compter les assertions.
- Utilisez uniquement des archives ZIP/TAR/TAR.GZ synthétiques et des répertoires temporaires.
- Pour les tests de mot de passe, utilisez des valeurs fictives et vérifiez leur absence des diagnostics.
- N'intégrez pas d'archives utilisateur, fichiers privés, jetons ou identifiants réels.
- Les changements concernant les chemins, le chiffrement, les types d'éléments, les limites, Atomic/Merge ou le système de fichiers doivent tester la limite de sécurité concernée.
- Si le comportement dépend de Windows ou des capacités libzip, conservez les tests adaptés et les vérifications Windows CI lorsque nécessaire.
- N'affaiblissez pas les contrôles pour accepter une archive problématique : définissez d'abord le contrat public sûr.

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
