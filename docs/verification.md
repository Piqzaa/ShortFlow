# Vérification — 17 octobre 2026, actualisation des statistiques à une minute

- À la demande du propriétaire, délai par publication réduit de cinq minutes à 60 secondes. Constante commune PublicationStatistic::REQUEST_INTERVAL_SECONDS utilisée par la validation métier et le bouton de la liste ; messages Twig, flash et commande locale actualisés. Documentation statistics mise à jour. Les verrous et protections contre les demandes répétées sont conservés.
- Test fonctionnel ajouté : demande refusée après 30 secondes et bouton désactivé, demande acceptée après 61 secondes et bouton actif ; HTTP simulé uniquement. Migrations shortflow_test à jour avant PHPUnit. PublicationStatisticsTest : **14 tests / 118 assertions réussis**, 1 min 24,630 s. Les 403/404 sont attendus. Lint 35 Twig, conteneur et PHPStan niveau 5 : codes 0 ; CS-Fixer appliqué. Aucun changement CSS/JS : build et navigateur non répétés ; suite globale non répétée pour ce réglage. Aucun nouvel appel social réel.

# Vérification — 17 octobre 2026, lecture YouTube réelle et suppression multiple

- Lecture réelle YouTube explicitement autorisée par le propriétaire. Activation séparée par réseau via PublicationStatisticsAccess : valeurs par défaut à 0, tests simulés à 1, configuration privée locale YouTube=1 / Instagram=0 / TikTok=0. Le contrôleur, la demande et le worker appliquent le même réglage. Test ciblé PublicationStatisticsTest|StudioOverviewTest : **17 tests / 173 assertions réussis**, 1 min 36,996 s, exclusivement shortflow_test migrée. Le scénario ajouté vérifie boutons et refus de mise en file/lecture des deux réseaux inactifs.
- Une connexion et une publication YouTube existantes détectées, sans sortie de jetons ni de données de compte. Premier relevé demandé par la commande locale app:youtube:statistics:refresh, qui réutilise le service et Messenger. Le worker conservait un cache dev sans debug antérieur au handler : NoHandlerForMessageException et nouvelles tentatives. Reconstruction avec cache:clear --no-debug, redémarrage du worker et vérification debug:messenger ; la tentative suivante réussit. Relevé enregistré à 14:12:14 UTC (16:12:14 Europe/Paris) : **0 vue, 0 j’aime, 0 commentaire**, état ready et file vide. Aucune nouvelle vidéo publiée. Essai navigateur réel limité à la page de connexion faute de session dans l’onglet de contrôle ; aucune session fabriquée ou authentification supplémentaire.
- Suppression multiple : sélection de 1 à 20 vidéos, GET de confirmation listant les titres, POST avec CSRF lié aux identifiants exacts et contrôle propriétaire. Une transaction commune vérifie tous les envois actifs avant de déplacer les fichiers, restaure les fichiers déplacés sur erreur et purge après validation. Suppression individuelle réutilise le même service. Aucun fichier dev supprimé. Deux premiers scénarios ciblés : **2 tests / 35 assertions réussis**, 12,898 s ; scénario supplémentaire de restauration après erreur de stockage ajouté à la suite globale.
- npm run check, npm run test:ui (**5 tests réussis**) et build Vite : codes 0, avertissements habituels sur médias statiques/chunk Three.js. La restauration de sélection contrôle les deux boutons. QA sur vrais templates avec objets synthétiques sans écriture DB : boutons désactivés sans sélection, sélection globale active les deux actions, GET mène à la confirmation de deux titres, annulation retourne à la bibliothèque. Desktop 1 440 px et mobile 360 px ; aucun débordement, console sans erreur/avertissement. La confirmation définitive n’a pas été soumise dans le navigateur. Captures ignorées artifacts/library-bulk-delete-confirm.png et library-bulk-selection-mobile.png. Serveur temporaire arrêté et viewport rétabli.
- Contrôle global final `php scripts/check.php` : code 0 ; migrations shortflow_test à jour, schémas dev/test valides, 19 YAML / 35 Twig et conteneur valides, PHPStan niveau 5 sans erreur, CS-Fixer 0/96 ; **110 tests / 1 070 assertions réussis**, 7 min 56,981 s. Les 403/404 affichés sont attendus dans les scénarios de sécurité. Aucun échec dans cette passe complète.
- Guides pédagogiques statistics/studio, README et brouillon de confidentialité actualisés. Les règles de conservation/vérification avant exploitation continue en production restent documentées ; seul le relevé local YouTube est autorisé ici. Plusieurs commits locaux demandés, aucun push demandé ; .env.local demeure ignoré.

# Vérification — 17 octobre 2026, aperçu direct et studio élargi

- À la demande du propriétaire, aperçu vidéo ouvert dès l’arrivée dans Publications, sans autoplay, avec preload metadata ; colonne média dédiée, informations et actions à côté. Suppression du lien source redondant et des explications répétées par vidéo. Message global si collecte désactivée ; sans ancien relevé, compteurs vides masqués. Collecte réelle toujours désactivée, aucun accès social ajouté.
- Conteneur commun du studio élargi de 1 500 à 2 200 px ; marges internes conservées, formulaires gardés lisibles. Changement dans studio.css, publication.css et les trois templates de Publications. Guides studio/statistics actualisés ; tests existants adaptés au preload et au libellé court Source supprimée.
- Lint 34 Twig, npm run check, 5 tests frontend et build Vite : codes 0, avertissements habituels sur ressources statiques/chunk Three.js. Migrations shortflow_test à jour avant PHPUnit. Première passe ciblée : 16 tests / 163 assertions avec un échec dû à l’ancien libellé attendu ; assertion corrigée. Passe ciblée finale PublicationStatisticsTest|StudioOverviewTest : **16 tests / 164 assertions réussis**, 1 min 33,440 s. Suite globale non répétée pour cette modification de présentation. git diff --check : code 0, avertissements de fins de ligne seulement.
- QA sur vrais templates avec données synthétiques en mémoire, sans écriture DB : écran 2 560 × 1 440 (conteneur mesuré à 2 200 px), capture desktop 1 440 px, mobile 360 px sans débordement. Lecteurs visibles, métadonnées prêtes et vidéos en pause. État collecte inactive : zéro bloc de compteurs vide et une seule explication globale. Console sans avertissement/erreur. Capture artifacts/publications-direct-preview.png ignorée. Serveur temporaire arrêté, viewport rétabli. Aucun commit.

# Vérification — 17 octobre 2026, aperçu vidéo et compteurs locaux des trois réseaux

- Lot explicitement autorisé avec API simulées pour YouTube, Instagram et TikTok. Aperçu de la source privée dans Publications, replié sans autoplay ; métadonnées demandées à la première ouverture, pause à la fermeture. Source supprimée : message sans lecteur cassé. Compteurs par destination, zéro distinct d'une valeur absente, relevé daté, demande asynchrone avec cooldown et conservation du dernier relevé en cas d'erreur. Aucun texte de commentaire ni appel social au chargement de la liste.
- Migration additive Version20261017133101 appliquée en dev et dans shortflow_test : quatre requêtes par base, aucune purge des données dev. Nouvelle entité de relevé, service métier, message/handler Messenger, contrôleur POST propriétaire/CSRF/limiteur et adaptateurs HTTP. Configuration réelle PUBLICATION_STATISTICS_ENABLED=0 vérifiée ; tests activés avec MockHttpClient uniquement. Guide pédagogique dans docs/statistics.md, limites Instagram et maintenance de conservation avant activation réelle explicites.
- Contrôle global final `php scripts/check.php` : code 0 ; migrations de test à jour, schémas dev/test valides, 19 YAML / 34 Twig et conteneur valides, PHPStan niveau 5 sans erreur, CS-Fixer 0/94 ; **106 tests / 1 015 assertions réussis**, 7 min 21,632 s. Refus 403/404 attendus dans les scénarios de sécurité. Première passe ciblée : quatre erreurs liées au remplacement de clients HTTP déjà initialisés dans les tests ; correction avec setResponseFactory, puis 8 tests / 83 assertions réussis avant ajout des quatre derniers cas. Une première passe globale a été interrompue après une modification de signature du contrôleur ayant rendu son conteneur en mémoire obsolète ; caches dev/test reconstruits avant la passe finale complète ci-dessus.
- `npm run check`, `npm run test:ui` (**5 tests réussis**) et build Vite : codes 0. Avertissements habituels Vite sur médias statiques/chunk Three.js. Contrôles frontend du lecteur : chargement unique des métadonnées, absence d'autoplay, pause fermeture/déconnexion, absence de vidéo tolérée. `git diff --check` réussi, avertissements LF/CRLF seulement.
- QA navigateur sur vrais templates avec objets synthétiques en mémoire, sans écriture DB et serveur temporaire loopback:8001 : desktop 1280 px, mobiles 390 et 360 px, pas de débordement horizontal. Lecture native constatée puis pause à la fermeture, ouverture/fermeture clavier, valeurs zéro/absentes, attente/échec et boutons cooldown contrôlés. Console sans erreur/avertissement. Correction d'une règle mobile héritée des formulaires, styles désormais limités à details.publication-preview. Captures ignorées artifacts/publication-statistics-desktop.png et publication-statistics-mobile.png ; chiffres exclusivement synthétiques. Serveur temporaire arrêté et viewport rétabli.
- Aucun accès social, essai réel, publication ou déploiement ajouté. Le worker local est rechargé après les contrôles. Aucun commit automatique.

# Ajustement — accès légal regroupé dans Mon compte

- À la demande du propriétaire, retrait des liens légaux de la barre latérale et de l'en-tête mobile du studio. Les trois liens de la section Mon compte (confidentialité, index légal, exercice des droits) restent présents, ainsi que les liens publics et ceux de l'inscription. Suppression des règles CSS devenues inutiles et adaptation des assertions du studio. Le guide studio décrit l'emplacement actuel ; le lot précédent ci-dessous décrit l'état historique.
- `npm run check`, build Vite et lint des 33 Twig : codes 0 ; avertissements Vite habituels conservés. Première passe ciblée exécutée avant le retrait effectif du template : un échec sur l'ancien lien encore présent, puis reprise après modification. Vérification finale `StudioOverviewTest|LegalPagesTest` : **5 tests / 125 assertions réussis**, 28,687 s, exclusivement dans shortflow_test déjà migrée. Pas de changement métier ni de migration ; navigateur non relancé pour ce retrait de liens.

# Vérification — 17 octobre 2026, ergonomie du dashboard et accès légal

- Dashboard compact, action principale préparation → ancre de sélection de la bibliothèque (import si vide), repères du parcours par réseau. Aperçus des trois derniers envois à reprendre puis en cours avant les sources récentes ; noms YouTube/Instagram/TikTok corrects. Requêtes des aperçus évitées si leur compteur est nul, filtrage propriétaire existant conservé et aucun appel social à l'affichage.
- Accès « Confidentialité et légal » déplacé du pied du studio à la barre latérale desktop/en-tête mobile ; Mon compte regroupe confidentialité, documents et exercice des droits. Les liens publics et de collecte restent présents. Les états réseau distinguent configuration manquante, connexion, reconnexion et renouvellement ; pas de fausse panne temporaire annoncée pour une intégration inactive.
- Nouveau contrôleur Stimulus pour retour d'attente sur les liens internes et soumissions natives, protection contre les doubles soumissions et restauration pageshow. L'import avec progression reste géré par son contrôleur. Correction supplémentaire vérifiée : après retour arrière dans la bibliothèque, compteur et bouton se recalculent à partir des cases restaurées. Sans JS, navigation/formulaires Symfony conservés. Reduced motion prévu pour l'indicateur CSS.
- Contrôle global `scripts/check.php` : migrations de shortflow_test appliquées, schémas valides, 19 YAML / 33 Twig, conteneur, PHPStan niveau 5 et CS-Fixer réussis ; suite complète **94 tests / 905 assertions, une erreur** dans le nouveau scénario de test (User détaché lors de création d'une publication après requêtes). Référence gérée réutilisée. Première reprise ciblée : **5 tests / 123 assertions, un échec** de sélecteur visant le premier aperçu au lieu d'En cours. Sélecteur corrigé. Vérification ciblée finale `StudioOverviewTest|LegalPagesTest` : **5 tests / 125 assertions réussis**, 27,722 s. Les refus 403/404 sont attendus dans les scénarios de sécurité. Suite complète non répétée après ces corrections limitées aux tests.
- `npm run check`, `npm run test:ui` (**4 tests réussis**) et build Vite via Docker : codes 0. Lint final 33 Twig et CS-Fixer 0/87 : codes 0. Avertissements Vite habituels sur médias statiques/chunk Three.js. Worker rechargé ; aucun secret, publication réelle, calendrier ou IA ajouté.
- QA navigateur : vrais templates Twig avec objets synthétiques en mémoire, rendus sans écriture DB et servis temporairement sur loopback:8001. Dashboard rempli à 1280 et 390 px, état vide à 360 px, bibliothèque/sélection, Mon compte à 390/360 et Connexions à 360 px. Aucun débordement horizontal aux contrôles réalisés, un accès légal visible par breakpoint. Clavier : premier Tab sur Aller au contenu avec contour visible. Action préparation rejoint la sélection et lui donne le focus ; sélection globale active le bouton ; accès aux vrais documents publics puis retour arrière conserve deux cases, compteur et bouton. Console de l'aperçu sans erreur/avertissement au relevé final. Pas un essai OAuth ni une publication sociale. Serveur temporaire arrêté et viewport rétabli. Captures `artifacts/dashboard-ux-desktop.png` et `dashboard-ux-mobile.png` ignorées.
- Guide pédagogique mis à jour dans docs/studio.md ; tests frontend exécutables avec `npm run test:ui`. Aucun commit automatique.

# Vérification — 17 octobre 2026, lenteur du runtime après git pull

- Diagnostic avant correction : conteneur PHP ancien avec uniquement `/app` en bind mount, sans les volumes natifs pourtant déclarés dans le Compose récupéré. HTTP connexion 200 : 3,536 / 4,680 / 3,693 s ; CSS statique : environ 0,222 s. Ressources Docker sans saturation au relevé. Sonde PHP temporaire : OPcache actif dans le serveur `cli-server`, connexion PostgreSQL et `SELECT 1` en 6,42 ms. Sonde retirée. Les premiers appels HTTP en sandbox ont renvoyé une réponse filtrée très rapide ; exclus des mesures, reprises hors sandbox avec contrôle du code HTTP.
- Comparaison temporaire dev sans debug non retenue comme solution : deux réponses 200 à 6,755 et 5,351 s (dont amorçage du cache), troisième réponse 404 après retrait de la sonde exclue. Le debug du serveur normal n'a pas été désactivé.
- Copie des dépendances Windows déjà installées vers le volume Docker `vydflow_php_vendor`, copie source conservée ; création du volume `vydflow_app_cache`. Application du Compose avec `up -d php worker`, vérification des trois montages réels et préchauffage du cache dev avec debug=true, code 0. Base et stockage vidéo non déplacés, aucun volume supprimé.
- Après correction, connexion HTTP 200 : 0,722 / 0,504 / 0,510 s ; CSS 200 : 0,222 / 0,209 / 0,223 s ; landing 200 : 0,509 s. Mesures ponctuelles depuis Windows vers localhost, pas un benchmark de charge ni une mesure du parcours authentifié. Schéma Doctrine valide, quatre services démarrés et base saine. Aucune modification applicative PHP/frontend dans cette correction ; PHPUnit non relancé. Une nouvelle session navigateur de mesure a abouti à la connexion, sans authentification supplémentaire ni action sociale.
- README complété : différence entre restart et up après modification de Compose, contrôle des montages et dépendances à installer avant recréation. Aucun changement d'emplacement des liens légaux dans ce diagnostic.

# Vérification — 17 octobre 2026, styles du studio après git pull

- Cause observée dans une session QA locale authentifiée : ancien `public/build/app.css` avec les templates récents, compteurs et états réseau sans mise en page. Reconstruction des assets après installation des dépendances dans le volume Node local. Le premier build seul a échoué (`vite: not found`, volume vide) ; `docker compose --profile tools run --rm --no-deps node` a ensuite installé les dépendances et réussi le build.
- Styles ciblés dans `studio.css` et `publication.css` : états réseau espacés avec retour à la ligne, colonne d'actions commune aux cartes de connexion, boutons pleine largeur et hauteur minimale de 44 px, actions sous le contenu sur petits écrans. Boutons YouTube/TikTok désactivés explicites quand indisponibles, comme Instagram. Aucune action OAuth déclenchée.
- Contrôles visuels : dashboard à 1366 et 390 px, Connexions à 1366, 390 et 360 px, Mon compte à 1366 px. Pas de débordement horizontal sur ces pages/tailles contrôlées ; boutons réseaux mesurés à 240 × 44 px avec même abscisse sur desktop, largeur commune de 289 puis 259 px sur mobiles. Premier Tab du dashboard sur « Aller au contenu », contour visible. Console Connexions sans erreur ni avertissement au contrôle final. Captures ignorées dans `artifacts/studio-*.png`, viewport rétabli après contrôle.
- Build final, `npm run check` via le conteneur Node et lint des 32 Twig : codes 0. Avertissements Vite préexistants sur médias statiques et chunk Three.js. PHPUnit non relancé pour ces changements CSS/Twig ; aucune logique de publication modifiée.
- README et guide studio complétés sur la reconstruction des assets après pull. Extension locale `ckolkman.vscode-postgres` 1.4.3 identifiée ; README et menus de l'extension confirment clic droit table → Select… → Run Select Top 1000 pour lire les lignes. Aucune modification de l'extension ou de ses connexions.

# Vérification — 17 octobre 2026, reprise de la base après git pull

- Erreur fournie : table `tiktok_connection` absente. Migration existante `Version20261016220806` appliquée sur la base dev : une migration, neuf requêtes, code 0. Aucune purge ni suppression de volume ; tables TikTok ajoutées.
- `composer db:sync` ajouté : création dev si absente, migrations manquantes et validation du schéma. Exécution sur la base existante : création ignorée, migrations déjà à jour, mapping et schéma valides, code 0. Une reconstruction depuis une base vierge n'a pas été rejouée pour ce changement.
- PostgreSQL publié uniquement sur `127.0.0.1:5433` pour VS Code ; le volume existant est conservé. Accès TCP hôte vérifié ; authentification PostgreSQL TCP avec les identifiants Compose locaux réussie et présence de `public.tiktok_connection` confirmée. Quatre services démarrés, PostgreSQL sain, worker redémarré après migration. Compose avertit que le volume conservé a été créé sous l'ancien nom de projet `shortflow` ; aucune modification de ce volume.
- `composer validate --no-check-publish`, `docker compose config --quiet` et `git diff --check` : codes 0 (avertissement Git LF/CRLF sur composer.json). Pas de modification applicative PHP/frontend ; suite PHPUnit et navigateur authentifié non relancés dans ce lot.
- README : procédure premier clone/après pull, rôle des migrations et des seeds facultatifs, paramètres VS Code et conservation des données. Aucun seed automatique ni compte partagé ajouté : inscription et confirmation Mailpit restent le parcours local.

# Vérification — 17 octobre 2026, lot TikTok local

- OAuth TikTok, renouvellement/rotation chiffrés, révocation, préparation avec consentement, publication Messenger, URLs signées et suivi intégrés. Instagram conserve son parcours Reels déjà implémenté. Aucun accès réel renseigné, aucune connexion/publication sociale réelle, aucun service HTTPS public créé. Préparation par réseau ; calendrier, IA, statistiques et confirmation simultanée multi-réseaux hors du lot.
- Migration additive Version20261016220806 relue puis appliquée en dev et shortflow_test, sans suppression de données dev. Les tests vérifient le nom shortflow_test avant tout nettoyage. HTTP TikTok remplacé par MockHttpClient dans le conteneur de test.
- `docker compose exec -T -e SHELL_VERBOSITY=-1 php php scripts/check.php` : code de sortie 0 (sortie interne non retransmise par cette exécution, aucun total PHPUnit global annoncé). Ce script migre la base de test, valide les schémas, lance les lints, PHPStan, CS-Fixer et la suite complète.
- Vérifications explicites : Twig 32 fichiers valides ; PHPStan niveau 5 sans erreur. Dernière vérification ciblée après sécurisation du renouvellement OAuth : `phpunit --filter TikTok`, **20 tests / 139 assertions**, réussis en 46,909 s. Les 403/404 affichés sont attendus. Le renouvellement en erreur conserve un EntityManager ouvert afin que le worker puisse enregistrer les tentatives puis la suspension.
- `npm run check` local : code 0. `npm run build` local indisponible car Vite est installé dans le volume Docker ; build de remplacement `docker compose --profile tools run --rm --no-deps node npm run build` : code 0, avertissements habituels sur médias statiques et chunk Three.js. Worker Docker redémarré avec le handler TikTok.
- Vérification Chrome headless de préparation TikTok, Connexions, Publications et dashboard à 1366, 390 et 360 px : douze pages sans débordement horizontal ni erreur JavaScript ; captures mobile/desktop inspectées. Visibilité initialement vide, interactions décochées, Duos interdits désactivés, sélection et navigation clavier vérifiées. Pages réellement rendues par les contrôleurs avec fixtures synthétiques exclusivement dans shortflow_test, puis servies au navigateur par interception locale ; ce n’est pas une session OAuth réelle ni une preuve de lecture complète de la vidéo. Scripts et captures dans artifacts/, ignorés.
- Documentation pédagogique, configuration privée et étapes d’activation dans docs/tiktok.md. Aucun nouveau secret réel dans .env ou .env.test ; les valeurs de test sont synthétiques. Aucun commit ni déploiement dans ce lot.

# Vérification — studio, suivi commun et compte utilisateur

- Vue d'ensemble : compteurs réels (sources/envois en cours/à traiter/publiés), trois derniers envois à traiter, état local YouTube/Instagram et dernières vidéos. Historique commun filtrable par réseau/état et paginé à 20 envois, tri date/réseau/id stable, contrôle propriétaire et projections sans jeton. Actions reprise/arrêt et liens source conservés. Aucun appel social à l'affichage.
- Mon compte : informations et date déjà stockées, stockage utilisé, accès aux réseaux et changement de mot de passe avec mot de passe actuel, CSRF, limiteur, verrou transactionnel, hash Symfony, invalidation des liens de récupération et des sessions. Email en lecture seule ; pas de changement d'email, suppression automatisée ou facturation ajouté.
- Pieds de page publics/compte/studio regroupés vers Informations légales. Navigation complète dans les documents et liens contextuels CGU/confidentialité à l'inscription conservés. Guide pédagogique des fichiers, flux et tests dans docs/studio.md.
- Contrôle global `docker compose exec -T -e SHELL_VERBOSITY=-1 php php scripts/check.php` : code 0 après préparation/migrations de shortflow_test. La sortie de cette exécution n'a pas été retransmise ; aucun compteur global supposé. Contrôles préliminaires Twig/PHPStan/CS-Fixer réussis. Rapport explicite complémentaire `StudioOverviewTest` : **4 tests / 48 assertions**, zéro erreur/échec, 18,789 s ; rapport ignoré artifacts/studio-targeted.xml. Première passe corrigée : sélecteur de test ciblant le formulaire de déconnexion au lieu du formulaire de mot de passe ; seconde passe corrigée : paramètre nommé non supporté d'une assertion, remplacé par la lecture explicite de la seconde session.
- `npm run check` et build : codes 0. Dernier build après deux détails CSS mobiles : code 0, avertissements Vite préexistants sur médias/chunk Three.js. Chrome headless à 1366×768, 390×844 et 360×740 : trois pages remplies inspectées, aucune erreur console ni débordement horizontal, parcours des filtres, lien Mon compte, Tab entre mots de passe et lien légal unique vérifiés. Pages HTML rendues par les vrais contrôleurs via KernelBrowser avec fixtures exclusivement dans shortflow_test, puis servies au navigateur par interception locale ; ce n'est pas un essai OAuth ni une nouvelle session navigateur sur la base dev. Scripts/captures dans artifacts/, ignorés. Aucune donnée dev modifiée ou supprimée.
- Aucun schéma modifié, aucune publication réelle, configuration sociale, activation TikTok/IA/calendrier ou déploiement. Bibliothèque et Publications conservent leurs responsabilités distinctes.

# Vérification — 16 octobre 2026, reprise Windows, performances locales et hero portable

- Reconstruction de l'image PHP, installation Composer et npm, démarrage PHP/PostgreSQL/Mailpit/worker et sept migrations sur la nouvelle base locale. Aucune ancienne donnée ni aucun volume supprimé ; secrets Google/Meta non récupérés. Les données de l'ancien poste ne sont pas dans Git.
- Diagnostic HTTP avant correction : connexion 3,418 puis 3,357 s, landing 2,271 s, redirection Publications sans session 1,991 s, CSS statique 0,012 s. Sonde temporaire : OPcache actif dans le serveur PHP, connexion PostgreSQL + `SELECT 1` environ 12–13 ms. Sonde retirée après mesure. Le cache natif seul ne résout pas la lenteur : connexion répétée 3,204 s, landing 2,238 s.
- `compose.yaml` partage désormais `php_vendor` et `app_cache` entre PHP et worker, pour éviter les accès aux dépendances et au cache via Windows/OneDrive. Dépendances existantes copiées vers le volume ; copie Windows conservée. Code source, configuration privée, stockage vidéo et PostgreSQL conservent leurs emplacements. Mode dev/debug conservé ; procédure Composer et cache documentée dans README.
- Mesure finale, après les tests : connexion 0,305 puis 0,292 s ; landing 0,265 s ; redirections anonymes Bibliothèque 0,244 s et Publications 0,250 s. Mesures locales ponctuelles, pas de benchmark de charge ni de chronométrage navigateur des pages authentifiées. Le déplacement des dépendances est le gain principal observé.
- Hero : titre plus compact/remonté et téléphone légèrement abaissé sur desktop de hauteur ≤ 850 px. Décalage calculé au resize et pondéré par la pose du hero, nul dans les chapitres suivants. Captures Chrome headless en WebGL logiciel à 1366×650, 1366×768, 1920×1080, 2560×1440 et mobile 390×844 : aucun débordement horizontal ni erreur JavaScript ; géométrie du titre grand écran/mobile identique avant/après. Captures de portable inspectées, titre et téléphone séparés, y compris avec pointeur proche du haut.
- Clavier : premier Tab sur « Aller au contenu ». Scroll vers L'impulsion, pause/reprise et resize 1366×650 → 1920×1080 → 1366×650 vérifiés. Deux avertissements de texture WebGL observés à la première reprise : correction des flags d'upload conservés par le contexte réutilisé dans `cleanup()`, puis scénario relancé sans erreur ni avertissement console. Mouvement réduit : état static ; WebGL indisponible simulé : état fallback. Captures et script local dans artifacts/, ignorés. Pas de test sur appareil physique.
- Premier contrôle global arrêté par CS-Fixer : fichiers PHP clonés en CRLF. Ajout de `*.php text eol=lf` dans `.gitattributes`, normalisation sans changement de contenu ; une indentation préexistante corrigée dans YouTubeApi. Aucun comportement réseau social modifié.
- Contrôle global final `docker compose exec -T php php scripts/check.php` : code 0, migrations shortflow_test à jour avant PHPUnit, schémas dev/test synchronisés, 19 YAML et 30 Twig valides, conteneur valide, PHPStan sans erreur, CS-Fixer 0/69, **70 tests / 712 assertions**, PHPUnit 4 min 46,516 s. Les 403/404 imprimés sont les refus attendus des tests. `npm run check` et `npm run build` finaux : codes 0, avertissements Vite habituels sur médias statiques et chunk Three.js. Compose valide, worker rechargé, quatre services démarrés. Aucun essai social réel, commit ou déploiement.

# Vérification — 16 octobre 2026, base des documents légaux

- Identité publique fournie par le propriétaire pour sa micro-entreprise, cible B2C + B2B ; contact retenu dev@alexbuild.fr. Pas de recherche sur son site ni de vérification du registre annoncée. Hébergeur, médiateur, TVA et offre commerciale non inventés.
- Sept routes accessibles : index, mentions légales, CGU, CGV, confidentialité (URL préservée), cookies, instructions de suppression. Version de travail visible, X-Robots-Tag noindex/nofollow. Liens dans landing, compte, studio et liste d’attente. Coordonnées centralisées dans Twig, pages HTML sans dépendance au JavaScript. Aucune acceptation contractuelle ni purge inexistante annoncée ; aucune souscription payante ajoutée.
- Sources officielles et inventaire du code dans docs/legal-audit.md. Le guide ux-flow-coding a servi au parcours ; aucun skill juridique spécialisé ni certification de conformité revendiquée. L’audit distingue pages rédigées et mesures techniques/organisationnelles restant à réaliser avant lancement.
- `npm run check` et `npm run build` : codes 0 ; avertissements Vite préexistants sur références statiques et chunk Three.js. Contrôle final Twig après retrait de l’ancien template : **30 templates valides**, code 0. Diff sans erreur de contenu, avertissements LF/CRLF seulement.
- Navigateur : index → Mentions légales, coordonnées et statut visibles sur ordinateur. Viewport mobile demandé 390 × 844, document visible 375 px / scrollWidth 375 px ; aucun débordement, passage Tab depuis CGU vers CGV, console sans erreur/avertissement. Captures ignorées : artifacts/legal-desktop.jpg et legal-mobile.jpg. Override viewport retiré. Plusieurs navigations ont nécessité une observation après expiration CDP ; les contrôles finaux cités ont réussi.
- Aucun déploiement, inscription à un médiateur, paiement, changement de domaine ou suppression de données. La procédure RGPD complète, DPA, rétractation/résiliation et conformité API de production ne sont pas validées par ces pages.
- `docker compose exec -T php php scripts/check.php` terminé avec **code 0** : base shortflow_test migrée à la version courante, schémas dev/test synchronisés, YAML/conteneur valides, PHPStan sans erreur, CS-Fixer sans changement et **70 tests / 712 assertions**, 8 min 11 s. Les erreurs 403/404 affichées sont les refus attendus des tests de sécurité. Le lint Twig complémentaire couvre les 30 templates finaux après retrait de l’ancienne confidentialité.

# Historique — 16 octobre 2026, publication Reels et barre des comptes

- Parcours Reels implémenté : préparation multi-vidéos par réseau, légende/options, confirmation/CSRF, jobs et messages atomiques, worker Docker existant, suivi/reprise/arrêt, liens média signés 24 h et badges cumulables. Aucun appel Meta réel ni accès public activé. La configuration HTTPS est vide par défaut et désactive l’envoi réel.
- Migration additive Version20261016164818 générée, relue et appliquée à dev et shortflow_test, sans suppression des données du développeur.
- `docker compose exec -T php php scripts/check.php` : code 0 ; schémas dev/test synchronisés, YAML/Twig/conteneur valides, PHPStan sans erreur, CS-Fixer sans changement, **68 tests / 645 assertions**, durée PHPUnit 7 min 56 s. Les 403/404 affichés sont des scénarios de sécurité attendus. Après cette suite : ajout d’un neuvième scénario Reels (rollback lot/messages), relecture de connexion sous verrou et correction navigation ; validation ciblée complémentaire ci-dessous.
- `npm run check` : code 0. Build d’abord refusé par les permissions Windows sur public/build, puis relancé avec accès autorisé : code 0. Dernier build après la barre pleine largeur et les liens destination : code 0, avertissements statiques/chunk Three.js préexistants conservés.
- Navigateur : compte QA local existant, sélection bibliothèque → préparation YouTube → lien Instagram conservant la sélection ; légende, options, bouton désactivé et explication de disponibilité. Vidéo de test d’une seconde refusée pour Reels. Ordinateur et viewport mobile demandé 390 × 844 ; document visible 375 px, scrollWidth 375 px, pas de débordement ni erreur/avertissement console. Navigation Publications corrigée après observation. Capture locale ignorée : artifacts/reels-mobile.jpg. Session QA déconnectée après contrôle.
- Barre compte : retrait du max-width 1440 px de .account-header ; largeur 100 %, marges intérieures existantes préservées, mêmes templates sur connexion et inscription. Contrôle grand écran tenté mais interrompu par délais CDP du navigateur ; ne pas annoncer une capture validée de cette barre à ce stade.
- Worker redémarré localement pour charger le nouveau handler. Aucun lancement de génération, calendrier ou TikTok. Guide pédagogique : docs/instagram-publication.md.
- Contrôle complémentaire final : `phpunit tests/Functional/InstagramPublicationTest.php --log-junit /app/artifacts/reels-junit.xml`, code 0, **9 tests / 90 assertions**, 1 min 16 s. Le premier passage du nouveau scénario de rollback a révélé deux imports retirés lorsqu’ils étaient encore inutilisés ; imports rétablis, neuf scénarios relancés avec succès. PHPStan final sans erreur, lint Twig final 22 templates valides, CS-Fixer final aucun changement. Aucune nouvelle suite globale de 69 tests annoncée.

# Historique — 16 octobre 2026, connexion Instagram

- Sous-lot terminé : connexion OAuth Instagram directe, compte Créateur/Entreprise vérifié, permissions de profil/publication, jeton longue durée chiffré avec clé Instagram séparée, propriétaire unique, reconnexion et déconnexion locale. Aucun envoi de Reel implémenté ni essai Instagram réel effectué ; inscription développeur Meta toujours en attente côté propriétaire.
- Migration additive Version20261015231230 appliquée à dev puis shortflow_test. Une première tentative test a échoué pendant le nettoyage du cache Symfony ; la migration test a ensuite été relancée avec succès avant la validation finale. Les premiers tests ont aussi révélé une entité synthétique détachée dans le scénario multi-utilisateur et un avertissement PHPStan sur un accès de tableau redondant ; tous deux corrigés.
- Validation finale : `docker compose exec -T -e SHELL_VERBOSITY=-1 php php scripts/check.php` terminé avec code 0. Ce script prépare/migre shortflow_test, valide les deux schémas, les lints YAML/Twig/conteneur, PHPStan niveau 5, CS-Fixer et la suite PHPUnit. La sortie console de ce lancement n’a pas été retransmise par le pipeline PowerShell ; aucun compteur global supposé annoncé.
- Rapport explicite complémentaire : `phpunit --filter Instagram --colors=never --log-junit /app/artifacts/instagram-junit.xml`, code 0, **14 tests / 100 assertions**, zéro échec/erreur, 45,731 s. Les erreurs HTTP 403 affichées correspondent aux scénarios d’accès refusé attendus. HTTP Instagram et YouTube toujours simulé en test.
- `npm run check` et `npm run build` relancés : codes 0, avertissements Vite préexistants (médias statiques et chunk Three.js) conservés. `git diff --check` sans erreur de contenu, avertissements Git LF/CRLF uniquement.
- Navigateur intégré : connexion avec le compte synthétique dev existant, carte YouTube préservée et Instagram indisponible faute de clés, rendu ordinateur et viewport mobile 390 × 844, largeur document = largeur visible = 375 px, aucun débordement horizontal. Bouton Instagram réellement désactivé et sauté au Tab depuis YouTube vers Confidentialité. Console : aucun avertissement/erreur. Session QA déconnectée après contrôle. Captures locales : artifacts/instagram-desktop.jpg et instagram-mobile.jpg, ignorées par Git. Pas d’essai du consentement Meta réel ni d’une connexion active dans le navigateur ; ces états sont couverts par les tests HTTP simulés.
- Reprise de l’environnement : Docker Desktop plantait sur des sockets AF_UNIX temporaires inaccessibles. Dossiers contenant exclusivement des sockets vides préservés sous `C:\Users\alexb\AppData\Local\Docker\run.stale-81f4cc85`, `run.stale-0f2a6840` et `C:\Users\alexb\AppData\Local\docker-secrets-engine.stale-2c19b61e`, puis redémarrage réussi. Aucun volume, base, vidéo ou réglage de sécurité supprimé/réinitialisé. Moteur accessible, VydFlow démarré. Le port 8025 étant occupé par jobflow_mailpit, Mailpit VydFlow passe en loopback 8026 dans compose.yaml ; l’autre projet reste actif. README et guide comptes actualisés.
- Guide pédagogique et limites : docs/instagram.md. Le renouvellement automatique et la publication de Reels restent au prochain sous-lot ; TikTok et IA hors de cette étape.

# Vérification — 16 octobre 2026, repère de publication réussie

- Mention « Déjà publiée » et icône YouTube accessible dans la bibliothèque, le dashboard et la fiche vidéo. Une requête groupée par page, succès avec identifiant distant uniquement, double filtre propriétaire, déduplication des icônes. Historique indépendant de la connexion actuelle ; aucune nouvelle publication externe.
- Test fonctionnel ciblé : sortie 0, 1 test / 24 assertions. Couvre les trois vues, queued/processing/failed/cancelled, doublons, déconnexion et dossier d’un autre propriétaire. Base shortflow_test déjà migrée au contrôle complet précédent. Le premier passage a révélé une entité de test détachée entre requêtes HTTP ; le test recharge désormais les entités avant leur modification.
- PHPStan : sans erreur ; lint Twig bibliothèque : 6 templates valides ; npm run check/build : sorties 0 avec les avertissements Vite déjà documentés ; git diff --check sans erreur de contenu. Contrôle visuel navigateur tenté, navigation locale interrompue par expiration CDP ; aucun résultat visuel desktop/mobile annoncé pour ce badge. Aucun nouveau contrôle complet de 46 tests annoncé.
- Décision du propriétaire pour la suite : passer son Instagram personnel en Créateur puis intégrer Reels avant TikTok. Le changement réel du compte et les identifiants Meta ne sont pas encore confirmés ; aucun code ni compte Instagram/TikTok connecté dans cette étape.

# Vérification — 16 octobre 2026, rangement des commits

- Contrôle complet `docker compose exec -T php php scripts/check.php` : sortie 0 ; migrations shortflow_test à jour, schémas dev/test valides, 19 YAML et 18 Twig valides, conteneur valide, PHPStan sans erreur, CS-Fixer 0/51, PHPUnit 45 tests / 436 assertions en 5 min 45 s. Les erreurs HTTP affichées correspondent aux scénarios négatifs attendus.
- `npm run check` et `npm run build` : sorties 0. Avertissements Vite conservés : références aux médias statiques résolues à l’exécution et chunk Three.js supérieur à 500 ko. Aucun nouveau parcours navigateur exécuté pendant ce rangement.
- Six commits séparés : dépendances, parcours bêta, bibliothèque privée, OAuth/publication YouTube, worker Docker et documentation/roadmap. Les fichiers partagés ont été découpés dans l’index sans modifier l’application active. La bibliothèque seule ne référence pas les routes ou tables de publication.
- .env.local, stockage privé et fichiers compilés ignorés ; aucune signature de secret Google ni clé privée détectée parmi les 79 fichiers texte modifiés. Le seul binaire versionné est la vidéo de test synthétique de 1 921 octets. La variation de config/reference.php générée par l’environnement test est écartée.

# Vérification — 16 octobre 2026, worker Docker et premier transfert YouTube réel

- Autorisation du propriétaire : intégrer la consommation automatique et traiter sa vidéo déjà confirmée. Connexion OAuth réelle effectuée par le propriétaire ; aucun secret affiché ou versionné.
- Fichiers : compose.yaml (service worker), docker/php/worker.sh (attente des prérequis puis consommation), docker/php/Dockerfile (pcntl), README.md, docs/youtube.md et docs/architecture.md.
- Contrôles exécutés avec succès : docker compose config --quiet ; sh -n docker/php/worker.sh dans PHP ; docker compose build php ; docker compose up -d php worker ; extension pcntl présente. docker compose ps et top worker confirment le processus Messenger actif sans port publié. git diff --check sans erreur de contenu, avec avertissements Git LF/CRLF sur les fichiers existants.
- Publication existante n° 1 : queued → processing → published ; 498 990 octets transférés, ID distant enregistré, zéro erreur. Visibilité demandée : non répertoriée. L’API de suivi a confirmé la disponibilité ; aucune vérification visuelle dans YouTube Studio ni garantie de visibilité effective. Aucun nouvel envoi créé par Codex.
- messenger:stats après traitement : publications = 0, publication_failures = 0. La consommation continue dans Docker ; arrêt propre et relance configurés, sans simulation d’une panne ou attente du recyclage horaire.
- Pas de changement du code PHP applicatif ni de nouvelle exécution de PHPUnit/frontend dans cette étape Docker. Les résultats des étapes antérieures restent ci-dessous. Guide pédagogique : docs/youtube.md, section worker et composants.

# Vérification — 15 octobre 2026, navigation application et cases à cocher

- LandingController redirige GET / vers /studio pour ROLE_VERIFIED, avec réponse privée non stockable. Le logo du studio pointe directement vers /studio ; lien Découvrir VydFlow supprimé. Déconnecté, la landing reste accessible. Confirmation email et sécurité inchangées.
- Cases de bibliothèque et de publication espacées de 14 px ; lignes Symfony synthétique/confirmation identifiées par row_attr puis disposées en grille pour préserver les retours de texte mobile. Fichiers : assets/styles/publication.css, src/Form/YouTubeVideoType.php, src/Form/YouTubePublishType.php, templates/studio/base.html.twig et src/Controller/LandingController.php.
- Test existant de navigation adapté : sortie 0, 1 test / 14 assertions, landing anonyme, redirection connecté, logo /studio et absence de lien footer vers /. Aucune nouvelle suite complète relancée pour cette correction ciblée.
- npm run check/build : réussis, avertissements Vite préexistants conservés. Twig : 18 valides. PHPStan : sans erreur. CS-Fixer exécuté sur les fichiers modifiés. git diff --check : sortie 0.
- Navigateur : connexion du compte QA fictif, / redirigé vers studio, sélection de 2 vidéos et préparation, absence du lien footer. Grilles de cases mesurées : contrôles 18 px et espace 14 px. Rendu mobile 390×844 examiné, aucun débordement horizontal ; la case de contenu synthétique a été cochée localement sans soumission ni appel externe.

---

# Vérification — 15 octobre 2026, publication YouTube

## Livré et limites
- Sélection multiple de vidéos privées, préparation YouTube par vidéo, connexion OAuth state/PKCE, autorisations et session de transfert chiffrées, file Doctrine Messenger transactionnelle, transferts reprenables, suivi, suspension/reprise et arrêt confirmé. Isolation propriétaire, CSRF, limiteur, contrôle de chaîne sous verrou et protection des sources en cours d’envoi.
- HTTP Google intégralement simulé dans les tests ; aucune application Google créée, aucun compte social réel connecté, aucun transfert réel, aucun worker dev lancé. Configuration privée et worker à préparer pour un essai propriétaire explicitement autorisé. Guide pédagogique : docs/youtube.md. Instagram/TikTok/calendrier/IA non implémentés.
- Migration additive Version20261014233023 appliquée en dev et shortflow_test, après lecture du SQL. Comptes, vidéos et données historiques conservés.

## Résultats exécutés
- Conteneurs locaux relancés après l’interruption ; volume PostgreSQL existant réutilisé, aucun effacement.
- Première passe YouTube : 5 tests unitaires / 25 assertions réussis ; 8 tests fonctionnels / 68 assertions avec 2 erreurs de préparation des tests (entité devenue détachée après une requête ; service HTTP simulé remplacé après initialisation). Ces erreurs ont été corrigées dans les tests. Reprise de 5 scénarios : 3 réussis, 2 erreurs dues à un import de classe absent dans les nouveaux tests, ensuite corrigé.
- **Passe complète `docker compose exec -T php php scripts/check.php` : sortie 0.** Schémas dev/test conformes ; 19 YAML, 18 Twig et conteneur valides ; PHPStan niveau 5 sans erreur ; CS-Fixer 0/51 fichiers à corriger ; PHPUnit **44 tests / 431 assertions, tous réussis**, durée PHPUnit 5 min 37,947 s. Journal local ignoré : output/youtube-full-check.log.
- Après cette passe, ajout d’un contrôle des chevrons dans la description selon la référence officielle YouTube, et d’un test couvrant description UTF-8 de 5 000 octets, dépassement et chevrons. Reprise de tests/Unit/YouTubeApiTest.php : **sortie 0, 6 tests / 29 assertions réussis**. Pas de nouvelle passe complète de 45 tests annoncée. PHPStan relancé sans erreur après ce dernier ajout ; formatage relancé puis dry-run : 0/51 corrections.
- npm run check et npm run build : réussis. Avertissements existants de médias publics résolus au runtime et chunk Three.js 704,89 kB / 181,19 kB gzip. Composer validate : valide ; composer audit et npm audit : aucune vulnérabilité connue signalée. npm audit a d’abord échoué sur DNS dans le sandbox, puis a réussi avec accès réseau autorisé. git diff --check : sortie 0.
- Couverture nouvelle : OAuth state mono-usage et mauvais state sans appel Google, PKCE/scopes, chiffrement authentifié, refus d’un hôte de transfert étranger, erreurs sans secrets, propriétaire/ROLE_VERIFIED/CSRF, batch/file atomiques et rollback intégral, double POST sans doublon, session enregistrée avant octets, reprise d’une réponse perdue, traitement distant, redélivrance terminale ignorée, session expirée sans recréation, cinq erreurs/suspension/reprise de la même session, déconnexion suspendant les jobs et blocage de suppression d’une source active.

## Navigateur
- Compte QA fictif existant en dev : sélection globale de 2 vidéos, compteur et activation du bouton, formulaire YouTube avec 2 aperçus/titres/champs, visibilité et audience sans défaut. Bouton final désactivé sans connexion Google, aucune tentative d’envoi.
- Desktop et mobile 390×844 : rendu de préparation examiné, scrollWidth 375 px pour viewport 390, sans débordement. Saisie du titre et Tab vers description vérifiés. Connexions indisponible sans identifiants ; Publications vide avec liens vers la bibliothèque. Console sans erreur JavaScript. Vue mobile remise à zéro puis onglet temporaire fermé.
- Intermittences de navigation/capture : états finaux confirmés après récupération du même onglet. Pas de navigateur physique, autre moteur, publication réelle, upload de 100 Mo vers Google ou simulation navigateur sans JS. Le formulaire de sélection natif est couvert côté serveur.

---

# Vérification — 15 octobre 2026, nettoyage du studio

- Retrait du badge Email confirmé, du badge Bêta du studio, des menus inactifs et de la mini-roadmap du dashboard ; retrait des mentions futures redondantes sur compte/détail vidéo. Aucun service, contrôle de sécurité ou stockage modifié.
- Lint Twig Docker : 14 templates valides. npm run check et npm run build : sortie 0 ; avertissements préexistants de ressources publiques résolues au runtime et chunk Three.js conservés. git diff --check : sortie 0.
- Vue d’ensemble connectée contrôlée dans le navigateur : deux liens de navigation actifs, import et dernières vidéos présents ; mentions retirées absentes. Capture desktop examinée. Pas de nouvelle vérification mobile ni de nouvelle suite PHPUnit pour ce nettoyage de texte/Twig uniquement.
- Roadmap actualisée : publication immédiate multi-réseaux avant IA. Cadrage et contraintes officielles dans docs/publication.md. Aucune intégration sociale implémentée dans ce lot ; aucun envoi externe.

---

# Vérification — 15 octobre 2026, bibliothèque privée et parcours bêta

## Livré
- Landing GET / : tous les CTA principaux vont à l’inscription ou au studio selon le compte confirmé connecté. Formulaire historique déplacé sur /liste-attente ; table conservée. Aperçus futurs et fonctions à venir explicités.
- Bibliothèque privée : import MP4/WebM, validation serveur + ffprobe, métadonnées, pagination, quota compte, galerie et dernières vidéos, lecture privée HEAD/Range, renommage et suppression confirmée/CSRF. Progression XHR, erreurs serveur préservées et fallback multipart sans JS. Guide : docs/library.md.
- Image PHP reconstruite avec ffprobe via le paquet Debian ffmpeg et limites PHP 100M/105M. Application utilisant uniquement ffprobe en lecture, aucune transformation des vidéos utilisateur. Symfony Process déclaré en dépendance runtime.
- Migration Version20261014222452 relue et appliquée en dev/test : video_asset et FK/index uniquement. Comptes et données historiques conservés. Dates Twig affichées en Europe/Paris.

## Contrôles exécutés et résultats exacts
- Schémas dev et shortflow_test : conformes après migration. Lints : 18 YAML / 14 Twig / conteneur valides. PHPStan niveau 5 : sans erreur. CS-Fixer : 0 correction restante sur 35 fichiers. Ces contrôles ont été exécutés via scripts/check.php puis composer check après les ajustements.
- Première suite interrompue par le timeout Composer de 300 s. Un PHPUnit restait actif après la sortie de Composer ; tous les processus de test de cette session ont été arrêtés via leurs PID internes avant relance. Aucun processus applicatif ni donnée dev arrêté/supprimé. Le délai est désormais désactivé uniquement pour le script Composer check.
- Un test de Range ouvert hors limites attendait 416 alors que HttpFoundation ignore cette forme et renvoie 200. Le scénario utilise maintenant une plage explicitement hors limites ; octets partiels 206, HEAD et protection privée restent vérifiés.
- Passe complète finale `docker compose exec php composer check` : **28 tests, 285 assertions, 26 tests réussis / 2 échecs d’assertion**. Les 2 échecs cherchaient le texte du quota dans le premier form (déconnexion), au lieu de .upload-form. Sélecteur corrigé sans changement du comportement applicatif.
- Reprise ciblée `php vendor/bin/phpunit --filter 'testUploadCsrfAndStorageQuota|testVideoCountQuotaAndCleanupNeverPurgesReferencedFiles'` : **sortie 0, 2 tests / 19 assertions réussis**. Tous les 28 scénarios ont donc été validés entre la passe complète et cette reprise ; aucune nouvelle passe complète annoncée après correction des sélecteurs.
- Couverture bibliothèque : CTA anonyme/connecté, accès anonyme/non confirmé, MP4/WebM réels, métadonnées et nom opaque, HEAD/Range 206/416 et octets reçus, absence de route publique, isolation entre deux comptes sur liste/dashboard/lecture/renommage/suppression, XSS échappé, CSRF, suppression fichier/ligne, faux fichiers/extension/corruption/vide/poids, quotas poids/nombre, traversée de chemin, fichier manquant, pagination, rate limit, JSON progressif limité au redirect et purge conservant les fichiers encore référencés.
- `npm run check` et `npm run build` : sortie 0, build relancé après réponse XHR 201. Avertissements existants : ressources publiques /media et /cursor*.svg au runtime, chunk Three.js 704,89 kB / 181,19 kB gzip.
- `composer validate --no-check-publish` : valide. `composer audit` et `npm audit` : sortie 0, aucune vulnérabilité connue signalée. `git diff --check` : sortie 0. Lint Twig relancé après les textes de disponibilité : 14 valides.

## Navigateur et limites
- Compte fictif qa-studio@example.test confirmé : landing, connexion, dashboard vide, import réel WebM, détail et métadonnées (4,967938 s, 540×960), vidéo chargée readyState 4 / sans erreur, renommage, galerie et aperçu à 0,1 s observés.
- Desktop 1280 px et mobile 390×844 : galerie/import/détail contrôlés. Mobile : scrollWidth 375 px avec barre de défilement, aucun débordement. Navigation et boutons d’import accessibles ; confirmation de suppression ouverte puis annulée pour conserver les clips de démonstration.
- Faux MP4 envoyé depuis le navigateur : message serveur explicite, formulaire de nouveau utilisable. Reprise avec le MP4 original de test : import progressif réussi et détail affiché, 1 s, 96×160, H.264. Deux vidéos de démonstration conservées dans le compte QA en dev ; aucun fichier du développeur remplacé ou supprimé.
- Console sans erreur JavaScript au contrôle. Intermittences de capture/navigation du navigateur ; les états finaux ont été vérifiés via des observations suivantes. Pas de campagne multi-navigateurs, appareil physique, mesure de débit ou import réel de 100 Mo dans le navigateur. VP8/AV1 et pistes audio autorisés par le code mais non vérifiés avec des fichiers réels pendant cette passe.
- Les fichiers refusés restent temporaires ; les fichiers déplacés sont compensés sur échec de transaction. Arrêt brutal ou erreur disque/restauration : rapprochement opérationnel à réaliser selon docs/library.md. La commande de purge privée n’a pas été exécutée sur les données dev ; comportement vérifié sur fichiers test.
- Aucun déploiement, cloud, paiement, OAuth, IA ou calendrier développé. Modifications de ce lot non commitées ; le commit b6cca04 conserve le lot comptes précédent.

---

# Ajustement — confirmation email obligatoire avant connexion

- UserChecker Symfony ajouté : compte non confirmé refusé après validation du mot de passe ; message dédié avec lien de renvoi. Un mot de passe incorrect garde une erreur générique. `/studio` exige ROLE_VERIFIED, y compris pour une ancienne session.
- Aucun changement de schéma ni migration supplémentaire. Mailpit local déjà configuré reste utilisé pour les confirmations. Guide comptes, README, architecture, décisions et AGENTS mis à jour.
- `docker compose exec php php scripts/check.php` : sortie 0 ; migrations test à jour, schémas dev/test conformes, 18 YAML / 7 Twig / conteneur valides, PHPStan niveau 5 sans erreur, CS-Fixer 0 correction sur 23 fichiers, **16 tests / 145 assertions** réussis sous PHP 8.4.26.
- Nouveaux tests : refus avant confirmation, statut non révélé avec un mot de passe incorrect, reset ne confirmant pas l’adresse, connexion réussie après confirmation, ancienne session non confirmée refusée (403 attendu). Les tests existants restent passants ; les fixtures de connexion utilisent maintenant des comptes confirmés.
- `npm run check` et `npm run build` : sortie 0 ; mêmes avertissements de ressources publiques et taille Three.js que la passe précédente. Lint Twig final relancé après ajout du style de lien : 7 templates valides. `git diff --check` : sortie 0.
- Pas de nouveau parcours navigateur exécuté pour cet ajustement : validation du parcours et du lien via les tests HTTP Symfony. Aucune fonction bibliothèque ajoutée ; le studio conserve son écran vide.

---

# Vérification — 14 octobre 2026, V1 lot comptes et curseur

## Résultats exécutés
- Migration Version20261014211700 relue puis appliquée en dev et dans shortflow_test : tables app_user/account_token et index, sans SQL modifiant waitlist_entry. Bases/volume historiques conservés ; aucune suppression de données dev.
- `docker compose exec php php scripts/check.php` : sortie 0 après correction d’une assertion trop stricte sur la chaîne Cache-Control. Schémas dev/test conformes ; 18 YAML, 7 Twig et conteneur valides ; PHPStan niveau 5 sans erreur ; CS-Fixer dry-run : 0 correction sur 22 fichiers ; PHPUnit : **14 tests / 120 assertions**, PHP 8.4.26. L’erreur CSRF journalisée pendant le test de logout invalide est attendue (403).
- Tests comptes : accès anonyme interdit, email normalisé, mot de passe haché, doublon neutre, validation/CSRF, connexion insensible à la casse, logout CSRF, throttling, confirmation explicite unique, usage distinct reset/verify, expiration, récupération neutre, reset limité au propriétaire, invalidation des liens et session, origine de confiance des emails et stockage par empreinte. Tests uniquement sur shortflow_test, migration appliquée avant PHPUnit.
- `npm run check` : sortie 0, inclut les contrôleurs account-token et cursor. `npm run build` : sortie 0, relancé après les derniers styles. Avertissements : chemins publics /media et /cursor*.svg résolus au runtime ; chunk Three.js existant 704,89 kB / 181,19 kB gzip, chargé dynamiquement.
- `composer audit` : aucune vulnérabilité connue signalée. `npm audit` : sortie 0, aucune vulnérabilité ; première tentative bloquée par DNS sandbox, seconde avec accès réseau autorisé réussie.
- `lint:twig templates` relancé après les derniers titres et textes : 7 templates valides. `git diff --check` : sortie 0.
- Navigateur : inscription fictive locale, email effectivement reçu dans Mailpit, connexion, état vide, confirmation, disparition du bandeau après reconnexion et déconnexion vers la landing vérifiés. Le fragment du lien est transféré dans le formulaire et effacé de l’URL ; code masqué une fois rempli.
- Rendus observés desktop (viewport initial 963 px) et mobile 390×844 : aucun débordement mesuré. Dashboard mobile : scrollWidth 375 px pour 390 px de viewport avec barre de défilement ; connexion : 390 px. Console sans erreur/warning au contrôle du studio.
- Curseur : SVG original anthracite avec contour clair, halo violet dont la position suit la souris observée, curseur texte conservé dans les champs. Tab déplace le focus et masque le halo. Aucun élément du halo n’intercepte les clics.
- Docker en fin de lot : PHP sur 127.0.0.1:8000, Mailpit sur 127.0.0.1:8025, PostgreSQL sans port hôte. Service superflu ajouté par la recette Mailer arrêté et retiré ; aucune donnée existante concernée.

## Limites et périmètre
- Intermittences de l’outil navigateur (timeouts de navigation/capture) ; les actions concernées ont été vérifiées ensuite via l’état de page. Pas de campagne multi-navigateurs, appareil physique ou benchmark de performance. Halo tactile/mouvement réduit désactivé dans le code ; ces préférences n’ont pas été simulées de bout en bout dans cette passe.
- Compte fictif qa-studio@example.test créé dans la base dev pour le parcours navigateur et conservé. Mailpit est non persistant et réservé au local ; aucun email extérieur, service payant, compte fournisseur ou publication réelle.
- Bibliothèque, calendrier, IA, OAuth et Stripe non implémentés. Pas de suppression de compte autonome ni de purge automatique des codes dans ce lot ; exigences de production documentées. Aucun commit, push ou déploiement.
- Guide pédagogique : docs/accounts.md. Roadmap/architecture/décisions/AGENTS cohérents avec V1 progressive et génération BYOK. L’hybride reste une piste future.

---

# Vérification — 14 octobre 2026, clarification produit

- npm run check : sortie 0.
- npm run build : sortie 0, puis relancé après le dégagement des noms de réseaux dans le hero. Avertissement existant sur le chunk Three.js et chemins /media résolus au runtime.
- lint:twig templates via Docker : 3 templates valides.
- Navigateur desktop 1280×720 : hero, textes de distribution et parcours en trois colonnes observés. Mobile en iframe 390×844 : hero lisible, lien Explorer le studio fonctionnel ; largeur utile et scrollWidth 375 px. Pas de test sur téléphone physique.
- Aucun JavaScript, backend, route, entité ni formulaire modifié ; suite PHP complète non relancée pour ces changements de texte et CSS.
- Prompt V1 fourni dans docs/prompt-v1-codex.md ; aucune fonctionnalité V1 développée dans cette passe.
- Capture du parcours : output/visual-qa/v6-product-desktop.png.

---

# Vérification — 14 octobre 2026, passe visuelle 5

## Résultats exécutés
- npm run check et npm run build : sortie 0 après intégration des cinq nouveaux visuels créateurs. Avertissement de taille du chunk Three.js (705 kB, 181 kB gzip), chargé dynamiquement sur desktop.
- lint:twig templates via Docker : 3 templates valides.
- Suite PHP complète exécutée le 13 octobre pendant cette passe : sortie 0, migrations shortflow_test avant PHPUnit, 4 tests / 26 assertions, schémas valides, PHPStan niveau 5, CS-Fixer, YAML et conteneur valides. Aucun changement PHP depuis.
- Hero : cinq plans originaux distincts contrôlés dans le rendu intermédiaire (mode, chat, fiction IA, voitures, football), puis film final installé. WebM : 1 123 508 octets ; durée native observée 14,960 s, muet, lecture mobile active. Téléphone WebGL desktop observé avec le nouveau contenu ; console sans erreur.
- Montage section 01 : quatre plans originaux exclusifs, canvas et timeline synchronisés aux plans 0 → 2 → 1 → 3. Même composite utilisé dans le téléphone desktop. Rechargement desktop en plan 2 : plan et position de section restaurés (top -259 px avant/après).
- Desktop 1280×720, mobile 360×740 et 390×844, tablette 768×844 : contrôles locaux par iframe, aucun débordement horizontal. Dernier contrôle à 390 : largeur utile 375 px, scrollWidth 375 px.
- Navbar/footer pleine largeur mesurée. Hint de scroll et compteur flottant absents. Bouton de pause masqué hors du récit, footer dégagé.
- Redimensionnement 390 → 1280 → 390 : états mobile → webgl → mobile. Pause/reprise au clavier, mouvement réduit et absence de WebGL simulés : fallback conservé, montage statique compact, aucune vidéo chargée en mouvement réduit.
- Captures : output/visual-qa/v5-hero-desktop.png, v5-hero-mobile.png, v4-edit-mobile.png, v4-edit-desktop.png, v4-footer-desktop.png.

## Périmètre et limites
Les plans sont des images originales animées par cadrage et fondus, pas des clips générés avec mouvement des sujets. Pas de test sur appareil physique, benchmark FPS, Lighthouse ou campagne multi-navigateurs. Backend inchangé. Changements non commités depuis f821430. Pages temporaires de QA retirées de public en fin de passe.

---

# Vérification — 13 octobre 2026, passe visuelle 4

## Sauvegarde avant travaux
Commit f821430 : refine VydFlow framing, chapter artwork and mobile finale. Toutes les retouches préexistantes ont été sauvegardées avant les nouveaux travaux ; les changements ci-dessous restent non commités.

## Contrôles exécutés
- npm run check : sortie 0, inclut désormais horizon-sequence.js.
- npm run build : sortie 0 après les six nouvelles couvertures. URLs /media résolues au runtime ; avertissement Three.js 705 kB / 181 kB gzip, importé uniquement en mode desktop.
- php scripts/check.php via Docker Compose : sortie 0. Migrations shortflow_test appliquées avant PHPUnit ; schémas dev/test conformes ; 17 YAML, 3 Twig et conteneur valides ; PHPStan sans erreur ; CS-Fixer 0 correction sur 11 fichiers ; PHPUnit 4 tests, 26 assertions.
- lint:twig templates relancé après les dernières couvertures : 3 templates valides.
- git diff --check : sortie 0 après retrait des espaces résiduels.
- Navigateur intégré desktop 1280×720 : séquence observée aux plans 0, 3, 4, 5, et retour au plan 1 ; écran 3D maintenu sur le concept IA pendant la séquence. Le timecode et la timeline suivent le scroll. Rechargement en cours de séquence : état restauré.
- Nouvelle bibliothèque : six couvertures originales distinctes chargées. Conclusion : aucun bandeau creative-transition dans le DOM ; écran final 5 et CTA visibles ensemble.
- Hero : nouveau film observé et déplacement gauche/droite du téléphone vérifié ; aucune erreur de console desktop. Film natif mobile : durée observée 7.964 s, muet.
- Responsive dans iframe locale : 360×740, 390×844, 768×844, sans débordement horizontal mesuré. Lecteur mobile sticky de 567 px, contenu dans le viewport ; passage plan 0 → 3 → 1 au scroll arrière.
- Redimensionnement 390 → 1280 → 390 : états mobile → webgl → mobile confirmés.
- Pause puis reprise par Entrée : suppression des scènes et de la zone de scroll allongée, puis réactivation contrôlée.
- Mouvement réduit simulé : état static, aucun src vidéo, séquence désactivée, zone compacte. Sans WebGL simulé : état fallback, zone compacte et poster conservé.
- Treize nouvelles images imagegen, 26 WebP (1023 KiB au total, images + miniatures), chargement des six plans à proximité de la section. Nouveau film hero-v2.webm : 647 KiB. Posters 0, 3 et 4 régénérés.
- Captures : output/visual-qa/v3-horizon-desktop.png, v3-horizon-mobile.png, v3-library-desktop.png, v3-hero-desktop.png et v3-finale-desktop.png.

## Limites
Séquence de six images originales reliées par fondus ; aucune génération vidéo métier disponible. Pas de benchmark FPS, Lighthouse, téléphone physique ni campagne multi-navigateurs. Les contrôles responsive et de préférences sont des simulations locales.
Aucun changement de backend, route, entité ou migration, aucune donnée du développeur supprimée. Pages de QA temporaires retirées de public en fin de travail.

---

# Vérification — 13 octobre 2026, passe visuelle 3

## Sauvegarde
Version précédente commitée avant toute retouche : 5fc4b84, feat: preserve VydFlow immersive landing v1. Les améliorations de la passe 3 restent dans le working tree pour revue.

## Résultats exécutés
- npm run check : sortie 0.
- npm run build : sortie 0, après nettoyage des anciennes compositions CSS. Avertissements habituels sur les chemins /media servis au runtime et le chunk Three.js importé dynamiquement (705 kB, 181 kB gzip).
- php scripts/check.php via Docker Compose : sortie 0. Migrations appliquées à shortflow_test avant PHPUnit ; schémas dev/test conformes ; 17 YAML, 3 Twig et conteneur valides ; PHPStan sans erreur ; CS-Fixer : 0 fichier à corriger sur 11 ; PHPUnit : 4 tests, 26 assertions.
- Navigateur intégré, desktop 1280×720 : arrêt du téléphone presque frontal, entièrement à l’intérieur du cadre. Capture à dataset.capture=1.000. Dernier écran contrôlé : photographie en haut, texte isolé sur fond opaque, aucun chevauchement de légendes.
- Scroll rapide en avant et en arrière, rechargement en fin de page : écran 5 restauré. Pause puis reprise au clavier : canvas libéré et légende du cadre masquée.
- Responsive dans une iframe locale : 360×740, 390×844, 768×844. Aucun débordement horizontal mesuré ; téléphone final et titre séparés par plus de 60 pixels à 360 et 390. Mode mobile HTML actif sous 1001 px.
- Simulation sans WebGL à 1280 px : state=fallback, aperçu de montage HTML présent.
- Mouvement réduit simulé : aucune scène immersive et aucune vidéo chargée (quatre src null).
- git diff --check : sortie 0 après suppression d’un espace résiduel.
- Console desktop : aucune erreur lors du contrôle.
- Captures : output/visual-qa/v2-framing-desktop.png, v2-finale-desktop.png et v2-finale-mobile.png.
- Nouveaux médias : deux images originales imagegen compressées, deux miniatures ; posters 4 et 5 régénérés depuis screen-composer. Les films existants sont conservés.

## Limites
Pas de téléphone physique, benchmark FPS, Lighthouse ou campagne multi-navigateurs. Les simulations ne remplacent pas les réglages d’un appareil réel.
Aucun changement de route, entité, migration ni service métier. Les identifiants historiques PostgreSQL et les données du développeur sont conservés. Aucune mise en ligne.

---

# Vérification — 13 octobre 2026, passe visuelle 2

## Résultats réellement exécutés
- npm run check : réussi, point d’entrée, contrôleur et screen-composer.
- npm run build : réussi après la dernière correction responsive.
- docker compose exec php php scripts/check.php : sortie 0. Migrations appliquées avant PHPUnit sur shortflow_test ; schémas dev/test conformes ; 17 YAML, 3 Twig et conteneur valides ; PHPStan sans erreur ; CS-Fixer sans correction sur 11 fichiers ; PHPUnit 4 tests, 26 assertions.
- lint:twig relancé après le dernier ajustement des liens : 3 templates valides.
- git diff --check : sortie 0, uniquement avertissements de conversion LF/CRLF de l’environnement Windows.
- Navigateur intégré : contrôle desktop 1440×900, mobile réel dans une iframe 360×740 et 390×844, tablette 768×844. Pas de débordement horizontal dans les trois tailles étroites après correction. Sous 1001 px, mode HTML sans import de Three.js.
- Contrôle du film dans le téléphone, de l’écran de montage, des versions par réseau et de la bibliothèque. Six miniatures chargées. Trois vidéos de destinations différentes. Sur mobile, seule la vidéo visible est chargée/lue ; les autres restent sans src tant qu’elles ne sont pas vues.
- Scroll lent et aller-retour rapide contrôlés. Rechargement sur #organisation : écran de bibliothèque correctement restauré. Changement mobile/tablette et retour desktop contrôlés.
- Clavier : premier Tab sur le lien Aller au contenu. Liens de découverte et CTA testés ; champ email accessible. Pause : WebGL désactivé et aria-pressed=true ; reprise contrôlée.
- Simulations locales : mouvement réduit (aucune vidéo chargée/lue), getContext WebGL indisponible (fallback statique) et autoplay refusé (posters conservés). Les outils temporaires sont retirés de public après vérification.
- Console desktop finale : aucune erreur. L’erreur Three.js provoquée volontairement dans la simulation sans WebGL est attendue et suivie du fallback.
- Captures : output/visual-qa/resonance-desktop.png et hero-mobile.png.

## Observations et limites
Vite signale les URLs /media servies au runtime (publicDir=false), et un chunk Three.js de 705 kB / 181 kB gzip, importé dynamiquement uniquement sur desktop. Les assets correspondants ont été chargés dans le navigateur.
Pas de benchmark de FPS, de score Lighthouse, de test sur un appareil physique ni de campagne multi-navigateurs. Le mouvement réduit et l’autoplay refusé ont été simulés dans une page de QA locale ; ce contrôle ne remplace pas les paramètres d’un vrai appareil.
Les six images sont originales, créées avec imagegen. Les quatre vidéos sont des montages animés de ces images, rendus localement en canvas/MediaRecorder, et non des séquences générées par un modèle vidéo.
Les identifiants PostgreSQL historiques et le volume existant sont conservés. Aucun schéma ni fonctionnalité métier ajouté. Aucune donnée du développeur supprimée.

---

## Historique de la phase 1

# Vérification — 12 octobre 2026

## Résultats exécutés
- Dossier initial vide ; Git initialisé ; commit initial demandé par le propriétaire.
- Docker Compose build et up -d : réussis ; PostgreSQL healthy et PHP démarré.
- Symfony about : 7.4.20 LTS ; PHP conteneur 8.4.26.
- Deux migrations appliquées en dev et test ; la seconde aligne le nom d’index attendu par Doctrine.
- doctrine:schema:validate : mapping et schéma conformes en dev et test.
- php scripts/check.php dans le conteneur : code de sortie 0.
- Lint YAML : 17 fichiers ; Twig : 3 templates ; conteneur : valide.
- PHPStan niveau 5 avec extension Doctrine : aucune erreur.
- PHP-CS-Fixer dry-run : aucune correction restante, 11 fichiers.
- PHPUnit : 4 tests, 23 assertions, réussis sous PHP 8.4.26. Pages accessibles, CSRF présent/obligatoire, email invalide rejeté, persistence, doublon normalisé, honeypot et limiteur HTTP 429.
- npm run check : syntaxe JavaScript valide.
- npm run build : réussi ; ressources compilées dans public/build.
- composer audit et npm audit : aucune vulnérabilité connue signalée à l’exécution.
- Navigateur intégré : page Symfony chargée ; smartphone WebGL visible ; console sans erreurs/warnings lors du contrôle initial.
- Contrôle visuel desktop et mobile 390×844 ; accès au formulaire par CTA ; aucun débordement horizontal mobile mesuré. Titre mobile ajusté pour éviter le chevauchement initial.

## Limites et observations
Le build Vite avertit d’un chunk Three.js de 705 kB environ (181 kB gzip). Il est importé dynamiquement à proximité de la scène ; il n’est pas utilisé lorsque le mouvement réduit ou l’économie de données sont détectés. Aucun score Lighthouse ni benchmark sur téléphone physique : ne pas annoncer de garantie de fluidité.

Le fallback HTML/CSS, le code reduced motion et le nettoyage GPU sont présents, mais la perte matérielle WebGL, la lecture d’une vidéo personnelle et les préférences d’un vrai appareil n’ont pas été simulées de bout en bout. La vidéo personnelle n’est pas fournie : le poster original est utilisé.

PowerShell fourni échoue au lancement (assembly System.Xml.ReaderWriter manquant). Les commandes ont été exécutées via cmd et Node. Le PHP Windows par défaut est 8.1 ; l’initialisation a utilisé PHP 8.3 installé, puis toute vérification applicative PHP 8.4 Docker. Les restrictions sandbox ont nécessité une exécution autorisée pour Docker, les téléchargements et la compilation Vite sur OneDrive. Aucun blocage final sur ces vérifications.

Configuration exclusivement locale : serveur PHP de développement et debug actifs. Aucune mise en ligne, dépense, intégration cloud ou fonctionnalité future engagée. Politique de confidentialité à compléter avant collecte publique.
