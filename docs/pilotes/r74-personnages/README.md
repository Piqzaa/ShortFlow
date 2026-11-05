# Milo et Nox — références proposées pour R7.4

5 novembre 2026. Le propriétaire retient **4 USD maximum pour préparer l’essai vidéo**, désigne le compte Fal d’Alex Berrel et délègue la création de personnages originaux amusants. Les images ci-dessous sont créées avec **l’outil intégré `image_gen`**, puis importées localement comme deux nouvelles fiches privées. **Aucun appel image/vidéo Fal, achat de crédits ou consentement de lancement vidéo.**

- **Milo** : chat roux en sweat bleu, regard curieux et sourire trop sûr de lui ; celui qui vient toujours d’avoir une idée douteuse. [Portrait source v1](milo-reference-v1.png).
- **Nox** : chatte anthracite, petite tache blanche au menton, yeux verts et tablier orange ; calme, malicieuse, elle voit déjà venir la catastrophe. [Portrait source v1](nox-reference-v1.png).

Deux références séparées, corps entier, fond de studio neutre, sans texte ni accessoire parasite. Les visages, couleurs et tenues sont distinguables ; les fichiers ont été inspectés visuellement. L’approbation humaine des portraits et la tenue de leur identité dans des vidéos ne sont pas inventées.

Les [prompts complets](prompts-v1.json) sont conservés. Les deux PNG originaux font 1254 × 1254 pixels, environ 2 Mo chacun. Ils restent dans le projet, avec leurs empreintes ; les originaux de l’outil n’ont pas été déplacés ou supprimés. Aucune édition ni variante supplémentaire n’a été demandée.

## Fiches privées préparées

Le seul profil local disposant d’une connexion Fal enregistrée est **Vyd**, utilisateur local 2. Aucune fiche de personnage n’y existait. Les deux nouvelles fiches sont créées en une transaction via `AiCharacters` et `AiReferenceStorage`, sans remplacement :

| Fiche | Identifiant local | Révision | Portrait JPEG privé |
| --- | --- | --- | --- |
| Milo | 1 | 1 | 159 952 octets, 1254 × 1254 |
| Nox | 2 | 1 | 125 470 octets, 1254 × 1254 |

La connexion applicative reste distincte du connecteur Fal de l’assistant. Le nom de compte désigné par le propriétaire est conservé comme intention : **l’appartenance de la clé VydFlow au compte Fal Alex Berrel n’a pas été vérifiée ici**. Aucune clé API n’a été consultée ou déchiffrée ; aucune adresse email, session ou URL signée n’est reproduite dans ce compte rendu.

L’import réutilise les contrôles existants de dimensions/taille/type, le quota privé et la normalisation FFmpeg en JPEG. Validation à blanc avant import, puis contrôle des empreintes des deux fichiers privés après écriture. Seul le compteur `ai_character` passe de 0 à 2 ; `ai_portrait_request`, `ai_generation`, `ai_episode` et `messenger_messages` restent identiques. Aucun test ni suite applicative rejoué pour cette création de contenu ; aucun code applicatif/configuration/schéma changé, cache dev actualisé ou worker redémarré.

Ce sont des images générées par l’outil intégré puis **importées** : elles ne constituent pas un essai réel du générateur de portraits Fal de R7.3b. Les fiches sont consultables dans **Studio → Mes personnages** du profil Vyd ; aucun succès fournisseur ni acceptation utilisateur fictive n’a été créé dans `AiPortraitRequest`.

## Suite

Le [protocole R7.4](../../r7-essai-reel-cadrage.md) référence désormais ces versions et le plafond retenu. Choix de cadrage prévu : conserver l’image entière pour les références, sans rogner les oreilles, pattes ou queue. Les quatre épisodes et leurs devis finaux restent à préparer dans VydFlow. Le lancement des huit clips exige ensuite l’accord distinct prévu dans le cadrage ; aucune vidéo n’est lancée par la création de ces portraits.

[Provenance et contrôles](../../fixtures/r74-portraits-20261105.json). Aucun push, email réel, publication, migration, déploiement, achat de crédits ni activation de génération Fal.
