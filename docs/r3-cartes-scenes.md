# R3.2 — Cartes de scènes compactes

**Allègement suivant du 29 octobre :** le résumé et sa relecture sont retirés des cartes et restent dans l'aperçu/données. Le prompt est visible directement ; le contexte séparé et les images sont repliés. [Comportement actuel](r3-relecture-prompts.md). Cette décision prime sur les descriptions historiques ci-dessous.

**Décision du 29 octobre :** le propriétaire remplace l'édition du résumé par un repère en lecture seule ; seul le prompt permet de modifier les consignes vidéo. Les textes existants sont conservés. [Comportement actuel, diagnostic et limites de recette](r3-relecture-prompts.md). Le bilan ci-dessous décrit la livraison historique du 28 octobre.

28 octobre 2026. Suite du [parcours R3.1 validé par le propriétaire](r3-parcours-commun.md). Cette étape concerne les scènes ; le rapprochement du mode unique, le choix guidé commun et la navigation restent en R3.3–R3.6. Grand R3 en cours, revue globale en R3.7.

## Objectif et comportement

Chaque plan affiche son numéro, sa durée native, son résumé français complet et une indication de références. Le résumé passe sur plusieurs lignes, y compris sur mobile. « Modifier le résumé, le prompt et les images » donne accès aux champs et aux actions d'ordre/retrait. Les préparations existantes gardent leurs textes, références, durées et consentements.

Un ancien plan sans résumé affiche « Résumé à compléter » et laisse ses détails ouverts ; aucun modèle n'est appelé pour le remplir. Le champ facultatif de résumé existait déjà : il devient éditable, avec la même limite serveur de 300 caractères. Le résumé n'est pas envoyé comme prompt vidéo et son édition ne réécrit pas ce prompt.

Après une modification du prompt, la carte demande de revérifier le résumé. « J’ai relu ce résumé avec le prompt » mémorise seulement les deux textes de la saisie courante ; une nouvelle modification retire ce repère. Le rechargement redemande une relecture. Ce repère ne certifie ni vérité, ni fidélité, ni autorisation de coût. Le focus revient au champ de résumé, en ouvrant son détail si nécessaire.

Les indications de références sont des repères de préparation : texte sans image, références communes de personnages/objets ou cadres de plan et raccord depuis le précédent. Le récapitulatif serveur reste l'autorité sur ce qui sera réellement envoyé. Aucun aperçu n'est inventé pour un cadre futur extrait d'un clip.

## Fichiers et rôles Symfony

- `src/Form/AiShotType.php` : le résumé passe de champ caché à champ texte facultatif, avec la même validation de longueur.
- `templates/ai/_shot_card.html.twig` : carte commune aux plans du Studio et au formulaire d'épisode ; vrais éléments HTML `details/summary`, durées et erreurs conservées.
- `_shot_description.html.twig`, `_preparation_plans.html.twig`, `_shot_references.html.twig`, `templates/series/episode.html.twig` : aperçu français échappé, réutilisation de la carte et ouverture des erreurs de références même sans JS.
- `assets/controllers/shot_editor_controller.js` : affichage du résumé après saisie/application, état de relecture, indications de références, ouverture des erreurs natives et focus. Renumérotation groupée des noms, IDs, labels et `aria-describedby` après déplacement.
- `story_writer_controller.js` : résumé ajouté à l'empreinte ; une proposition antérieure ne peut pas remplacer un résumé humain modifié. L'application reste volontaire et ouvre le prompt du premier plan pour relecture.
- `preparation_controller.js` et `src/Controller/AiStudioController.php` : refus d'une réduction automatique qui retirerait un résumé. Même protection côté serveur sans JS ; la transformation complète appartient à R3.3.
- `assets/styles/episode.css` : aspect compact, texte qui revient à la ligne et actions adaptées aux petits écrans.
- Tests frontend et `tests/Functional/AiEpisodeFlowTest.php` : texte humain conservé, ancien plan manuel, absence de coût, erreurs ouvertes et relecture invalidée après modification.

Flux : Symfony construit le formulaire depuis la préparation existante → Twig affiche la carte et ses erreurs → Stimulus met à jour le résumé et les repères de saisie → soumission manuelle → validations et enregistrement existants → devis → confirmation distincte. Fermer un détail HTML ne retire aucun champ de la soumission ; une protection visuelle ne remplace pas la validation serveur.

## Vérifications

- Base vérifiée `shortflow_test` et trois migrations manquantes du poste appliquées avant PHPUnit ; aucune suite DB concurrente.
- Premier passage ciblé : `php bin/phpunit tests/Functional/AiEpisodeFlowTest.php tests/Functional/AiStoryProposalTest.php` : **32 tests / 520 assertions réussis**, avant le dernier cas ajouté et le correctif de lisibilité mobile.
- Dernier passage des trois cas cartes : **3 tests / 38 assertions réussis**, avec cache test sans debug régénéré. Dernier cas de refus : statut Symfony 422, champs conservés et détail d'image ouvert. Les premières tentatives de ce cas ont exposé un ancien cache sans debug, puis deux erreurs d'attente dans le nouveau test (statut attendu 200 au lieu de 422 et sélection du formulaire de déconnexion) ; test corrigé, protections applicatives conservées.
- **155 tests frontend réussis**, `npm run check`, build Vite, PHPStan sur les deux fichiers PHP modifiés, CS-Fixer sur ceux-ci et le test fonctionnel, lint de 37 Twig et conteneur test réussis. Avertissements de build préexistants sur les polices d'éditeur et le chunk Three.js. Pas de nouvelle suite PHP complète ; chiffres R2.7 historiques.
- Revue indépendante en lecture seule : quatre protections demandées et vérifiées après correction (empreinte du résumé, réduction sans perte, focus, erreurs d'image sans JS). Aucun blocage restant identifié par cette lecture ; revue globale R3 encore à faire.

La recette utilise le HTML réel d'une préparation synthétique de test, les assets compilés et un serveur loopback GET uniquement. Les interactions ne peuvent accepter aucun POST fournisseur, sauvegarde ou envoi social. Le premier serveur lancé dans le sandbox était inaccessible au navigateur ; relance locale sur loopback, sans modification du pare-feu ni exposition publique.

Recette finale : desktop 1440 × 900 et mobile 390 × 844, carte repliée/dépliée, résumé long intégralement lisible, aucun débordement horizontal observé (largeur utile/scroll 375/375), édition séparée du prompt, alerte puis relecture avec retour du focus. Ajout d'un plan, saisie manuelle et déplacement vérifiés : résumé/prompt/durée restent associés, IDs et labels suivent l'ordre. Tab depuis le résumé mène au prompt. Aucun avertissement/erreur console capturé sur le rendu final. Les captures ont été inspectées pendant la recette ; ce contrôle synthétique ne remplace pas la recette globale des références, états et écrans de R3.7. Le libellé ancien « Subjects » et le contexte de navigation observés restent explicitement en R3.6. Onglets fermés et viewport restauré.

Cache dev actualisé et quatre workers précédemment actifs relancés après garde zéro message, rédaction, occurrence en traitement, génération et montage actifs. Aucun schéma applicatif ajouté, clé remplacée, appel réel de modèle, génération payante, publication, activation réelle ou push.

## Comment essayer

Dans Studio ou un épisode encore en préparation, ouvrir « Personnaliser les scènes et les durées ». Comparer une carte avec résumé à un ancien plan sans résumé, ouvrir ses détails, modifier le prompt puis relire le résumé. Déplacer/ajouter un plan et vérifier les champs au clavier. Tenter une réduction de découpage avec un résumé sur un plan retiré : le texte doit rester présent avec une explication. Le devis et son consentement restent nécessaires pour la vidéo.

Le résultat n'est pas une refonte complète de l'écran : le reste du cadrage est livré progressivement. **Prochaine tâche : R3.3.**
