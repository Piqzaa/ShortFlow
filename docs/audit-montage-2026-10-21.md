# Audit du mini éditeur VydFlow — 21 octobre 2026

## Avis produit

L’éditeur dispose d’un moteur fonctionnel (assemblage, export privé, transcription locale, musique, retour à la préparation), mais les manipulations restent inégales selon les éléments. Les vidéos se coupent et se déplacent ; les autres pistes sont surtout des réglages. Pour répondre à l’usage demandé, chaque bloc doit pouvoir être sélectionné, déplacé dans le temps, raccourci, séparé, dupliqué et supprimé lorsque cela a du sens. La position d’un texte dans l’image est un réglage distinct de son emplacement dans le temps.

L’objectif recommandé est un mini éditeur de vidéos courtes intégré à la publication : vidéo principale aimantée, textes libres superposables, sous-titres synchronisés et éléments audio indépendants. Inutile d’ajouter dès maintenant les effets et la complexité d’un logiciel professionnel.

Méthode : lecture de l’implémentation courante, analyse avec les skills ux-flow-coding et ui-ux-pro-max, comparaison des documentations officielles de CapCut, Canva, Clipchamp et VEED. Les produits concurrents n’ont pas été testés en session connectée ; leur disponibilité peut dépendre de la version et de l’offre. Aucun correctif applicatif, export, transcription ou appel payant lancé pendant cet audit. Les recommandations génériques de couleurs/landing de la base de skill ont été écartées : conserver l’identité existante VydFlow.

## Comparaison des parcours

| Référence | Principe documenté utile | Application à VydFlow |
|---|---|---|
| CapCut | Texte en calque supérieur, placement et taille ajustables | Manipuler le texte sur l’aperçu, avec son propre bloc temporel |
| Canva | Timeline à plusieurs pistes pour médias, éléments et audio ; sous-titres modifiables comme couche temporelle | Distinguer visuellement textes, sous-titres, vidéo et audio |
| Clipchamp | Sélection d’un élément puis propriétés contextuelles ; séparation de l’élément au curseur | Le bouton Séparer agit sur l’élément sélectionné, quel que soit son type |
| VEED | Déplacement horizontal, poignées de durée, zoom et aimantation | Gestes cohérents et précision sans saisir systématiquement des secondes |

Sources officielles :
- [CapCut — text overlays](https://www.capcut.com/resource/free-video-overlay-effects)
- [Canva — vidéo et timeline](https://www.canva.com/help/creating-and-editing-videos/)
- [Canva — sous-titres éditables](https://www.canva.com/help/generate-edit-captions-on-videos/)
- [Clipchamp — édition vidéo](https://support.microsoft.com/en-au/clipchamp/how-to-edit-a-video-in-clipchamp)
- [Clipchamp — édition audio et voix](https://support.microsoft.com/en-us/clipchamp/how-to-add-voiceovers-to-videos)
- [VEED — timeline](https://support.veed.io/en/articles/10804541-what-you-need-to-know-about-veeds-timeline)

## État constaté et écarts

| Domaine | Aujourd’hui dans le code | Manque important |
|---|---|---|
| Vidéo | Réordonner, raccourcir, séparer, retirer, trois formats et cadrage centré | Zoom temporel, déplacement du cadrage, volume par clip ; limite de huit fragments vite atteinte |
| Texte libre | Titre d’introduction lié au nom du projet pendant 2,5 secondes | Véritables textes indépendants, plusieurs blocs simultanés, positions et durées propres |
| Sous-titres | Blocs texte/début/fin, styles blanc ou jaune, clic dans la timeline, export SRT | Déplacer dans l’image, poignées temporelles, séparation/fusion, suppression directe, styles plus souples |
| Musique | Une piste importée, volume global, point de départ dans le fichier, boucle | Position de départ dans le montage, fin propre, plusieurs morceaux, coupes, fondus |
| Son des vidéos | Suit les clips ; un volume global | Volume et sourdine par clip, détachement et montage indépendants |
| Historique | Quinze changements de clips avec leurs sous-titres | Annuler/rétablir pour texte, styles, timing, audio ; conservation de l’état de travail |
| Sauvegarde | Explicite et automatique avant certains liens/imports | Sauvegarde temporisée cohérente, reprise après incident, gestion de conflit entre onglets |
| Traitements | Écran de statut remplaçant l’éditeur, puis rechargement | Retour de traitement directement dans le panneau en conservant la position de lecture |
| Aperçu | Composition navigateur et rendu FFmpeg distincts | Contrat commun pour police, taille, position, retours à la ligne et temps |

La musiqueOffset actuelle désigne le moment lu dans le fichier musical. Ce n’est pas le moment où la musique apparaît dans le montage : celui-ci reste zéro. Deux réglages distincts sont nécessaires.

Le bouton Ajouter un bloc de texte crée aujourd’hui un sous-titre ; il ne crée pas un texte libre superposable. Le validateur interdit le chevauchement des sous-titres : cette règle ne convient pas à une accroche et une légende affichées en même temps.

## Diagnostic de la sortie lors de la génération des sous-titres

Le comportement signalé correspond à l’implémentation :
1. Le bouton soumet le formulaire principal avec intent=captions. Le gestionnaire Stimulus submit affiche un message mais n’intercepte pas la navigation.
2. VideoEditController enregistre la recette, puis VideoTranscriptions place le projet en transcribing et envoie un message Messenger.
3. Le contrôleur redirige vers le même projet. Dans templates/edit/show.html.twig, la branche project.active affiche seulement le statut, à la place de la timeline et du lecteur.
4. edit_watch_controller.js interroge l’état toutes les quatre secondes, puis appelle window.location.reload() à la fin. Le panneau et le curseur ne sont pas restaurés.

Il s’agit donc d’une rupture de contexte produite par l’interface, sans qu’un changement d’URL vers un autre projet soit nécessaire. Le diagnostic est établi par lecture du code ; ce rapport ne prétend pas avoir reproduit un nouvel essai dans le navigateur.

Correction proposée : enregistrer puis lancer la transcription par une requête sans navigation ; conserver le lecteur et la timeline ; afficher préparation audio / transcription / ajout des blocs dans le panneau Sous-titres ; récupérer les blocs structurés en lecture propriétaire et les insérer sans recharger. Garder sélection, panneau, zoom, curseur et textes existants. En cas d’échec, conserver les anciens sous-titres et proposer Réessayer.

Aucun pourcentage inventé : le moteur actuel ne publie pas une progression précise de reconnaissance. Autoriser les réglages qui ne changent pas l’audio ; verrouiller les coupes dépendantes pendant l’analyse, ou rejeter un résultat dont la recette audio a changé. Un état de transcription distinct de l’état d’export simplifie ce comportement.

## Problème de conservation des sous-titres

edit_controller.js vide les lignes lorsque les coupes/positions changent. VideoEdit::revise fait également disparaître les sous-titres si les clips changent. Même séparer un plan sans changer le contenu temporel invalide donc la transcription. Ce comportement protège contre une mauvaise synchronisation mais fait perdre la relecture manuelle.

Comportement cible :
- Séparer un clip sans enlever d’images ne supprime aucun sous-titre.
- Retirer un passage enlève ou raccourcit uniquement les paroles correspondantes.
- Réordonner un clip déplace ses sous-titres associés.
- Les changements de style, position ou volume ne relancent pas une transcription.
- Régénérer propose explicitement de remplacer la transcription existante ; les textes libres restent conservés.

Conserver les repères temporels de transcription par source et les associer aux fragments de montage permet de recalculer les positions finales. Les textes libres utilisent par défaut le temps du projet ; on peut proposer ensuite de les lier à un clip.

## Interactions recommandées

### Textes libres

Ajouter Texte crée un bloc à la position du curseur. Un clic sur le texte dans l’image le sélectionne ; un double clic ouvre sa saisie. Le cadre permet déplacement et redimensionnement, avec guides de centrage et repères de zones sûres pour les réseaux. Proposer Haut / Centre / Bas avant le réglage libre. Réglages essentiels : taille, couleur, graisse, alignement, fond/contour et opacité. Préréglages de lisibilité plutôt qu’un catalogue de polices immense.

Le bloc possède début et fin dans la timeline. Séparer crée deux blocs de même contenu et style, modifiables ensuite. Le titre du projet ne doit plus être le texte d’introduction : nommer un fichier et écrire une accroche sont deux intentions différentes.

### Sous-titres

Style et position communs à tous les blocs par défaut ; possibilité explicite d’ajuster uniquement le bloc sélectionné. Déplacement du groupe sur l’aperçu, puis séparation/fusion de segments, déplacement temporel, poignées de durée et suppression dans la timeline. Une séparation de texte ne peut pas deviner quels mots appartiennent à chaque moitié sans repères de mots : garder les timestamps de mots déjà produits par Whisper rend un découpage cohérent possible. En cas de modification manuelle, demander ou permettre de corriger la répartition des mots.

### Audio

Chaque élément audio doit avoir une source, une entrée/sortie dans le fichier et une position dans le projet. Le glisser déplace sa position ; les poignées modifient sa durée ; Séparer coupe au curseur. Ajouter volume par élément, sourdine et fondus courts d’entrée/sortie. Deux ou trois pistes visibles suffisent pour voix, musique et quelques effets ; les éléments supplémentaires restent gérables sans imposer une console de mixage.

Le son original reste lié à sa vidéo par défaut. Détacher le son crée un élément indépendant et évite de jouer deux fois le même flux. Détacher ne sépare pas la voix et la musique déjà mélangées dans l’enregistrement : cette dernière fonction demanderait un autre traitement.

Une forme d’onde réelle aide à caler la voix. Elle doit provenir du fichier, être calculée et mise en cache en arrière-plan. Afficher un état de chargement plutôt qu’une décoration imitant le signal.

### Timeline cohérente

Un seul jeu de commandes : sélectionner, déplacer, raccourcir, séparer, dupliquer, supprimer, annuler et rétablir. La commande agit sur la sélection ; avec un son sélectionné, elle ne coupe pas la vidéo dessous. Ajouter zoom +/−, Ajuster à l’écran, aimantation aux bords et au curseur, accès clavier sans intercepter les touches pendant la saisie de texte.

La vidéo principale reste continue : retirer un morceau referme le trou par défaut, en déplaçant ses sous-titres liés. Les éléments autonomes ne changent pas arbitrairement de position ; leur comportement doit être défini et testé. Prévoir dès le départ les règles de liaison, même si l’interface les garde simples.

Sur mobile : aperçu en haut, commandes contextuelles en bas, panneau en feuille, déplacement tactile et zoom accessibles sans dépendre du survol ou du clic droit. Agrandir les zones de capture des poignées sans masquer les petits blocs ; permettre aussi les réglages numériques. Le simple rétrécissement du bureau ne suffit pas.

## Priorités d’implémentation

1. **Fiabilité et continuité** : transcription dans l’éditeur, sauvegarde robuste, sous-titres conservés, états explicites et retour au bon curseur. Définir les règles de temps et d’historique.
2. **Textes et sous-titres manipulables** : séparer les deux objets, position libre dans l’image, début/fin dans la timeline, séparation/duplication/suppression ; appliquer à tous ou au bloc.
3. **Audio manipulable** : position temporelle, entrée/sortie, séparation, volume par élément, fondus, son original lié/détachable. Formes d’onde et aimantation.
4. **Confort et automatisation** : cadrage avec point focal, zoom temporel peaufiné, préréglages d’habillage, recette de montage enregistrable. Petit catalogue musical autorisé dans un lot distinct. Les séries pourront réutiliser la même recette après stabilisation.

La limite de huit fragments doit être remplacée par des limites mesurées : nombre de sources, nombre d’éléments et durée sont des contraintes différentes. Ne pas annoncer un nombre de clips ou une résolution supplémentaire sans mesurer les temps de rendu et la mémoire. Le plafond actuel de rendu à 240 secondes doit aussi être réévalué selon la complexité.

À différer : animations à images clés complexes, masques, colorimétrie avancée, effets multiples, suivi de mouvement, collaboration temps réel et nombreuses pistes vidéo superposées. Un fondu simple pourra venir après la fiabilité des coupes. La transcription locale évite la facturation d’une API mais consomme du CPU et des ressources d’hébergement.

## Mise en œuvre dans la stack actuelle

Conserver Symfony, Twig, Stimulus, Messenger et FFmpeg. Introduire un état d’édition structuré et versionné avec identifiants stables, au lieu de reconstruire toute la recette depuis les champs du DOM. Une petite couche JavaScript de commandes peut gérer les gestes et l’historique, sans migration vers React.

Pour chaque bloc : type, identifiant, source éventuelle, position dans le projet, durée et entrée dans le média. Pour le texte : contenu et style/position ; pour l’audio : volume et fondus. Les coordonnées proportionnelles permettent de garder le placement entre aperçu réduit et export. Les règles de police, retour à la ligne et taille doivent être partagées entre aperçu et rendu.

Les contrôleurs restent responsables d’authentification, propriété et CSRF ; les services valident bornes, sources et limites. Messenger calcule transcription et export sur une recette identifiée. Le résultat n’est appliqué que si la version pertinente correspond. La migration doit convertir les montages existants sans perdre les exports, musiques et réglages.

## Scénario de validation du prochain lot

Sur une vidéo importée et une vidéo IA : ajouter une accroche en haut, placer les sous-titres en bas, déplacer un seul sous-titre, le séparer, retirer une partie vidéo et vérifier la synchronisation restante. Poser une musique à 4 secondes, la couper à 12 secondes, séparer à 8 secondes et régler un fondu. Séparer l’audio original en gardant la vidéo intacte. Annuler/rétablir chaque type de changement, recharger et retrouver la recette. Relancer des sous-titres sans quitter l’éditeur ni perdre les textes libres. Comparer un export réel à l’aperçu sur les positions et les temps, puis revenir à une préparation avec textes et horaire conservés. Vérifier téléphone et clavier, erreurs de transcription, source absente et conflit entre onglets.

La prochaine étape recommandée est donc un lot « continuité de l’éditeur + éléments texte/sous-titres », fondé sur un modèle de temps pouvant accueillir l’audio ; l’extension audio suit sur les mêmes gestes.
