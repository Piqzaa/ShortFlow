# Design system

## Identité
Studio créatif sombre, composition éditoriale et géométrie abstraite originale. Inspiration de narration immersive : https://lusion.co/ ; aucun élément graphique ou code repris.

## Tokens
- Fond : #09090d ; surface : #111116 ; texte : #f4f2f8 ; secondaire : #a4a2b0.
- Accent : #ac8aff ; hover : #c6adff ; lignes : blanc à 11 %.
- Familles : Space Grotesk 500 pour titres, DM Sans 400/500/600/700 pour texte. Polices auto-hébergées via Fontsource, licence OFL incluse dans les paquets. Fallback Arial/sans-serif.
- Titres desktop : 108/68/54 px ; mobile : 52/44/38 px. Corps 16 px desktop, 14 px mobile. Petits libellés réservés aux métadonnées.
- Espacement : base 8 px, paliers 8/16/24/32/40/48/64/72/96/110.
- Conteneur : 1320 px maximum, marges 48/28/20 px selon viewport.
- Breakpoints : 760 px, 1100 px, 1600 px.
- Rayons : bouton/input 7 px ; aperçu 15 px ; carte 18 px ; badge 30 px.
- Ombres : diffuses, noires, réservées au téléphone et à l’aperçu studio.

## Composants
Bouton violet sombre au texte foncé, focus visible de 2 px, hover légèrement éclairci et translation de 2 px. Liens textuels soulignés au CTA. Formulaire avec labels persistants, erreurs serveur et confirmation role=status. Cartes réservées aux destinations et à l’aperçu ; sections éditoriales ouvertes. SVG pour le poster et favicon ; symboles typographiques pour la navigation illustrative.

## Mouvement et performance
Une scène Three.js commune aux six chapitres desktop, attachée par CSS sticky sans interception du scroll. Une progression GSAP unique contrôle toutes les poses et les transitions d’écran. Le téléphone présente successivement un film, le montage, les versions, la bibliothèque, le concept IA et la conclusion. Fondus composés dans un canvas, une seule texture GPU d’écran. Rendu vidéo via requestVideoFrameCallback quand disponible ; sinon boucle WebGL limitée à la lecture. Hors écran et sur les interfaces fixes, pas de boucle permanente. DPR plafonné à 1.5.

Mobile : mise en scène HTML indépendante, film natif dans le hero, inclinaison du téléphone de montage au scroll, destinations successives au format 9:16, bibliothèque en deux colonnes puis calendrier. Three.js n’est pas importé sous 1001 px. Les films natifs sont chargés et lus à proximité du viewport. Pause globale, mouvement réduit, économie de données et autoplay refusé conservent les affiches. Un changement de breakpoint libère la scène et recrée le mode adapté.

## Médias
Six créations originales produites avec imagegen et compressées en WebP, six miniatures, six affiches d’écran et quatre montages WebM de démonstration. Le hero de huit secondes alterne danse, objet et architecture. TikTok, Reels et Shorts disposent chacun d’un montage de cinq secondes différent. Les vidéos sont des montages animés à partir d’images, pas des séquences tournées ou produites par un modèle vidéo.
Voir landing-experience.md pour le flux et la provenance. scripts/render-flow-film.html contient un outil de rendu canvas/MediaRecorder sans service externe : le copier temporairement dans public, l’ouvrir sur localhost:8000, rendre puis télécharger les fichiers ; retirer ensuite la copie publique. scripts/prepare-media.py compresse les sources de travail de output/media/source avec Pillow.
