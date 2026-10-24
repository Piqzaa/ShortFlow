# Séries : suppression et images de référence

Lot local du 24 octobre 2026. Aucune série du propriétaire supprimée, aucun appel IA payant, aucune publication ni push.

## Ce qui manquait

La suppression d’une série n’avait pas de route ni de bouton. Les images existaient déjà côté serveur, mais leur section était cachée avec un modèle texte. Le libellé « Personnages de référence » désignait seulement une description textuelle : remplir ce champ n’envoyait aucune photo au fournisseur.

## Utilisation

Dans une série, ouvrir « Configurer la série », choisir « Animer une image de référence », puis choisir explicitement un modèle compatible. La section 03 permet alors d’importer l’image de départ commune et, selon le modèle, une image de fin. Aucun changement de modèle payant n’est choisi automatiquement. Les fichiers restent privés et les contrôles existants de format, taille et propriété sont conservés.

L’image guide le premier cadre ; elle ne garantit ni une identité parfaite ni les raccords entre clips indépendants. Les épisodes déjà estimés ou générés gardent leur préparation figée : modifier la configuration ne réécrit pas leurs vidéos.

« Supprimer la série… » ouvre une page avec une case de confirmation. La configuration et ses épisodes sont supprimés définitivement. Les vidéos, l’historique des générations et les brouillons/programmes de publication restent disponibles. Les programmations ne sont donc pas annulées. La suppression est refusée si la récurrence est active ou si un épisode est en rédaction, génération ou assemblage.

## Composants et flux Symfony

- `AiSeriesType` construit les champs et précise que les personnages sont une description textuelle. Le choix du point de départ est une aide d’interface non persistée ; le modèle reste la source de vérité validée côté serveur.
- `series_settings_controller.js` filtre les modèles après un changement de point de départ, vide un choix incompatible et active les champs image compatibles. La section reste visible pour expliquer son fonctionnement.
- `configure.html.twig` présente ces réglages. `show.html.twig` expose l’entrée de suppression ; `delete.html.twig` explique ses conséquences.
- `AiSeriesController::delete` vérifie la propriété, traite un formulaire Symfony avec CSRF et confirmation, puis délègue au service. Un GET ne supprime rien.
- `AiSeriesStudio::delete` verrouille le propriétaire et la série dans une transaction, vérifie la révision et les traitements actifs, puis supprime la série. Les relations existantes conservent vidéos/générations et détachent les préparations de publication. Aucune migration nécessaire.

À comprendre : Stimulus améliore l’ergonomie ; il ne remplace jamais les contrôles serveur. La révision protège contre une confirmation devenue périmée, et les verrous coordonnent les opérations concurrentes.

## Vérification

Tests fonctionnels exécutés exclusivement sur `shortflow_test`, sans API réelle : `AiSeriesTest` et `SeriesRecurrencesTest`, **24 tests, 224 assertions réussis**. Couverture : propriété, CSRF, consentement, révision périmée, traitements actifs, récurrence et conservation d’une vidéo et d’une programmation. Deux erreurs initiales de fixtures Doctrine détachées ont été corrigées avant cette exécution finale.

Tests frontend : **111 réussis**. `npm run check`, `npm run build`, PHPStan niveau 5, CS-Fixer sur les quatre fichiers PHP modifiés, lint conteneur test et lint des **87 templates Twig** réussis.

Navigateur : configuration synthétique rendue par Symfony, changement texte → image sans sélection automatique d’un modèle, activation des fichiers avec un modèle image, console sans erreur et contrôle mobile 390 px sans débordement horizontal. Aucun formulaire de suppression réel soumis. Le rendu synthétique de la confirmation est couvert par les tests HTTP ; sa navigation dans le navigateur de vérification a été interrompue. Captures locales ignorées dans `artifacts/series-image-choice.png` et `artifacts/series-image-choice-mobile.png`.

Pour tester manuellement : recharger la série, vérifier les champs avant et après choix d’un modèle image, enregistrer seulement si désiré. Ouvrir la suppression et revenir avec « Garder ma série ». Toute suppression définitive d’une série existante appartient au propriétaire.
