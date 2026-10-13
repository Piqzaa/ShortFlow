# VydFlow — expérience visuelle, passe 3

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
