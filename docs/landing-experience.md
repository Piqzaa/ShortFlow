# VydFlow — expérience visuelle, passe 5

## Objectif
Faire découvrir le futur studio par ses contenus : chaque chapitre apporte un nouvel écran et un nouvel usage. La bibliothèque, le calendrier, les montages et le studio IA restent des illustrations. Seule la liste d’attente est fonctionnelle.

## Fichiers et responsabilités
- templates/landing/index.html.twig : HTML accessible, scènes, médias distincts, aperçus et formulaire Symfony existant.
- assets/controllers/studio_controller.js : cycle de vie Stimulus, scène Three.js, progression GSAP unique, changement d’écran et lecture des vidéos visibles.
- assets/screen-composer.js : dessine six écrans de démonstration sur canvas. Ce module ne réalise aucun montage métier et n’enregistre aucune donnée utilisateur.
- assets/styles/experience.css : direction artistique des médias et composition mobile indépendante, chargée après les styles de base.
- public/media/creators : huit images originales et leurs miniatures compressées WebP.
- public/media/screens : six affiches d’écran, utilisées dans les versions HTML et statiques.
- public/media/films : quatre montages WebM, muets, à partir des images originales.
- scripts/prepare-media.py : compression des sources locales avec Pillow. Sources de travail dans output/media/source, ignorées par Git.

## Flux d’exécution
1. Symfony traite la route / et rend Twig avec le formulaire protégé par CSRF.
2. Stimulus améliore ce HTML quand le studio entre dans le viewport.
3. Sur mobile et tablette (jusqu’à 1000 px), GSAP anime de petits déplacements ; Three.js et ses textures ne sont pas chargés. Le téléphone du hero est en HTML et présente la vidéo native si le navigateur l’autorise.
4. Sur desktop, une seule progression de scroll interpole la position, la rotation, la taille et l’environnement du téléphone. Elle pilote aussi un fondu entre les écrans : film → montage → versions → bibliothèque → concept IA → conclusion.
5. Les vidéos de destinations sont chargées au moment où elles deviennent visibles et arrêtées hors champ. Le film du téléphone est arrêté lorsqu’un écran d’interface est montré. requestVideoFrameCallback évite de redessiner à 60 Hz une vidéo à 24 images/s.
6. Pause, changement de breakpoint, mouvement réduit et déconnexion libèrent les observateurs, les timelines, les vidéos et les ressources GPU. Le contenu HTML reste disponible.

## Mise en scène mobile
Hero compact avec CTA direct. Téléphone de montage en HTML avec inclinaison et curseur de timeline au scroll. Trois destinations successives de taille lisible. Bibliothèque de six créations en deux colonnes, puis calendrier complet. Instruction IA, résultat et timeline séparés. Transition photographique exclusive, puis téléphone de conclusion en HTML avec un écran sans légendes superposées.

## Médias et provenance
Les six images ont été générées pour VydFlow avec l’outil imagegen : danse contemporaine en studio ; objet en verre violet ; escalier architectural ; coulisses de tournage ; portrait créatif ; paysage volcanique. Personnages fictifs, aucun témoignage produit. Aucune image de Lusion reprise.
Les vidéos sont des montages de photos avec recadrages animés et typographie, rendus localement via canvas et MediaRecorder. Ce ne sont pas des prises de vue animées ni des vidéos générées par un modèle vidéo. Le résultat conceptuel IA de la landing est une illustration, pas une fonctionnalité disponible.

## Comprendre Symfony
Twig garde le contenu lisible et le formulaire utilisable avant le JavaScript. Stimulus se connecte à un élément data-controller et améliore sa présentation. Les fichiers JS ne deviennent pas des services PHP : les validations, le CSRF et la persistance restent du côté de Symfony. Cette passe ne change ni les routes ni les entités ni les migrations.

## Tester
Exécuter npm run check et npm run build, puis docker compose exec php php scripts/check.php. Ce dernier applique les migrations de la base de test avant PHPUnit. Les tests destructifs vérifient shortflow_test, le nom historique conservé pour les données locales.
Ouvrir http://localhost:8000, vérifier 360/390/768/1440 px, suivre chaque chapitre et regarder le changement du contenu du téléphone. Tester les trois vidéos, les ancres, la pause, le clavier, un aller-retour rapide, le redimensionnement et un rechargement sur #organisation. Vérifier aussi le poster si autoplay ou WebGL est refusé et le contenu statique en mouvement réduit.

## Passe 3 : cadrage et renouvellement visuel
La version précédente est conservée par le commit 5fc4b84. Cette passe corrige trois points :
- L’impulsion : les quatre repères sont désormais des LineSegments Three.js dans la même caméra que le téléphone. Le téléphone arrive presque de face, réduit à .86, entièrement contenu dans les repères. La pose est maintenue pendant 28 % du segment suivant. Le léger suivi du curseur disparaît durant ce cadrage pour stabiliser la composition.
- Conclusion : drawScreen(5) dessine une image sans texte dans la partie supérieure, puis un panneau opaque de 390 pixels. Toute la typographie repose sur ce panneau. Le film du hero est arrêté dès la sortie du premier écran et ne redémarre plus en conclusion.
- Variété : manta.webp et river.webp sont deux créations originales supplémentaires produites avec imagegen. La raie de verre remplace l’objet déjà utilisé dans les réseaux et la bibliothèque ; la rivière remplace la planche de six images répétées et apparaît dans le dernier écran. Les rubans métalliques sont atténués différemment à chaque chapitre.

### Nouveaux médias
Images verticales WebP 720×1279, miniatures dans une boîte maximale de 270×480. Sources PNG de génération conservées dans output/media/source (ignoré).
- manta : raie translucide en verre irisé, océan de nuit, faisceaux sous-marins bleus et reflets violets, composition verticale sans texte. Utilisation : aperçu IA, timeline et écran 4.
- river : photographie cinématographique aérienne d’une rivière argentée traversant un canyon de basalte noir, aube pêche et lilas, composition verticale sans texte. Utilisation : transition, écran 5 desktop/mobile.

Le script scripts/render-flow-film.html est synchronisé avec le compositeur. Les posters 4 et 5 ont été régénérés depuis ce compositeur dans le navigateur ; les quatre films existants restent identiques.
Aucun service Symfony supplémentaire : Twig rend toujours les aperçus et le formulaire, Stimulus ne fait qu’améliorer la présentation. Le cadre 3D est libéré avec les autres géométries et matériaux lors de cleanup().

## Passe 4 : séquence illustrée et continuité
Sauvegarde avant travaux : f821430. Les nouvelles retouches restent séparées de ce commit.

### Objectif et médias
Treize nouvelles images originales ont été produites avec l’outil imagegen intégré :
- public/media/horizon/horizon-0.webp à horizon-5.webp : six plans de la même raie de verre, avec six miniatures. Apparition, approche, déploiement, passage dans la lumière, virage, éloignement. Images 540×960 au maximum ; miniatures 135×240.
- public/media/creators/craft.webp, surf.webp, street.webp, sound.webp, taste.webp, botanic.webp et kinetic.webp, avec leurs miniatures : céramique, surf à l’aube, ville après la pluie, studio musical, cuisine, botanique et ruban métallique violet.
Les six couvertures de la bibliothèque sont désormais propres à ce chapitre. Le hero utilise exclusivement le ruban, dans public/media/films/hero-v2.webm (montage de photographie rendu localement). Le paysage de rivière reste uniquement dans le téléphone final : le bandeau HTML a été supprimé.
Les prompts exacts sont versionnés dans docs/media-prompts-pass-4.json. Les PNG source restent dans output/media/source, ignoré par Git. Les fichiers WebP sont les livrables de production.

### Composants et flux
- assets/horizon-sequence.js : charge les six images et dessine un fondu entre deux plans voisins. Il ne dépend ni de Three.js ni de Symfony et n’implémente aucune génération IA métier.
- studio_controller.js : attend la proximité de l’aperçu avant de charger les plans (IntersectionObserver, marge 600 px). Une progression GSAP avec scrub pilote simultanément le canvas HTML, la timeline, le timecode et l’écran 3D. Le scroll arrière suit la même progression. Le téléphone attend la fin de ce chapitre avant d’entamer sa pose finale.
- screen-composer.js : reçoit les mêmes plans et la même progression pour dessiner l’aperçu IA dans le téléphone. Les écrans 0, 3 et 4 ont été régénérés pour les fallbacks HTML.
- Twig : conserve une image initiale et six miniatures lisibles sans JavaScript. Le canvas est décoratif (aria-hidden) ; les textes de disponibilité restent en HTML. Le formulaire existant est inchangé.
- experience.css : le lecteur reste visible dans une courte zone sticky, adaptée au mobile. Cette zone n’est allongée qu’après activation JS. En pause, en mouvement réduit, en économie de données ou après échec WebGL, elle redevient compacte et statique.
- Mouvement du hero : rotations limitées à environ ±10° en Y et ±6° en X, translation maximale de .28/.16 unité, amortissement .75 s. La liberté diminue jusqu’à zéro en quittant le hero. Les tweens et écouteurs sont nettoyés lors de cleanup().

### Comprendre et tester
Symfony continue de rendre Twig sur la même route, avec la même validation et le même CSRF. Le canvas est seulement une amélioration visuelle gérée par le cycle de vie Stimulus connect/disconnect. Aucun service PHP, entité ou migration ajouté.
Exécuter npm run check (inclut le nouveau module), npm run build et php scripts/check.php dans le conteneur. Dans le navigateur, suivre La vision, défiler lentement puis à rebours : le timecode et le plan actif doivent suivre le scroll. Vérifier mobile 360/390, tablette 768, retour desktop, pause/reprise, mouvement réduit et sans WebGL. Les images clés créent une impression de séquence ; il ne s’agit pas d’une vraie vidéo calculée par une IA.

## Passe 5 : montage synchronisé et contenus créateurs
### Objectif
Faire défiler cinq contenus variés dans le hero, et faire réellement avancer le montage de la section 01 avec sa timeline. Supprimer les indications flottantes devenues inutiles et étendre la navigation et le footer.

### Fichiers et responsabilités
- public/media/creators/hero-fashion.webp, hero-cat.webp, hero-fiction.webp, hero-cars.webp et hero-football.webp (avec miniatures) : cinq créations imagegen originales réservées au hero. Mode, chat, fiction à compteur frontal, convoi automobile et football. Personnages et situations fictifs ; aucune vidéo concurrente reprise.
- public/media/edit/edit-0.webp à edit-3.webp (avec miniatures) : quatre étapes originales d’un oiseau en papier irisé, exclusivement utilisées dans la section 01.
- public/media/films/hero-v4.webm : boucle locale de 15 secondes, cinq plans avec recadrages et fondus. Les textes correspondent aux thèmes. C’est un montage d’images générées, pas une prise de vue ni une génération vidéo métier. Les anciens essais abstraits et films sont conservés pour comparaison mais ne sont plus chargés par le hero.
- assets/edit-sequence.js : charge les quatre images, calcule le plan actif et le timecode, puis dessine le même écran de montage pour le téléphone HTML et le téléphone 3D.
- studio_controller.js : une progression GSAP pilote image, miniature, curseur et texte. Les quatre images sont chargées à proximité de la section ; avant leur disponibilité complète, affiche et curseur restent sur le premier plan. L’espace desktop est réservé avant l’activation 3D pour conserver la position lors d’un rechargement.
- screen-composer.js : réutilise drawEditScreen au lieu de dessiner une interface différente. Le hero présente cinq médias distincts ; les affiches 0 et 1 ont été régénérées.
- Twig et experience.css : navbar et footer à 100 % de largeur, avec 32 px de marge intérieure desktop / 20 px mobile. Indication scroll, flèche, compteur flottant et barre associée supprimés. Les titres numérotés des chapitres restent présents. Le bouton d’animation est masqué lorsque l’expérience sort du viewport, pour libérer le footer.

### Flux et accessibilité
Symfony rend toujours le même HTML et le même formulaire CSRF sur les routes existantes. Stimulus connecte les aperçus ; GSAP convertit la progression du scroll en une valeur de 0 à 1. editState choisit un des quatre plans (coupes franches comme une timeline de montage), et drawEditScreen dessine ce plan dans les deux téléphones.
Sur mobile, téléphone et timeline restent ensemble dans une zone sticky, puis quittent naturellement la section. En pause, en mouvement réduit, en économie de données ou en fallback, cette zone redevient compacte. Observateurs, contextes GSAP, images référencées et canvas sont nettoyés lors de cleanup().
Cette interface est une illustration, pas une fonctionnalité d’édition ajoutée au SaaS. Aucun nouveau service PHP, contrôleur, entité ou migration.

### Tester et comprendre
Exécuter npm run check (inclut edit-sequence.js), npm run build et php scripts/check.php dans le conteneur. Sur mobile, défiler dans L’impulsion : image, plan actif et timecode doivent avancer ensemble puis revenir en arrière. Vérifier le dernier plan avant La résonance et constater que leurs médias diffèrent.
Contrôler la boucle de 15 secondes dans le hero, la navigation pleine largeur, la pause/reprise, les largeurs 360/390/768/1280, le mouvement réduit et le rechargement au milieu du montage.
Prompts exacts : docs/media-prompts-creator-hero.json pour le hero final et docs/media-prompts-pass-5.json pour le montage et l’essai abstrait précédent. Génération via l’outil imagegen intégré. Sources PNG dans output/media/source (ignoré), images WebP dans public/media/edit et public/media/creators.

## Clarification produit — 14 octobre 2026
Le hero nomme les trois réseaux et le studio précise le parcours importer → préparer → publier. Les textes de distribution décrivent le choix des destinations. La publication reste présentée comme un objectif V1 conditionné aux autorisations ; le calendrier est désormais indiqué pour une étape ultérieure. Le HTML Twig porte ces textes : aucun changement de route, formulaire, service PHP ou animation. Le parcours en trois étapes utilise une grille CSS, empilée sur mobile. Tester la lisibilité du hero et du studio à 360/390/1280 px. Prompt de lancement de la V1 : docs/prompt-v1-codex.md.
