# Politique de sécurité

## Choisir une langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_en.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_es.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_zh.md) | **Sélectionné** | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_de.md) |

## Versions prises en charge

Les correctifs de sécurité sont publiés pour la ligne stable actuelle `1.x`.

| Version | Prise en charge |
|---|---|
| `1.x` | Oui |

## Ce qui constitue une vulnérabilité

Les problèmes de sécurité comprennent notamment :

- contourner la validation des chemins de façon à pouvoir écrire un fichier hors du répertoire de destination ;
- extraire un lien symbolique, un lien physique, un objet spécial ou un autre élément que le paquet est censé rejeter ;
- contourner les limites de `ArchivePolicy` afin que l'extraction continue après le dépassement de la taille autorisée d'un élément, du volume total de données ou d'une autre limite configurée ;
- une différence entre le contenu vérifié et le contenu réellement extrait permettant d'écrire des données non validées ;
- contourner les contrôles des noms Windows ou des collisions de chemins de façon à écrire les données à un emplacement inattendu ;
- une erreur d'analyse ZIP, TAR ou TAR.GZ ayant un impact concret sur la sécurité, par exemple une écriture de fichier non prévue ;
- la compromission du code source, de la CI, des tags de publication, des paquets publiés ou d'une autre partie de la chaîne d'approvisionnement.

## Ce qui n'est pas une vulnérabilité en soi

Les comportements suivants sont des limites documentées de la version actuelle :

- l'extraction n'est pas atomique et des fichiers déjà créés peuvent rester après un échec ;
- le répertoire de destination n'est pas verrouillé contre les modifications concurrentes d'un autre processus ;
- le répertoire de destination doit déjà exister, être vide et ne pas être un lien symbolique ;
- les fichiers et répertoires existants ne sont pas écrasés ;
- les ZIP chiffrés ne sont pas extraits et aucun mot de passe n'est demandé ;
- les permissions, le propriétaire et les horodatages stockés dans l'archive ne sont pas restaurés ;
- les fonctionnalités de format non prises en charge sont rejetées au lieu d'être traitées partiellement.

Si le comportement réel enfreint une garantie documentée ou permet de contourner un contrôle tout en respectant les conditions d'utilisation documentées, il peut s'agir d'un problème de sécurité.

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
