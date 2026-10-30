# R4.2 — séries paginées et historique conservé

30 octobre 2026. **R4.2 terminé** ; grand R4 reste ouvert. Prochaine tâche : **R4.3**, traiter les autres lenteurs démontrées. Revue globale R4.5 encore à faire.

## Comportement et conservation

Une série affiche **12 épisodes par page**, dans l'ordre de leur numéro, avec total complet, liens précédents/suivants et accès aux derniers épisodes. L'ajout d'épisodes conduit à la dernière page. Actualiser conserve la page courante. Les nombres invalides reviennent à la première page, les pages positives trop grandes sont ramenées à la dernière.

L'**historique des créneaux** reste disponible dans un volet replié, **12 lignes par page**, du plus récent au plus ancien. Ses liens conservent la page d'épisodes ; les liens d'épisodes conservent la page d'historique quand elle est ouverte explicitement. Une navigation ordinaire entre épisodes n'ouvre pas ce volet sans raison. Les trois derniers créneaux sans épisode gardent leur aide de reprise ; les plus anciens restent consultables dans l'historique complet.

La date, les comptes enregistrés, le statut et la publication de chaque épisode visible restent présents. Les couvertures et résumés de publication sont projetés pour les seuls épisodes affichés, en gardant les projections groupées existantes. Aucun épisode, occurrence, média, brouillon, texte, compte ou consentement n'est supprimé par la pagination.

Les liens de retour depuis le Studio, les plans et une ancienne génération précisent `episode=ID`. Le serveur ouvre la page de cet épisode, uniquement s'il appartient à la série déjà autorisée. Les liens venant d'un calendrier ou d'un brouillon ouvrent toujours directement l'épisode ; son retour rejoint ensuite la bonne page de série.

Pour les anciens favoris contenant seulement `#episode-ID`, le contrôleur de navigation existant ajoute le paramètre sur la même URL si la cible n'est pas rendue. Il ne le fait qu'une fois, et **jamais sur une réponse contenant une saisie refusée**. Sans JS, les nouveaux liens et les deux paginations fonctionnent nativement ; une ancienne ancre seule ne peut pas être lue par PHP et nécessite alors de choisir la page manuellement.

## Formulaires et protections

Le GET ne construit plus les formulaires d'édition d'épisodes qui n'étaient pas affichés. Les formulaires utiles de récurrence et d'ajout restent disponibles. Une ancienne soumission `episode_ID` conserve sa compatibilité : un seul identifiant de formulaire accepté, épisode recherché dans la série autorisée, contrôles CSRF, révision, état et verrou métier conservés. Une préparation répétée retrouve le résultat déjà préparé sans nouveau lancement.

Un refus affiche **un seul formulaire de récupération**, sur la page du bon épisode, avec les textes échappés, les erreurs et l'alerte de départ active. Le message de session est en français. Le refus d'une activation de récurrence conserve aussi ses valeurs et ses paginations. Aucune redirection d'ancienne ancre ne peut remplacer ces réponses par un GET qui ferait perdre la saisie.

## Fichiers, flux et notions Symfony

- `AiSeriesStudio::episodePage()` compte les épisodes, trouve éventuellement la position d'un épisode ciblé dans cette série, puis applique `setFirstResult` et `setMaxResults(12)` à la requête avec sa génération. Le tri numéro/identifiant est déterministe ; l'unicité numéro/série rend le calcul de position cohérent.
- `SeriesRecurrences::historyPage()` compte et charge au plus douze occurrences avec leur épisode associé. Il ne charge pas tout l'historique avant de le découper.
- `AiSeriesController::show()` autorise d'abord la série, choisit sa page, charge seulement les occurrences des douze épisodes visibles, l'historique paginé et trois créneaux sans épisode. Les anciennes éditions construisent un formulaire uniquement sur POST ciblé. Le booléen de réponse refusée fournit la garde de navigation.
- `series/show.html.twig`, `_episode_pagination.html.twig` et `_occurrence_history.html.twig` rendent les deux navigations et l'éventuelle récupération. Les templates Studio/plans/génération enrichissent leurs liens de retour.
- `studio_navigation_controller.js` conserve les anciennes ancres, avec garde anti-boucle et garde de saisie refusée ; `series.css` adapte les liens au mobile sans modifier les autres écrans.

En Symfony/Doctrine, borner la requête **avant hydratation** évite de créer tous les objets pour n'en montrer que douze. Un `COUNT` reste une requête SQL et peut parcourir des lignes dans PostgreSQL, mais ne crée pas tous les objets PHP. Les fragments après `#` ne sont pas transmis au serveur ; le paramètre de requête est nécessaire pour sélectionner la page. Le marqueur de refus protège l'HTML contenant les données humaines avant que Stimulus envisage une navigation.

## Comparaison reproductible avec R4.1

Le programme `scripts/benchmark-studio.php` est **inchangé**. Deux nouvelles campagnes utilisent exactement les mêmes volumes, préchauffages, trois échantillons par route, environnement test sans debug et copie Linux que [R4.1](r4-mesures-performance.md). Application mesurée : commit `195ff4e777a83bc06a2199e7af8d8f86f5e25cb7`. Programme, dix empreintes de sources/dépendances et comptages de nettoyage vérifiés ; aucune suite DB concurrente.

[Résultats bruts après pagination](fixtures/r4-pagination-20261030.json), à comparer aux [résultats R4.1](fixtures/r4-baseline-20261030.json). Médianes serveur en ms, sans analyse DOM ; chaque minimum/maximum reste dans le JSON.

| Détail de série | Avant A / B | Après A / B |
| --- | ---: | ---: |
| Petit jeu, 3 épisodes : serveur | 10,33 / 9,99 ms | 13,94 / 13,32 ms |
| Grand jeu, 120 épisodes : serveur | 36,27 / 31,69 ms | 14,68 / 14,63 ms |
| SQL petit / grand | 13 / 13 | 17 / 17 |
| HTML grand jeu, passage B | 151 006 octets | 55 172 octets |
| Pic PHP au-dessus du début, grand jeu, premier échantillon A | 5 023 736 octets | 978 608 octets |

Le petit jeu supporte le coût supplémentaire des comptages et de l'historique paginé. Sur le grand jeu, la première page contient maintenant 12 épisodes et 12 occurrences d'historique ; elle ne renvoie plus les 120 épisodes et ne construit plus quarante formulaires inutilisés. L'accès aux autres pages reste disponible. Ce changement de quantité rendue explique le gain : aucun rendu complet de 120 épisodes n'est prétendu plus rapide à quantité constante.

Les quatre formulaires HTML utiles du détail de série restent quatre. Les autres pages du banc sont aussi conservées dans les résultats ; aucune conclusion de capacité ou promesse de délai n'en est déduite. La mémoire est celle du processus PHP persistant, pas un RSS de production. L'attente Doctrine et les réponses de rédaction restent contrôlées/simulées ; aucune mesure d'inférence réelle nouvelle. Les onze tables retrouvent leurs comptages avant/après rollback et les HTTP interdits restent à zéro ; les séquences PostgreSQL consommées restent la limite documentée du banc.

## Vérifications et revue

`SeriesPaginationTest` couvre 0/12/13/25 épisodes, pages négatives/non numériques/tableaux/énormes, limite trop grande, ordre et cible hors page, autre série du même propriétaire et autre propriétaire, indépendance des deux navigations, retour vers un ancien épisode, refus de récurrence sur page 2, récupération d'un ancien POST hors page, CSRF, échappement, rejet des soumissions multiples et préparation répétée sans nouvel appel. Les tests frontend couvrent les anciennes ancres, la cible existante, l'anti-boucle et les deux formes de refus.

Premier contrôle de style : deux mises en forme corrigées, puis ciblage 7 / 72 avec une erreur de fixture CSRF hors RequestStack. Le token est désormais repris depuis le vrai formulaire de récupération ; **7 / 93 verts**. Première suite complète : **811 / 9 320 verts**. Renfort de tests : passage **811 / 9 322**, erreur de propriétaire détaché dans la construction de la seconde série ; référence managée rechargée, sans changer de garde métier. Passage suivant **811 / 9 327 vert** avant le dernier marqueur de présentation ; ciblage après ce marqueur **7 / 101 verts**.

La revue indépendante a relevé deux manifestations du même risque : la reprise automatique d'une ancienne ancre pouvait détourner une réponse refusée d'édition puis de récurrence. Le marqueur serveur générique interdit désormais ce déplacement ; tests ajoutés. Après relecture, aucun autre blocage identifié dans le périmètre R4.2. Le relecteur est resté en lecture seule, sans exécuter de suite DB.

Recette : douze GET Symfony authentifiés et un POST historique refusé exportés avec 25 épisodes/occurrences synthétiques, puis servis en boucle locale avec les assets construits. Navigation clavier entre pages, dernière page à un épisode, historique indépendant et retour à l'épisode 1 vérifiés. Ancien favori à ancre seule vers l'épisode 25 repris automatiquement, sans boucle ; erreur de session en français et texte récupéré lisible. Desktop et mobile 375 × 812 sans débordement horizontal, focus sur titres de pages, aucune erreur console relevée. Aucun POST fournisseur/social/activation accepté par le serveur de recette. Captures locales : `artifacts/r42/pagination-desktop.png` et `pagination-mobile.png`. La recette ne certifie pas les comptes réels ni la qualité audiovisuelle.

## Essai manuel et suite

Ouvrir une série de plus de douze épisodes, aller aux suivants puis aux derniers. Ouvrir un épisode, revenir à la série : sa carte doit être sur la bonne page. Dans l'historique des créneaux, consulter les plus anciens puis retrouver l'épisode associé. Aucun lancement vidéo ou activation réelle n'est nécessaire pour ces vérifications.

R4.3 reste la prochaine tâche ; R4.4 et la revue globale R4.5 ne sont pas déclarés terminés par ces tests ciblés. Pas de migration applicative, coût, inférence réelle, publication, activation réelle ni push.

Contrôles finaux sur les sources applicatives du commit `195ff4e` : **811 tests PHP / 9 328 assertions**, **182 frontend**, schémas dev/test, lints YAML/Twig/conteneur, PHPStan/style, `npm run check` et `npm run build` verts. Commande complète `php scripts/check.php` dans un edit-worker temporaire test avec copie Linux et vérification du Kernel ; migrations test avant PHPUnit, aucune suite DB concurrente. Avertissement Vite Three.js connu, aucune exclusion de test. La dernière recette vérifie aussi `#episode-1` sur une réponse de récupération : URL inchangée, marqueur de navigation bloquée vrai et texte humain toujours affiché, console sans erreur.

Cache dev régénéré et quatre workers relancés après garde à zéro traitement actif, fonctionnement vérifié. Serveur et onglets de recette fermés, viewport restauré. Commit fonctionnel local `195ff4e feat: paginate series and preserve episode navigation`, suivi du bilan/mesures dans `docs: record bounded series performance and validation`. Aucun push.
