# Séries avec les vidéos de la bibliothèque — 7 novembre 2026

Lot demandé après validation de la création IA simplifiée : proposer « Générer avec l’IA » ou « Utiliser mes vidéos », puis nommer, sélectionner/importer et ordonner les épisodes. La refonte du tableau des épisodes IA, la récurrence et une programmation groupée restent des étapes ultérieures.

## Parcours livré

Le bouton Créer une série ouvre deux choix. Le parcours IA existant est conservé. Le parcours vidéos permet de nommer la série, rechercher ses vidéos privées, les cocher puis déplacer ou retirer les épisodes. Une vidéo devient un épisode ; retirer un épisode ne supprime pas la vidéo source. Les séries IA et vidéo apparaissent dans la même liste paginée, avec leur type et un lien approprié.

L’import est accessible dès la sélection et reste sur la page avec JavaScript : il utilise le formulaire, le contrôle du vrai format, la limite 100 Mo/3 minutes, le quota et le stockage privés existants. Un succès ajoute la vidéo à la bibliothèque et à la sélection sans perdre le nom ou l’ordre en cours. Un échec affiche un message et conserve la sélection. Le fichier importé reste dans la bibliothèque même si l’utilisateur quitte sans enregistrer la série. Sans JavaScript, l’import classique revient au formulaire de série avec la vidéo sélectionnée ; la création par cases fonctionne et les actions d’ordre nécessitent JavaScript.

La série enregistrée affiche les épisodes dans l’ordre avec Voir la vidéo et Préparer la publication vers le préparateur existant. Ces liens ne créent ni ne programment une publication automatiquement. Supprimer une vidéo dans la bibliothèque retire son association aux épisodes ; une série devenue vide reste accessible et invite à ajouter de nouvelles vidéos.

## Comprendre les composants Symfony

- `VideoSeries` et `VideoSeriesEpisode` sont les entités Doctrine : une série appartient à un utilisateur, et chaque épisode relie une vidéo à une position. Elles sont séparées des entités IA pour ne pas inventer de modèle, de prompt ou de génération.
- `VideoSeriesType` décrit le formulaire et ses contraintes. La liste des choix est reconstruite à chaque requête avec les seules vidéos du propriétaire, y compris après un nouvel import. CSRF, nom, taille de sélection et valeurs possibles sont contrôlés côté serveur.
- `VideoSeriesController` reçoit les requêtes et choisit le template ou la redirection. `VideoSeriesStudio` réalise la sauvegarde transactionnelle, contrôle les vidéos, l’ordre exact et la révision, sous verrou propriétaire/série. Un ancien onglet ne peut pas écraser une version plus récente.
- `SeriesLibrary` réunit les deux types dans une pagination commune de 12 éléments, en filtrant le propriétaire dans chaque branche. `AiSeriesController::index` utilise cette liste ; les réglages et le moteur IA restent inchangés.
- `series_videos_controller.js` organise l’affichage, l’ordre et l’import progressif ; il ne remplace jamais la validation PHP. Les templates `choose`, `videos_configure` et `videos_show` donnent les trois écrans. Le shell conserve Séries comme section active.
- `VideoController::upload` réutilise `VideoLibrary`. Il ne renvoie les métadonnées du nouveau fichier que dans le contexte Séries explicite ; le contrat JSON historique reste inchangé ailleurs. Un contexte de série existante est vérifié avant import.

Flux : requête → sécurité utilisateur vérifié → formulaire/CSRF → service transactionnel → entités persistées → redirection → affichage ordonné. En Symfony, le formulaire valide la saisie, le service porte les règles métier et Doctrine enregistre les relations ; JavaScript sert à rendre ce parcours plus pratique.

## Vérification et état local

- Nouveau banc : 5 fonctionnels / 71 assertions verts ; relecture finale du parcours complet et navigation : 1 / 26 chevauchant. Contrôles de propriété, ordre falsifié, CSRF, saisies conservées, révision obsolète, import synthétique réel en stockage test, pagination mixte et suppression d’une source.
- Régressions import : 3 / 28 ; séries IA : 2 / 169 verts. Pas de suite PHP globale ni de revue indépendante.
- 210 frontend verts ; `npm run check` inclut le nouveau contrôleur, build vert avec l’avertissement préexistant de gros chunk. PHPStan huit applicatifs, style dix PHP, 18 Twig et conteneur verts. Schémas test/dev synchronisés.
- Chrome : quatre parcours interactifs 320/390/768/1280 (choix, ordre, retrait, recherche, Entrée sans soumission, succès/échec d’import, nom conservé, contenu réellement soumis vérifié) et huit vues choix/liste d’épisodes aux mêmes largeurs. Aucun débordement ni erreur JS inattendue. La réponse HTTP422 simulée produit le message réseau attendu. Requêtes interceptées ; captures mobile et ordinateur relues.
- Premiers échecs du banc corrigés : ordre des cases DomCrawler, sélecteurs trop larges, objet détaché dans le test de suppression, ancien libellé d’état vide et noms d’index Doctrine. Les échecs intermédiaires navigateur provenaient du traitement attendu de HTTP422 et de l’intercepteur POST `/qa` ; corrigés puis rejoués.

Migration additive `Version20261106010000` appliquée en test puis dev après simulation et garde inactive. Deux tables vides ajoutées, 27 anciens compteurs inchangés hors métadonnées de migration ; 29 tables finales. Cache dev actualisé ; huit GET authentifiés HTTP200 en transaction de lecture seule, 29 compteurs égaux, clients fournisseurs bloqués et zéro appel fournisseur. Aucun worker redémarré, aucune série/vidéo du propriétaire créée ou modifiée, aucun coût/génération/upload fournisseur/clé réelle lue/publication/push/déploiement.

## Essai propriétaire

Séries → Créer une série → Utiliser mes vidéos. Donne un nom, coche deux vidéos, inverse leur ordre et enregistre. Rouvre Ajouter ou organiser les épisodes pour vérifier la conservation ; teste un import si tu le souhaites. La validation de l’interface par le propriétaire reste attendue avant le prochain lot. R7 reste ouvert.
