# Mini-éditeur : timeline libre, habillage et médias — 22 octobre 2026

## Objectif

Monter les vidéos importées et les résultats IA depuis les mêmes entrées Vidéos/publication, sans imposer un logiciel séparé. Ce lot autorisé complète les outils de base : placement temporel libre, textes personnalisables, cadrage, effets simples et plusieurs musiques. Il ne lance aucune génération payante ni publication sociale.

## Utilisation

- Glisser un clip dans un espace libre. Maj + clic sélectionne plusieurs clips ; glisser ou utiliser les flèches les déplace ensemble. Flèches : 0,1 s, Maj + flèches : 0,5 s. Les superpositions vidéo sont refusées sur la piste principale. Les espaces sont exportés avec la couleur de fond choisie.
- Retirer laisse un espace. Retirer et rapprocher décale les clips suivants de la durée retirée. Fermer les espaces réassemble les clips. Dupliquer ajoute une copie en fin. Zoom et aimantation facilitent les coupes ; Tout voir rétablit la vue complète.
- Sélectionner un texte dans l’aperçu pour le déplacer ou utiliser sa poignée de taille/largeur. Couleur libre, six familles de polices (Sans, Serif, Mono, Anton, Bebas Neue, Oswald), gras/italique, alignement, fond, contour, ombre et opacité. Les trois presets restent personnalisables. Enregistrer le style le rend réutilisable dans le compte (12 styles, même nom remplacé). Les sous-titres ont un style global et gardent leurs positions individuelles ; deux sous-titres voisins peuvent être fusionnés.
- Cadrage et effets du clip : zoom/déplacement, rotation par quarts de tour, miroir, vitesse 0,25× à 4×, luminosité/contraste/saturation, filtres chaud/froid/vif/doux/noir et blanc avec intensité, fondus d’entrée/sortie.
- Audio : jusqu’à huit fichiers musicaux privés et 32 fragments, avec leurs propres positions/coupes/volumes/fondus. Une forme d’onde issue du vrai fichier se calcule en arrière-plan. Les musiques ne sont pas envoyées à la transcription des paroles. Pas de catalogue musical tiers ni de musique sans autorisation intégrée.
- Ajouter une image JPEG/PNG/WebP (10 Mo, 40 MP maximum), ou un carton avec fond uni et texte modifiable. Durée initiale de 1 à 30 s. L’image devient un clip privé normalisé et bénéficie des mêmes réglages.

## Fichiers et responsabilités

- `EditTimeline`, `assets/edit-timing.js` : même règle de position `at`, vitesse `rate` et durée. Les projets sans ces champs conservent leur assemblage continu. `CaptionTiming` remappe les paroles quand un passage source est déplacé ou accéléré.
- `VideoEdits` : validation propriétaire, positions finies, absence de chevauchement vidéo, bornes et sauvegarde transactionnelle avec révision. `VideoEdit` conserve la recette en JSON et calcule sa durée.
- `EditClipStyle` : liste fermée des effets, validation et filtres FFmpeg. `VideoEditRenderer` crée les espaces colorés/silencieux, normalise les clips puis applique textes et mixage. `EditAudio` utilise la même chronologie pour les paroles.
- `EditTypography`, `EditTextLayers`, `EditCaptions` : typographie validée et rendu ASS ; les caractères de contrôle utilisateur restent neutralisés. Les polices locales de `public/fonts/editor` sont chargées par le navigateur et libass. Licences livrées avec les fichiers ; aucune requête Google Fonts à l’affichage.
- `User.editorStyles` et migration `Version20261022010000` : styles privés du compte. `VideoEdit.musicFiles` et migration `Version20261022020000` : plusieurs musiques, avec reprise des anciennes sans réécriture destructive.
- `EditWaveforms`, `BuildEditWaveform` et son handler : cache privé de 256 pics d’amplitude, calcul FFmpeg dans la file Messenger `video_edits`, propriétaire contrôlé avant accès. Une absence de son reste explicite, sans dessiner de fausses données. Cache régénérable ; sa purge périodique reste une amélioration d’exploitation.
- `EditStillImages` : validation du fichier réel, conversion locale et passage par l’import sécurisé existant. L’image est conservée dans Vidéos même si son ajout au projet échoue ensuite.
- `VideoEditController`, `templates/edit/*`, `edit_controller.js`, `editor-desk.css` : formulaires CSRF, réponse privée, panneau contextuel, manipulation directe, préécoute, historique et sauvegarde existante. `max_input_vars=5000` évite la troncature des grandes recettes.

## Flux Symfony à comprendre

Stimulus modifie une recette, pas les fichiers sources. Le formulaire Symfony contrôle le CSRF, puis le service métier valide et Doctrine enregistre. À l’export, Messenger transmet un identifiant de projet au worker ; le worker relit la recette et les sources appartenant au compte, puis exécute FFmpeg via des tableaux d’arguments. Le résultat repasse par l’import privé. L’injection de dépendances permet de partager les règles de temps, de typographie et d’audio entre l’enregistrement et le rendu. Une migration ajoute la structure de stockage ; elle ne remplace pas la validation métier.

## Essai manuel

1. Ouvrir Modifier depuis une vidéo importée ou IA ; ajouter un second clip.
2. Laisser un espace, sélectionner les deux clips avec Maj, déplacer le groupe, séparer/dupliquer puis annuler/rétablir. Enregistrer et recharger.
3. Placer un titre en haut, changer police/couleur/largeur et enregistrer son style. Générer les sous-titres sans quitter le montage puis corriger/fusionner un passage.
4. Appliquer un cadrage et un filtre ; ajouter deux musiques, déplacer/couper leurs fragments et écouter les fondus. Ajouter une image ou un carton.
5. Exporter, relire le MP4 et vérifier espace, vitesse, titre, cadrage et mixage. Vérifier aussi téléphone et clavier.

## Limites conservées

32 fragments vidéo maximum, durée totale 179,5 s et export 100 Mo maximum ; huit sources distinctes à la création, ajout ultérieur possible dans ces limites. Une piste vidéo principale, sans superposition d’images animées ; fondus simples vers le fond, sans transition croisée. Pas d’images clés, de téléchargement de polices personnelles, de transitions complexes ou d’éditeur audio professionnel. L’aperçu HTML peut marquer un chargement entre sources ; le MP4 est la référence finale. Les limites de ressources et la file d’export existantes restent appliquées.
