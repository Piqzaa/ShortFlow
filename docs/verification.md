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
