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
Pointeur fin seulement pour la parallaxe. Rotation interpolée au scroll, aucune capture du scroll. Pas de boucle de rendu permanente avec le poster : rendu sur resize/scroll/pointeur. Densité GPU plafonnée à 1.5. prefers-reduced-motion et économie de données activent le fallback statique. Le mode réduit activé en cours de visite libère la scène. Formats et dimensions explicites évitent le layout shift.

## Vidéo personnelle
Placer un MP4 autorisé dans public/media/cats.mp4 puis ajouter data-video-src="/media/cats.mp4" à la section data-controller="studio". Vidéo muette, playsInline et boucle ; fallback poster si lecture refusée. Fournir un clip vertical léger 1:2, idéalement quelques secondes. Sans attribut, aucune vidéo n’est téléchargée. Le poster SVG original reste remplaçable sans dépendance cloud.
