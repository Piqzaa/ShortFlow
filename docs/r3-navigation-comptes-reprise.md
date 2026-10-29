# R3.6 — Navigation, comptes et reprise

Le 29 octobre 2026, le propriétaire demande le prochain point après les correctifs plan unique/raccords. R3.6 conserve le parcours commun et corrige ses repères. R3.7 reste la revue globale de R3, avant tout R4.

## Ce qui change

Une préparation d’épisode ouverte dans le Studio garde la section Séries active dans le menu desktop, le menu mobile et la barre inférieure. Une création autonome garde Studio. Le correctif s’applique à la sous-requête sans route créée par `forward()` ; les autres routes gardent leur navigation habituelle. Le retour vers la série, les valeurs et le CSRF restent ceux du parcours existant.

Le libellé résiduel « Subjects » est retiré. Les enfants des collections de références sont déjà rendus dans leurs cartes ; leurs parents sont maintenant marqués comme rendus pour empêcher `form_end()` de les réafficher. Les erreurs agrégées et de champs restent visibles. Un test de référence invalide a révélé une erreur 500 lors de la construction de l’URL d’aperçu : le rendu retire désormais uniquement cette URL si le nom est invalide, tout en conservant la valeur et son erreur de validation.

Chaque destination de publication affiche le réseau, l’état et le compte enregistré dans ce brouillon. La résolution du nom se limite aux connexions du propriétaire et à l’identité conservée dans le brouillon. Un compte retiré est signalé comme indisponible ; il n’est pas remplacé par le compte aujourd’hui disponible. Si une ancienne préparation n’a pas enregistré d’identité, l’interface le dit. Le nom affiché est celui de la connexion correspondante aujourd’hui, pas une archive du nom historique. Les occurrences sans brouillon utilisent leur propre instantané de destinations et comptes, jamais les réglages actuels de la série.

Quand la rédaction automatique n’est pas activée dans cette installation, le bouton l’indique dès l’affichage. La rédaction manuelle et la récupération d’une demande existante restent possibles. L’état est une configuration, pas un contrôle de santé : le GET ne sonde pas le modèle et ne lance aucune rédaction. La garde serveur conserve son refus de nouvelle demande lorsque la fonction est désactivée. Le bouton de récurrence reflète aussi cette indisponibilité, sans bloquer l’ajout manuel d’épisodes ni la pause d’une préparation existante.

Les créneaux sans épisode disposent de liens vers l’ajout manuel et les futurs réglages. Ces liens ne relancent pas le créneau. L’interface rappelle que l’installation et le rédacteur doivent être disponibles avant la date visée et qu’un créneau manqué n’est pas rattrapé automatiquement. Aucune règle de programmation, compteur ou autorisation n’est modifiée.

## Fichiers et flux

- `templates/studio/shell.html.twig` rétablit le contexte d’un épisode lorsque la sous-requête n’a pas de nom de route.
- `AiStudioController` et `AiSeriesController` passent l’activation du rédacteur à Twig. `story_writer_controller.js` garde le bouton désactivé après une restauration, sans empêcher la consultation des résultats existants.
- `_subjects.html.twig` conserve les cartes et erreurs, puis marque les collections parentes rendues. `_reference_picker.html.twig` distingue la valeur refusée de son aperçu affichable.
- `EpisodePublications::summaries()` ajoute le compte à la projection existante, depuis `draft.accounts`. Les choix du propriétaire sont chargés une fois pour le lot et uniquement quand il existe des brouillons ; aucune requête par épisode ajoutée. La série réutilise ses choix déjà chargés pour nommer `occurrence.accounts`.
- `series/show.html.twig`, `_publications.html.twig` et `_recurrence.html.twig` rendent ces informations et les actions de reprise, sans nouvelle route de relance.

En Symfony, `forward()` appelle un contrôleur dans une sous-requête : il peut donc manquer le nom de route consulté par le menu. `FormView::setRendered()` agit sur le rendu HTML, pas sur le champ ni sa validation. Une projection de compte doit partir de l’identité enregistrée avec la préparation ; choisir le compte par défaut courant changerait le sens de l’historique.

## Contrôles

Tests fonctionnels : navigation après GET et POST refusé, préparation autonome, collections de références vides/non vides et invalides, compte figé puis retiré avec un autre compte disponible et une identité appartenant aussi à un autre propriétaire, occurrence avant brouillon, rédacteur désactivé et créneau manqué sans relance. Les appels externes sont refusés ou simulés ; les tests DB utilisent uniquement `shortflow_test`, migrations vérifiées, sans suite concurrente.

Premiers contrôles : 9 tests / 72 assertions, une erreur de remplacement tardif d’un service de fixture et une assertion comptant le prototype en plus des huit champs. Fixture désactivée déplacée avant initialisation, prototype exclu sans changer le nombre attendu. Rejeu : 8 / 79, une erreur applicative 500 de référence invalide corrigée, puis **1 / 10** vert. Première suite complète : 802 / 9 115, erreur d’initialisation tardive du mock de l’ancien test de proposition ; mock désormais installé avant le GET. Rejeu final des deux cas : **2 tests / 109 assertions verts**, attentes métier inchangées.

Résultats finaux : **802 tests PHP / 9 207 assertions**, **177 frontend**, schémas dev/test, YAML/Twig/conteneur, PHPStan/style/syntaxe et build verts. Avertissement Vite Three.js connu. Aucune exclusion de test. Commandes : `npm run test:ui`, `npm run check`, `npm run build`, `php scripts/check.php` dans un conteneur edit-worker temporaire avec APP_ENV=test/APP_DEBUG=0. Copie Linux des sources/vendor, Kernel chargé vérifié, caches et variables locales exclus.

## Recette et limites

Pour vérifier : ouvrir un épisode de série et provoquer une erreur de formulaire ; Séries doit rester actif. Ouvrir ensuite une création autonome : Studio reste actif. Dans une série avec une publication préparée, vérifier le compte à côté du réseau et de l’état. Sur un créneau manqué, utiliser les liens pour atteindre l’ajout manuel ou les futurs réglages, sans lancer de génération ni activer de récurrence pour cette recette.

Recette synthétique : vrais GET Symfony authentifiés exportés avec un propriétaire, deux comptes et épisodes, un brouillon et un créneau manqué de test. Rédaction désactivée pour vérifier son état sans HTTP. Pages servies sur boucle locale avec les assets construits et un petit média FFmpeg synthétique, sans POST réel. Navigation Séries sur trois surfaces, absence de Subjects, bouton de proposition restant désactivé après restauration, identité enregistrée visible, liens clavier vers `add-episodes` et `recurrence-settings` avec focus et volet ouvert. Largeur 375 px sans débordement horizontal et aucune erreur console relevée. Les erreurs/sauvegardes et le contexte autonome sont couverts par les tests fonctionnels. Aucun modèle ni service distant sollicité.

Revue indépendante finale : aucun blocage applicatif restant identifié ; les anciennes attentes réseau/statut restent satisfaites, le compte étant ajouté après le statut. La revue a fait compléter les cas de références invalides et de disponibilité de l’installation. Captures de recette locales dans `artifacts/r3-light/r36-*.png`.

Aucune génération IA réelle, dépense, publication, activation, migration applicative ou push. Les tests ne certifient ni un compte réel ni la disponibilité d’un modèle local. Prochain point après validation : **R3.7**, recette et revue globale du grand R3.

Cache dev régénéré et quatre workers relancés après vérification de zéro traitement actif ; services en fonctionnement vérifiés. Commit local : `fix: preserve series context and clarify recovery`. Grand R3 reste ouvert jusqu'à la revue globale R3.7.
