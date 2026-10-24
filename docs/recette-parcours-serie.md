# Recette du parcours série — 24 octobre 2026

## Objectif et isolation

Vérifier dans le navigateur le parcours d'une série fictive, du créneau hebdomadaire à la préparation dans le calendrier. L'application complète tourne sur localhost:8001 dans une copie Linux temporaire, exclusivement avec `shortflow_test`, un utilisateur synthétique et des connexions sociales factices. Le studio de développement reste distinct.

Les vrais formulaires, contrôleurs, entités Doctrine, messages Messenger et moteur FFmpeg sont utilisés. Le rédacteur appelle réellement Qwen3 8B via Ollama local pour le premier épisode. Les appels de génération vidéo sont remplacés par un MockHttpClient strict qui fournit des mires vidéo locales avec son. Tous les clients sociaux refusent toute requête. L'horloge injectée est simulée ; les messages devenus échus à cette horloge sont rendus disponibles pour les vrais consommateurs Messenger. Il ne s'agit pas d'une attente hebdomadaire réelle.

## Résultats observés

| Étape | Observation |
| --- | --- |
| Configuration | Série « Recette fictive — Objets du quotidien », présentation d'un mug, 30 secondes finales, 9:16, un épisode initial |
| Récurrence | Deux créneaux autorisés, mardi 18 h Europe/Paris, YouTube et Instagram proposés ; estimation 2,10 USD par épisode |
| Préparation anticipée | Au 26 octobre, création d'un nouvel épisode sans écraser l'épisode manuel initial ; prochain créneau au 3 novembre |
| Rédaction réelle locale | Deux appels Ollama : scénario français, identité et trois prompts anglais pour des plans de dix secondes ; titre « Mug céramique sur table de bois » |
| Pause | Après la première transmission vidéo simulée, la pause laisse ce résultat se terminer et conserve les deux autres plans à générer. Une seule soumission recensée à ce stade |
| Reprise confirmée | Les deux plans restants sont générés avec les consommateurs réels ; trois soumissions simulées au total, trois téléchargements |
| Assemblage réel | FFmpeg produit un MP4 de 1080 × 1920, durée mesurée 30,022 secondes ; état « À valider » |
| Validation et brouillon | Choix explicite de la version finale ; préparateur prérempli avec les deux réseaux, textes, déclarations IA et date du 27 octobre à 18 h |
| Calendrier | Après vérification et confirmation dans l'environnement simulé, la préparation apparaît au 27 octobre |
| Créneau dépassé | Consommation à 18 h 03 : préparation bloquée avec demande de nouvelle date ; aucun appel social |
| Erreur de rédaction | Réponse structurée volontairement invalide au deuxième créneau : récurrence en pause, épisode conservé, aucune soumission vidéo supplémentaire ni réservation supplémentaire |
| Annulation | Annulation confirmée depuis le brouillon bloqué ; état « Annulée », textes et vidéo conservés |

La preuve locale est conservée dans le dossier ignoré `artifacts/series-recipe/` : captures `01-recurrence-active.png`, `02-calendrier.png`, `03-erreur-pause.png`, `04-annulation.png`, état technique `final-status.json` pris après le blocage et avant l'annulation, et harnais temporaire. Ce harnais est propre à cette session, pas une commande de lancement disponible en production.

## Ajustements issus de la recette

- `templates/series/index.html.twig` : le texte vide respecte désormais le nombre d'épisodes choisi ; les cartes affichent la durée finale plutôt que la durée native d'un clip.
- `templates/series/show.html.twig` : pendant la rédaction récurrente, afficher « Rédaction en cours » et « Ouvrir l'épisode » sans inviter simultanément à préparer une histoire vide.
- `templates/series/_recurrence.html.twig` : parler de « créneaux utilisés », car le compteur inclut les tentatives commencées, même si la rédaction échoue.
- `tests/Functional/AiSeriesTest.php` : vérifier que la carte indique bien 30 secondes finales avec le réglage natif par défaut de cinq secondes.

Ces ajustements concernent l'affichage Twig. Les contrôleurs et services continuent à déterminer les états et les autorisations. Le chemin est : formulaire privé → Doctrine → message Messenger → rédacteur local → préparation des plans → génération simulée → assemblage FFmpeg → validation utilisateur → brouillon durable → confirmation du calendrier. Comprendre en Symfony : la vue explique l'état enregistré ; elle ne doit pas déclencher une génération ni décider du consentement.

## Vérifications et limites

Migrations de `shortflow_test` à jour : `Version20261024020000`. Lint des 83 fichiers Twig réussi. Après restauration de la configuration de test habituelle, `AiSeriesTest` et `SeriesRecurrencesTest` : **21 tests, 193 assertions, aucun échec**, PHP 8.4.27. `git diff --check` réussi. Aucun JavaScript ni CSS modifié ; le build frontend et la suite PHP complète n'ont pas été relancés dans ce lot.

Le navigateur desktop confirme le calendrier, les états, la reprise et l'annulation ; aucun avertissement ou erreur console enregistré pendant le parcours consulté. Pas de nouvelle recette mobile dans ce lot. La copie temporaire et ses médias sont supprimés à la fin ; les tests fonctionnels nettoient leurs données dans la base de test.

Cette recette valide l'enchaînement logiciel, pas la qualité visuelle d'une génération IA, la cohérence du mug entre clips réels, le fonctionnement après plusieurs semaines ou les délais des plateformes. Aucun coût réel, appel social, série réelle activée, push ou déploiement. Avant un premier épisode vidéo réel, définir séparément son sujet, son modèle et son plafond de dépense puis obtenir l'autorisation correspondante. La relecture éditoriale reste nécessaire.
