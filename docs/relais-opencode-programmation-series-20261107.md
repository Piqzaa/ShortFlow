# Relais OpenCode · Programmation groupée des vidéos importées

## Demande validée

Dans **Séries → Utiliser mes vidéos**, le propriétaire veut programmer plusieurs épisodes depuis la même série. Il choisit la date et l’heure de chaque épisode, relit les textes et les comptes destinataires, puis confirme la programmation des épisodes prêts en une fois. Les envois démarrent aux créneaux confirmés via le moteur existant. Le premier scénario de recette comporte deux vidéos, deux créneaux distincts et des envois simulés.

Le lot IA est séparé. Le parcours retenu pour plus tard comporte deux validations par lot, d’abord les créations avec le coût total estimé, puis la programmation des épisodes prêts après relecture. Pour ce premier lot, garde le travail concentré sur les vidéos importées.

## État à la reprise

- Le lot groupé a été étudié et son implémentation reste à faire. Commence par `git status --short --branch` et lis le diff. Le dépôt de référence au relais est `c04be16`.
- Quatre modifications locales antérieures sont à conserver. `docs/roadmap.md` consigne la décision. `src/Service/EpisodePublications.php`, `templates/series/calendar.html.twig` et `tests/Functional/SeriesPublicationJourneyTest.php` contiennent le transfert du créneau IA vers une nouvelle préparation hors récurrence et son contrôle. Ce travail a passé 19 tests fonctionnels et 243 assertions sur trois fichiers, ainsi que le lint Twig et PHPStan ciblés. Relance les tests pertinents après tes propres changements.
- Lors du dernier contrôle, le schéma dev et test était à jour jusqu’à `Version20261107030000`. Le CSS local avait été recompilé et l’accueil répondait en HTTP 200. Si l’état a changé, diagnostique avant d’agir. Les assets générés sont ignorés par Git et un ancien cache Symfony peut servir des routes ou du Twig obsolètes.

## Parcours à livrer

1. Depuis la page privée d’une série importée, ouvre une vue **Programmer les épisodes**. Présente les épisodes actifs dans l’ordre de la série. Réutilise le préparateur individuel pour écrire ou corriger les textes, les comptes et les déclarations propres aux réseaux. Indique les épisodes à préparer et donne un accès direct à l’action utile.
2. Pour les brouillons sélectionnés, chaque épisode a sa propre date et sa propre heure. Affiche le fuseau et reprends le créneau et le fuseau existants s’il y en a. Valide les heures côté serveur avec `PublicationTime`, y compris la fenêtre de deux minutes à 90 jours et les changements d’heure. Borne la taille du lot pour limiter les validations réseau et indique la limite dans l’interface.
3. L’aperçu affiche les créneaux, les vidéos, les comptes, les textes et les réglages par destination. Explique ce qui manque pour les épisodes incomplets. La confirmation ne porte que sur le sous-ensemble prêt et affiché. Préserve les déclarations et le consentement existants, notamment pour les destinations TikTok concernées. Le bouton doit nommer l’action réelle, par exemple **Confirmer la programmation de 2 épisodes**.
4. Après confirmation, retrouve chaque programmation et son suivi depuis la série. Un épisode déjà programmé, envoyé ou archivé ne doit pas être reprogrammé par ce parcours. Les épisodes laissés de côté gardent leurs brouillons et réglages. Conserve le retour au préparateur individuel et le fonctionnement sans JavaScript.

## Points d’appui dans le code

- `src/Controller/VideoSeriesController.php`, `src/Service/VideoSeriesStudio.php` et `templates/series/videos_show.html.twig` donnent le point d’entrée, la propriété, l’ordre et les états de la série. Le rattachement durable passe par `VideoSeriesEpisode` et `PublicationDraft::getVideoEpisode()`. Utilise l’identifiant d’épisode, qui résiste au réordonnancement.
- `src/Service/EpisodePublications.php` prépare ou retrouve le brouillon lié et fournit les résumés. Ne recrée pas de brouillon générique à partir du seul ID de la vidéo, car il perdrait le lien avec l’épisode.
- `src/Service/PublicationDrafts.php` porte les validations de contenu et de comptes, la confirmation, la programmation et le dispatch à échéance. `src/Controller/PublicationDraftController.php`, `templates/draft/confirm.html.twig` et `templates/publication/_batch_review.html.twig` montrent les contrôles et le récapitulatif à réutiliser. `PublicationBatch` prépare déjà des brouillons indépendants et peut servir de référence transactionnelle. La confirmation groupée reste à construire.
- Pour garder le lot cohérent, réutilise le verrou propriétaire, la vérification de révision des brouillons et une transaction commune. Lie la confirmation à l’aperçu côté serveur avec les identités et révisions de la série et des brouillons, la sélection et les dates/fuseaux. Revérifie sous verrou la propriété, l’appartenance à la série, l’état actif, l’identité de la vidéo, l’état du brouillon et la validité des comptes, textes et créneaux. Si un épisode du sous-ensemble confirmé a changé, demande un nouvel aperçu et évite de programmer le reste silencieusement.
- La transaction doit protéger ensemble les brouillons et les messages différés. La file `publications` utilise Doctrine sur la connexion par défaut. Réutilise `DispatchScheduledPublication` et son contrôle de révision à l’échéance. Évite une boucle d’appels qui valide chaque épisode dans une transaction indépendante. Une double soumission ou un rejeu doit laisser une seule programmation par épisode.
- Garde la modification locale au parcours. Les brouillons portent déjà les dates et les messages différés. Commence avec ce schéma et les composants Twig/CSS existants. Explique au propriétaire tout écart de périmètre, migration nécessaire ou décision produit qui ne peut pas être déduite des consignes.

## Vérifications à faire avant le relais

- **Parcours nominal.** Avec un propriétaire et des médias synthétiques sur `shortflow_test`, préparer deux épisodes de la même série, choisir deux dates, vérifier l’aperçu puis confirmer. Vérifier les deux créneaux et leurs fuseaux, deux brouillons programmés et deux messages différés dans la file de test. Avant l’échéance, zéro publication sociale créée. Avec l’horloge de test et les clients simulés, vérifier le départ de chacun à son propre créneau et l’absence de doublon.
- **Protections.** Vérifier qu’un aperçu seul ne programme rien, qu’un épisode incomplet reste à préparer, et qu’un créneau expiré ou invalide est refusé. Tester l’accès par un autre propriétaire, un épisode hors série ou archivé, une confirmation forgée, une modification après aperçu et une répétition de la confirmation. Un échec au sein du sous-ensemble confirmé doit laisser ses brouillons et sa file de test cohérents.
- **Régressions.** Rejouer `SeriesPublicationJourneyTest`, `PublicationScheduleTest` et `SeriesCalendarJourneyTest` au minimum, en ajoutant un test fonctionnel ciblé pour le nouveau parcours si les tests existants ne le couvrent pas. Vérifier explicitement le nom de la base avant tout test destructif. Lancer PHPStan et le lint Twig sur les fichiers touchés. Relire le rendu desktop et mobile, y compris les erreurs et la confirmation. Si tu modifies les assets, lance `npm run check` et `npm run build` et vérifie le CSS réellement servi.
- Rends compte des fichiers modifiés, des commandes réellement exécutées, des résultats et des limites restantes. Donne le chemin dans l’app pour que le propriétaire puisse relire le parcours.

## Autorisation et données

Le « go » autorise l’implémentation locale de ce lot et sa recette simulée. Les tests utilisent des données synthétiques et des clients fournisseur simulés. Une génération réelle, un coût, un upload fournisseur, une publication réelle, un changement de compte connecté, une modification de données du propriétaire, un push ou un déploiement demandent une autorisation distincte. Ne lis, n’affiche et n’exporte aucun secret réel. Vérifie l’inactivité avant toute opération sur les workers existants et privilégie un banc isolé.
