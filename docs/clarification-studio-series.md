# Clarification Studio/Séries — 26 octobre 2026

## Objectif

Les retours du propriétaire révèlent trois ambiguïtés : les images semblent obligatoires en mode texte, le terme BYOK suppose des connaissances techniques et la préparation récurrente est difficile à découvrir lorsqu’une connexion manque.

## Changements

- `templates/ai/_shot_references.html.twig`, `_subjects.html.twig` et `templates/series/configure.html.twig` expliquent les modes : texte sans images, image avec début obligatoire et fin selon le modèle, personnage avec référence principale obligatoire et références supplémentaires facultatives. Une référence de personnage ne fixe pas les cadres.
- `src/Form/AiIdeaType.php` indique dès le choix du mode que le texte seul suffit. `src/Form/AiSeriesType.php` explique la connexion au fournisseur et sa facturation directe. Les messages de `AiKeys`, `AiEpisodeFlow` et `SeriesRecurrences` emploient le même vocabulaire, sans sigle BYOK dans l’interface.
- Les vues de liste, série et épisode affichent la durée par épisode et le compte fournisseur. Le bloc `_recurrence.html.twig` explique l’activation distincte, les limites de 1 à 12 épisodes et la validation avant publication. En cas de prérequis manquant, il donne accès aux connexions et aux réglages de la série.

Un second retour a confirmé un décalage de connexion : une série configurée avec une clé API restait bloquée malgré une connexion Fal par navigateur. Le message nomme désormais ce décalage et indique le réglage à modifier. Le bloc affiche toujours la méthode choisie. Pour une nouvelle série seulement, `AiSeriesController` présélectionne Fal par navigateur si c’est la seule connexion Fal enregistrée pour le propriétaire. Les séries existantes et les autorisations de facturation restent inchangées tant que le créateur ne les modifie pas explicitement.

Aucun changement de contrat fournisseur, consentement, contrôle propriétaire ou traitement automatique. Aucune série réelle n’est activée. Le test fonctionnel ajouté vérifie la suggestion sur une nouvelle série, la conservation du mode des anciennes, l’isolation des connexions d’un autre compte et l’absence de message de génération.

## Ce que permet la récurrence

Après autorisation explicite des coûts, la série peut écrire, générer et assembler un nouvel épisode chaque semaine. La préparation commence normalement 24 heures avant la publication visée. Le créateur doit ensuite valider la vidéo, relire les textes et confirmer la programmation. La publication sans intervention humaine n’est pas intégrée. Docker, les workers et le moteur de rédaction local doivent rester disponibles ; un hébergement durable est nécessaire pour ne pas dépendre de l’ordinateur du propriétaire.

## Flux Symfony et recette manuelle

Le formulaire recueille le choix de connexion ; les services contrôlent les prérequis, la propriété et le consentement ; Twig affiche les explications et les liens. Les libellés ne changent pas les valeurs techniques `api` et `mcp`, ni les préparations enregistrées. Messenger conserve son rôle d’exécution des tâches déjà autorisées.

Sans dépense : ouvrir une création en mode texte et vérifier l’aide sur les cadres ; comparer avec un modèle image et un modèle personnage ; ouvrir une série sans connexion utilisable et suivre les liens proposés ; vérifier que le simple enregistrement n’active aucun épisode automatique. Ne pas confirmer de coûts ni de publication pendant cette recette.

## Vérifications

Revue croisée en lecture seule : terminologie et fonctionnement confirmés ; précisions sur les fins non prises en charge et les références de personnage incorporées.

Résultats techniques consignés à la fin de la validation dans `suite-audit-studio-series.md`. Les changements de texte n’ajoutent pas de tests qui reproduiraient simplement ces libellés ; les contrôles existants sont réutilisés.
