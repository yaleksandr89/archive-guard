# Politique de sécurité

## Choisir une langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/SECURITY.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/SECURITY.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/SECURITY.md) | **Sélectionné** | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/SECURITY.md) |

## Versions prises en charge

Les correctifs de sécurité sont publiés pour la ligne stable actuelle `1.x`.

| Version | Prise en charge |
|---|---|
| `1.x` | Oui |

## Ce qui constitue une vulnérabilité

Les problèmes de sécurité comprennent notamment :

- le contournement des chemins sûrs permettant une écriture hors de la destination attendue ;
- l'extraction de liens symboliques ou physiques, objets spéciaux ou autres entrées qui devraient être rejetés ;
- le contournement de `ArchivePolicy` permettant d'appliquer des données au-delà des limites ;
- une divergence entre le contenu vérifié et celui réellement appliqué ;
- le contournement des règles de noms ou collisions de chemins déterministes de la plateforme entraînant une écriture inattendue ;
- une violation de la garantie Atomic exposant le répertoire final comme publié avant la fin des contrôles obligatoires ;
- le contournement de la vérification préalable Merge ou de la stratégie choisie, remplaçant un objet non prévu ;
- une opération Archive Guard conforme au protocole pouvant contourner le verrou coopératif d'une autre opération sur la même destination ;
- une fuite du mot de passe ZIP via exception, violation, résultat, état ou diagnostic ;
- une faille de lecture/analyse ZIP, TAR ou TAR.GZ ayant un impact de sécurité concret ;
- une compromission du code source, CI, tags de version, paquet ou autre élément de la chaîne d'approvisionnement.

## Limites du modèle de sécurité

Atomic publie un résultat entièrement préparé dans un nouveau répertoire. Merge prépare tout dans l'espace temporaire et vérifie les conflits avant d'appliquer la stratégie choisie. Les opérations Archive Guard concurrentes sur la même destination se coordonnent via `.archive-guard-locks`.

La prise en charge du chiffrement ZIP dépend de `ZipArchive`/`libzip` dans l'environnement actuel. Tout contournement de ces contrôles, modes d'application ou protections du mot de passe doit être considéré comme une vulnérabilité potentielle.

## Signaler une vulnérabilité

GitHub Private Vulnerability Reporting est le canal privilégié lorsqu'il est disponible pour le dépôt :

1. Ouvrez l'onglet **Security** du dépôt.
2. Accédez à **Advisories**.
3. Sélectionnez **Report a vulnerability**.
4. Envoyez le rapport sans publier de détails dans une Issue publique.

Si le formulaire privé n'est pas disponible, ouvrez une Issue publique minimale sans code d'exploitation ni détails sensibles et demandez un canal de communication privé.

Ne publiez pas avant la disponibilité d'un correctif :

- un exploit prêt à l'emploi ou une archive reproduisant directement un contournement de protection ;
- de vrais secrets, jetons, identifiants ou données privées ;
- des chemins, contenus de fichiers ou journaux de production contenant des informations sensibles.

## Informations à fournir

Dans la mesure du possible, indiquez :

- mode d’extraction : Atomic ou Merge, et stratégie de conflit le cas échéant ;
- présence d’un mot de passe ZIP, **sans transmettre le secret** ;
- la version du paquet concernée ou le SHA du commit ;
- la version de PHP et le système d'exploitation ;
- le format de l'archive : ZIP, TAR ou TAR.GZ ;
- l'impact sur la sécurité ;
- les étapes minimales de reproduction ;
- une archive synthétique minimale ou les instructions permettant de la construire ;
- le comportement attendu et le comportement réel ;
- une correction possible, si elle est connue.

Utilisez des données synthétiques. Ne joignez pas de vrais secrets ni de fichiers privés d'utilisateurs.

## Suite du traitement

Le projet est maintenu par un seul auteur ; aucun SLA fixe n'est donc garanti. Le rapport sera examiné dès que possible et, si le problème est confirmé, un correctif et un test de régression seront préparés.

Merci de coordonner la divulgation publique avec le mainteneur avant de publier les détails techniques. Aucun programme de récompense n'est garanti.
