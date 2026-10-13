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
