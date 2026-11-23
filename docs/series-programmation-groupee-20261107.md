# Programmation groupée des publications d'une série de vidéos importées — 7 novembre 2026

## Recette navigateur du 23 novembre 2026

Neuf états Twig rendus par les tests ont été ouverts dans Chrome à 320, 390 et 1280 px, puis à 390 px sans JavaScript. Création, ajout par import, préparateur, sélection du lot, récapitulatif, épisode exclu, créneau expiré, suivi après confirmation et suppression. Les 36 vues passent sans défilement horizontal ni exception JavaScript. L'ouverture du formulaire d'import et la sélection clavier fonctionnent. Les captures du récapitulatif à 390 et 1280 px ont été relues, avec deux dates et fuseaux, le compte et la confirmation visibles.

Le navigateur a chargé les pages de test et les assets locaux. Les médias et les destinations externes étaient simulés. La lecture des vidéos et les accès sociaux restent à vérifier dans le parcours pilote. Les formulaires ont été exercés côté serveur sur `shortflow_test` et le navigateur n'a émis aucun POST.

## Parcours actuel depuis la page de série

Les textes, les destinations et l’heure se règlent une seule fois dans le préparateur individuel. La série affiche l’état, le créneau et les destinations de chaque épisode. À partir de deux brouillons datés avec comptes choisis, le raccourci groupé apparaît sur la page de série. Il accepte de deux à vingt épisodes et vérifie leur préparation avant d’afficher le récapitulatif. Les anciens liens vers `/programmer` reviennent à la série.

La confirmation utilise les révisions des brouillons relus et leurs créneaux enregistrés. Aucun champ de date n’est transmis par le formulaire groupé. Une révision ou un créneau invalide fait refuser tout le sous-ensemble confirmé. Les messages différés restent dans la même transaction que les programmations.

**Supprimer la série** demande une confirmation et conserve les médias, les brouillons et l’historique. La vérification sous verrou couvre aussi les épisodes archivés. Elle bloque une programmation en attente et les envois actifs ou à reprendre. Le retrait des liens d’épisode repose sur les contraintes existantes de la base.

Les sections ci-dessous documentent la première livraison et ses vérifications.

## Parcours livré

Page privée **Séries → page d'une série importée → « Programmer les épisodes ↗ »**
(`GET /studio/series/videos/{id}/programmer`, entrée ajoutée dans l'en-tête de la page de série
`templates/series/videos_show.html.twig`).

- Une seule ligne par épisode actif dans l'ordre de la série (1, 2, … dans l'ordre de `VideoSeriesEpisode.position`), miniature de la vidéo, nombre de plans durées implicites, état courant : brouillon à planifier (formulaire complet), programmé (départ prévu + lien vers le suivi), envoi demandé/bloqué/manqué (« À surveiller »), historique conservé, sans brouillon (lien direct vers « Compléter les textes et les réglages ↗ » = préparateur individuel existant).
- Chaque épisode à planifier a sa case + sa date/heure `datetime-local` (reprise du créneau et du fuseau stockés, sinon Europe/Paris), son fuseau sélecteur complet avec Europe/Paris, UTC et America/Toronto en favoris. La note rappelle la fenêtre « deux minutes à 90 jours dans le futur » et la conservation du fuseau par épisode.
- Zone « Un créneau par épisode. » explicite : limites, récapitulatif d'abord, confirmation ensuite, le lot ne porte que sur les épisodes choisis.

## Aperçu (aucune écriture)

Bouton **« Vérifier l'aperçu ↗ »** : activité de lecture seule. Pour chaque épisode coché :

- créneau résolu localement (mois DST réel), résumé des vidéos, comptes, textes et réglages par destination réutilisé du récapitulatif existant (`publication/_batch_review.html.twig`) ;
- sous-ensemble prêt annoncé (« 2 épisodes prêts », « 1 épisode prêt ») avec bouton nommé **« Confirmer la programmation de N épisode(s) ↗ »** ;
- épisodes non confirmables en liste « Ces épisodes ne passent pas à la confirmation » (créneau invalide/expiré/moins de 2 minutes : « Choisis un créneau entre deux minutes et 90 jours dans le futur. » ; préparation incomplète : « La préparation reste incomplète : vérifie les comptes, les textes et les réglages. »).

L’aperçu ne modifie aucun brouillon ni message. Le dernier aperçu prêt est conservé en session avec un jeton et les épisodes prêts. La confirmation exige ce jeton, la case cochée et la sélection relue avec ses révisions, dates et fuseaux. Une nouvelle vérification est requise après une modification. Le formulaire fonctionne sans JavaScript.

## Confirmation (une transaction verrouillée)

`VideoSeriesSchedule::confirm()` : limite 20 épisodes, verrou propriétaire `pg_try_advisory_lock` + `SELECT FOR UPDATE` sur la série, chaque épisode et brouillon re-vérifiés sous verrou (série : révision, propriété ; épisode actif et vidéo ; brouillon : identité vidéo, révision, état) puis `PublicationDrafts::schedule()` (résolution, `plan()`, `arm()`, révision +2) pour le sous-ensemble prêt, en une seule `transactional()` commune aux brouillons et aux messages différés Messenger (`DispatchScheduledPublication` existant avec contrôle de révision à l'échéance, délai toujours ≥ 2 minutes ⇒ jamais instantané).

- La confirmation consomme le jeton. Un rejeu ne peut pas programmer les épisodes une seconde fois.
- Un épisode déjà exclu à l’aperçu conserve son brouillon. Si un épisode du sous-ensemble prêt devient incomplet, change ou expire avant confirmation, le sous-ensemble entier reste en brouillon.
- Après succès, le retour à la série indique le nombre d’épisodes programmés et pointe vers le premier épisode concerné.
- Seuls les brouillons éditables ont des champs de programmation. Un épisode sans brouillon renvoie vers son action de préparation sur la page de série. Les numéros affichés commencent à 1.

## Fichiers

- `src/Service/VideoSeriesSchedule.php` (nouveau) : `LIMIT=20`, `rows()`, `preview()` lecture seule, `confirm()` transactionnelle.
- `src/Service/PublicationDrafts.php` (retouches connexes héritées du relais) : `checkComplete()` public, `resolved()` validation seule, `schedule()` = résolution + `plan()` + `arm()`.
- `src/Form/VideoSeriesScheduleType.php`, `src/Form/VideoSeriesScheduleRowType.php` (nouveaux) : révision de série `Assert\EqualTo` + rangées.
- `src/Controller/VideoSeriesController.php` : route `app_video_series_schedule` GET+POST (intents `preview`/`confirm`, CSRF `video_series_schedule_{id}`, limiteur d'envoi existant `limiter.publication`, 422 sur erreur, flashs/redirects).
- `templates/series/videos_schedule.html.twig` (nouveau), `templates/series/videos_show.html.twig` (entrée).

## Vérifications exécutées

Après correction de la revue, le passage combiné `SeriesScheduleBatchTest`, `SeriesPublicationJourneyTest`, `PublicationScheduleTest` et `SeriesCalendarJourneyTest` est vert sur `shortflow_test` avec **35 tests et 571 assertions**. Les tests couvrent l’absence de consentement, le contournement de l’aperçu, la modification du créneau après aperçu, l’expiration dans un lot, le formulaire d’une série mixte et la numérotation. Twig, PHPStan ciblé et le formatage PHP sont verts. Le cache web local a été actualisé et l’accueil répond en HTTP 200. Les vérifications ci-dessous décrivent la livraison initiale.

- **Recette nouvelle** `tests/Functional/SeriesScheduleBatchTest.php` : **4 tests / 135 assertions verts** (nominal deux créneaux deux fuseaux deux messages différés / rejeu refusé ; épisode incomplet exclu, sous-ensemble armé ; brouillon ou série modifiés entre aperçu et confirmation ⇒ refus global ; garde-fous : >20, vide, autre propriétaire 404, brouillon étranger, créneau expiré refusé avec zéro modification store). Artifacts HTML de relecture dans `artifacts/series-schedule-*.html` (batch, preview, preview-issues, scheduled, guardrails).
- **Régressions** demandées par le relais : `SeriesPublicationJourneyTest` conservé et relancé ; passage combiné 1 : `SeriesPublicationJourneyTest` + `PublicationScheduleTest` + `SeriesScheduleBatchTest` + `SeriesPaginationTest` + `VideoSeriesTest` → **43 tests / 776 assertions, 1 échec préexistant** (voir découverte ci-dessous) ; passage combiné 2 : `PublicationScheduleTest` + `SeriesCalendarJourneyTest` + `SeriesRecurrencesTest` + `PublicationBatchTest` → **OK 49 tests / 588 assertions**.
- **Qualité** : `php -l` (fichiers touchés), PHPStan niveau 5 sur 6 fichiers applicatifs verts, `lint:twig` 2 fichiers, `lint:container` vert. Aucun changement JS/CSS ⇒ `npm run check`/`build` non relancés.
- **Base** : aucune migration (schéma inchangé au relais `Version20261107030000`). Cache `dev` vidé et recalculé ; accueil `GET /` HTTP 200 sur le port local 8000. File Messenger vide et aucun traitement actif ⇒ les quatre workers alors actifs ont été redémarrés après la garde d'inactivité (protocole habituel).
- Zéro appel HTTP fournisseur : clients WebSocket bloqués dans le banc fonctionnel, tout échange simulé.

## Découvert en cours de travail (hors périmètre du lot)

`SeriesPaginationTest::testLongHistoryKeepsOldAndUnassignedSlotsWithoutMutatingStoredWork` (test 505 épisodes/505 créneaux) échoue **déjà sur HEAD `c04be16` sans mes changements** : la page d'erreur 404 de débogage (`APP_DEBUG=1` forcé par `phpunit.dist.xml`) embarque les extraits de code source du test lui-même, donc le littéral « Foreign prompt secret » du test apparaît dans la réponse, ce que le test interdit. Reproduction isolée rejouée et confirmée via `git stash` ⇒ défaillance préexistante, non liée au lot ; laissée en l'état (après notification) pour décision distincte. L'aval du lot maintenu composé d'un usage runtime ne modifie pas cette page d'erreur de débogage.

## Limites restées ouvertes

- Aucun outil navigateur disponible dans cette session : pas de recette Chrome interactive desktop/mobile, relecture rendue possible par les artifacts HTML + tests fonctionnels réels (les styles CSS fournis par les includes existants sont testés par les pages de recette avec un vrai DOM).
- un déclenchement réel à l'échéance est celui du moteur existant non modifié (couvert par `PublicationScheduleTest`), emporté pour preuve complémentaire.
- Le lien « Programmer les épisodes » est présent côté propriétaire uniquement ; la page liste les épisodes actifs uniquement.
