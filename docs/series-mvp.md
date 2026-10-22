# Séries IA — premier lot local du 22 octobre 2026

## Objectif et périmètre livré

Une série conserve son nom, son thème, son style, ses personnages décrits et ses réglages vidéo. Sa création prépare **trois épisodes** en base, chacun avec un titre et un prompt modifiables. Le contexte commun est copié dans leurs prompts ; aucune IA de rédaction n’est appelée. Le créateur complète lui-même la scène et l’action de chaque épisode.

Parcours : **Séries → créer une série → compléter les épisodes → vérifier une estimation → confirmer la génération dans le parcours IA existant**. Le premier lot s’arrête à l’organisation et à la préparation individuelle. Le lancement par lots avec estimation totale, la validation éditoriale et la programmation depuis la série sont les étapes suivantes. Ni fréquence automatique, ni abonnement, ni quota différencié ne sont ajoutés.

Tous les modèles du catalogue existant restent disponibles. Une image de départ commune est obligatoire pour les modèles image vers vidéo ; une image de fin est possible ou obligatoire selon le modèle. Les fichiers restent privés dans `AiReferenceStorage` et sont copiés par référence dans chaque préparation. Les personnages ne bénéficient d’aucune garantie de cohérence entre générations.

L’accès BYOK est un choix de mode (`api` ou connexion navigateur Fal), pas une nouvelle copie de la clé. Les connexions existantes sont réutilisées au moment de la confirmation. La série peut être préparée avant de connecter son fournisseur. Les coûts sont ceux du catalogue existant, en dollars US indicatifs ; leur acceptation reste individuelle et explicite.

## Composants Symfony

| Fichier | Rôle |
| --- | --- |
| `src/Entity/AiSeries.php` | Propriétaire, univers commun, recette JSON typée/documentée et numéro de révision. |
| `src/Entity/AiEpisode.php` | Numéro unique dans sa série, titre, prompt et lien unique vers une génération existante. Aucun second statut de génération. |
| `src/Form/AiSeriesType.php` | Champs et limites, choix du catalogue, validation de la durée/du format/du mode d’accès, validation des fichiers. |
| `src/Form/AiEpisodeType.php` | Validation des titres/prompts et des révisions soumises. |
| `src/Service/AiSeriesStudio.php` | Enregistrement transactionnel, création des trois épisodes, images privées et préparation idempotente d’une `AiGeneration`. |
| `src/Controller/AiSeriesController.php` | Routes privées, autorisations propriétaire, formulaires, erreurs et redirections. |
| `src/Entity/AiGeneration.php`, `src/Service/AiGenerations.php` | Modification limitée à une préparation non confirmée et contrôle du contenu effectivement accepté lors de la confirmation. |
| `src/Controller/AiStudioController.php`, `templates/ai/generation.html.twig` | Retour vers la série et réutilisation du récapitulatif, du coût, du worker et du suivi existants. |
| `templates/series/`, `assets/styles/series.css` | Liste paginée, configuration et liste commune des épisodes. |
| `assets/controllers/series_settings_controller.js` | Options compatibles et images présentées selon le modèle ; le serveur valide aussi sans JavaScript. |
| `templates/studio/shell.html.twig`, `assets/styles/workspace-responsive.css`, `assets/app.js` | Entrée Séries active dans les navigations desktop et mobile. |
| `migrations/Version20261022153610.php` | Ajout des deux tables et de leurs contraintes ; aucune modification des données existantes. |

Les formulaires Symfony apportent le CSRF et la validation serveur. Le service concentre les règles métier et les transactions Doctrine. Les entités conservent les données ; les templates présentent ces données avec échappement Twig. Les champs masqués de révision ne constituent jamais une autorisation : les ressources sont d’abord recherchées avec leur propriétaire.

La recette JSON contient uniquement un petit ensemble fixe de paramètres déjà utilisés par le studio IA. Ce choix évite une nouvelle hiérarchie de classes et une table par réglage. Le fournisseur est déduit du modèle du catalogue ; aucun secret, jeton ou identifiant de compte facturé n’est stocké dans cette recette.

## Flux d’exécution et protections

1. Le contrôleur vérifie le compte confirmé et traite le formulaire. Les noms des images existantes ne viennent jamais de champs soumis.
2. Le service verrouille le propriétaire puis, lors d’une modification, la série. Il vérifie la révision avant d’enregistrer. Les images passent par la validation réelle et le réencodage FFmpeg du stockage existant. Les trois épisodes sont créés dans la même transaction que la série.
3. Chaque épisode possède son propre formulaire sur la page commune. Enregistrer un prompt ne crée pas de génération. La vérification d’estimation crée au plus une préparation liée, sous verrou, sans message Messenger ni requête fournisseur.
4. Avant confirmation, le prompt reste modifiable depuis la série. Le titre et le prompt de la préparation liée sont actualisés ensemble. Les réglages vidéo de cette préparation restent ceux de son premier récapitulatif.
5. Les changements de configuration ne réécrivent jamais les prompts existants. Les nouveaux réglages vidéo s’appliquent aux épisodes sans préparation ; les préparations déjà liées conservent leur modèle, leurs images, leur durée et leur estimation.
6. Un ancien onglet ne peut pas écraser silencieusement un prompt enregistré ailleurs. Une répétition identique du bouton de préparation retrouve la même génération ; une saisie différente sur une révision périmée affiche une erreur en conservant le formulaire saisi.
7. Le récapitulatif transmet une empreinte du contenu affiché. Le moteur existant la vérifie sous le verrou du propriétaire avant de mettre la génération en file : un prompt modifié depuis l’aperçu exige une nouvelle vérification. Cette empreinte est fondée sur le contenu, pas sur une date tronquée à la seconde en base.
8. Après confirmation, l’épisode devient non modifiable et affiche le statut réel de sa `AiGeneration`. Le worker, les erreurs, la récupération, l’import en bibliothèque et les outils de montage/publication restent ceux déjà développés. Les états de la liste se mettent à jour au rechargement de la page.

Les clés restent chiffrées par `AiKeys`. Les listes sont limitées au propriétaire et paginées ; les pages sont privées et non stockables. L’enregistrement d’une configuration est limité par le limiteur existant. Les formulaires avertissent avant de quitter avec une saisie non enregistrée.

## Limites assumées

- Trois épisodes initiaux fixes ; ajout/suppression/réorganisation d’épisodes et suppression d’une série hors de ce lot.
- Un épisode correspond à une génération vidéo ; il ne représente pas encore un montage narratif de plusieurs plans.
- Une seule préparation liée par épisode. Un résultat incertain se récupère depuis son suivi existant ; aucun nouvel envoi silencieux.
- L’interface n’envoie ni ne programme un ensemble d’épisodes. La vidéo disponible reste accessible par le parcours existant pour montage ou publication manuels.
- Les images communes peuvent être remplacées. Les anciennes restent conservées pour préserver les préparations et l’historique. Comme dans le studio existant, elles comptent dans la limite technique de 100 Mo d’images ; il n’y a pas de quota Gratuit/Creator dans ce lot. Une validation tardive d’image peut laisser un fichier préparé sans série enregistrée, également borné par ce plafond.
- Une image de fin déjà enregistrée reste utilisée si le modèle accepte une fin ; l’interface actuelle permet son remplacement. La retirer spécifiquement reste une petite amélioration ultérieure.
- Les tarifs du catalogue et les capacités réelles restent vérifiés par le parcours IA existant. Les tests de ce lot sont locaux et synthétiques : aucun essai payant ou social réel supplémentaire.

## Comment tester

Après mise à jour : appliquer la migration additive et vider le cache dev ; reconstruire les assets avec `npm run build`. Si un worker doit être relancé après mise à jour PHP, attendre la fin des tâches en cours. Ne pas vider les files et ne pas supprimer les données existantes.

Dans le studio connecté, ouvrir `/studio/series`, créer une série texte sans fournisseur connecté, vérifier les trois prompts puis les enregistrer. Vérifier une estimation : aucun débit ni génération ne doit partir. Revenir à la série, modifier le prompt, puis vérifier que l’ancien récapitulatif ne permet pas une confirmation obsolète. Tester deux onglets, un modèle à image, une durée invalide sans JavaScript, et les connexions absentes. Un autre compte ne doit voir ni les séries, ni leurs images.

Tests automatisés : `tests/Functional/AiSeriesTest.php`, avec appels IA interdits par un client HTTP simulé, exclusivement sur `shortflow_test` après migration. Le test frontend `tests/Frontend/series_settings.test.js` vérifie les choix compatibles et la présentation des images. Exécuter aussi les contrôles décrits dans le README ; leurs résultats réellement obtenus sont consignés dans `docs/verification.md`.

Pour comprendre Symfony : une route ne fait pas le travail du worker. Ici, « préparer » enregistre des entités ; seule la confirmation existante produit le message asynchrone. Le statut affiché vient de l’entité traitée par ce worker, sans seconde machine d’états à maintenir pour les séries.
