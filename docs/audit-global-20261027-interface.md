# Audit visuel, responsive et performance perçue — 27 octobre 2026

## Méthode et portée

Audit de l'agent principal sur le commit `d3e4700`, en complément des trois revues spécialisées. Application inchangée. Landing réelle sur loopback ; pages privées rendues par Symfony/KernelBrowser avec un propriétaire synthétique dans `shortflow_test`, migrations vérifiées avant rendu. Les clients HTTP externes sont remplacés par des clients qui refusent tout appel. Le compte de la capture aboutie est supprimé après rendu. Le serveur de consultation sert ensuite ces HTML avec les assets réels, des médias de test et des POST désactivés.

Cette méthode vérifie rendu, interactions locales Stimulus, liens visibles et navigation clavier. Elle ne constitue pas une nouvelle recette des soumissions privées, une mesure réseau publique ou un test avec des créateurs indépendants. Les formulaires de publication et handlers ont été testés dans le lot précédent ; ses 617 tests / 7 470 assertions et 136 tests frontend ne sont pas relancés pour cet audit documentaire. Le montage/export complet n'est pas rejoué visuellement ici ; sa revue repose sur les sources et vérifications antérieures.

## Matrice responsive observée

| Largeur | Écrans contrôlés |
| --- | --- |
| 1280 px | Vidéos, Studio vide, préparation de trois plans, nouvelle série, détail de série, sélection de trois vidéos, préparateur YouTube/Instagram, connexions multiples, profil, import, calendrier vide et publications vides |
| 768 px | Vidéos, Studio, plans, nouvelle série, détail de série, préparateur, sélection, connexions |
| 390 px | Studio, plans, nouvelle série, détail de série, sélection, préparateur, connexions, profil, vidéos vides et import |
| 320 px | Plans, nouvelle série, sélection, préparateur, connexions, profil et calendrier vide |

Landing réelle examinée à 1280 et 390 px. Aucun débordement horizontal durable confirmé sur cet échantillon. Un relevé transitoire sur le profil à 320 px a été infirmé par un nouveau contrôle après chargement des styles ; il n'est pas retenu comme défaut. Les hauteurs sont arrondies et dépendent de l'état des panneaux, du contenu et du viewport.

| Écran | Hauteur à 390 px | Lecture |
| --- | ---: | --- |
| Studio vide | 3 857 px | Beaucoup d'explications avant de terminer une première préparation |
| Préparation de trois plans | 5 688 px | Une action de révision peut être éloignée de son récapitulatif |
| Nouvelle série | 2 947 px | Configuration univers + réglages techniques + images |
| Préparateur deux réseaux | 3 206 px | Longueur en partie justifiée par les réglages propres aux réseaux |
| Sélection de trois vidéos | 3 387 px | Comptes et moments distincts utiles, synthèse par vidéo à améliorer |

La landing mesure environ 7 726 px à 1280 et 10 735 px à 390 dans cette visite animée. Cela décrit sa longueur, pas son temps de chargement ni un taux d'abandon.

## Points solides

- Identité visuelle reconnaissable : anthracite, violet, typographie et hiérarchie cohérents. Landing travaillée ; conserver son intention et ses animations avec fallback.
- Menu mobile présent ; test d'ouverture au clavier avec Entrée, sept liens dont Mon compte, fermeture avec Échap et focus conservé sur le bouton avec contour visible. Les six destinations principales restent disponibles en bas.
- Les contrôles principaux observés sont de taille confortable. La bibliothèque vide présente une première action ; les comptes connectés sont séparés et nommés.
- La pause des animations de la landing fonctionne : bouton changé en « Activer les animations », `aria-pressed=true`. Les règles reduced motion et les chemins fallback existent dans les sources. Le réglage système reduced motion, un lecteur d'écran et le mode sans JavaScript ne sont pas émulés dans cette recette.
- Aucune erreur ni alerte console relevée dans les visites locales observées. Cela ne prouve pas une absence globale d'erreurs.

## Constats et actions proposées

### UI1 — P2 — La préparation reste longue sur téléphone et ordinateur

Preuves : mesures ci-dessus ; formulaire commun `templates/ai/studio.html.twig:9`, plans ouverts par défaut dans `templates/ai/_preparation_plans.html.twig:1`. Même à 1280 px, la préparation de trois plans représente environ 4 865 px de hauteur.

Impact : le créateur doit parcourir beaucoup de contenu pour passer de son idée aux scènes puis à la prochaine action. L'absence de débordement ne suffit pas à rendre un parcours simple.

Proposition : garder un seul écran, trois groupes reconnaissables « Idée », « Scènes », « Récapitulatif ». Présenter chaque plan par résumé français, durée et petite référence ; ouvrir son prompt et ses consignes d'image à la demande. Un résumé de préparation et une action contextuelle peuvent rester accessibles pendant le défilement, sans masquer le contenu ni contourner le devis. Éviter de réduire globalement les polices ou les espacements.

Critères : une personne retrouve l'action suivante et le coût sans reparcourir les trois plans ; les erreurs ouvrent le bon panneau ; texte personnel, ordre, références et validation sont conservés. À mesurer avec des testeurs, pas seulement par la hauteur gagnée.

### UI2 — P2 — Mauvaise section active pendant la préparation d'un épisode

Constat reproduit : une préparation issue d'une série affiche « Vidéos » dans l'en-tête et les navigations. `AiEpisodeController.php:63` fait un `forward` ; `templates/studio/shell.html.twig:4` déduit la section de la route du sous-appel. La revue UX confirme le chemin source.

Impact : retour de contexte ambigu, particulièrement sur mobile. Proposition : passer explicitement le contexte série/Studio au rendu commun plutôt que dépendre seulement de `_route`. Critères : épisode de série → Séries actif ; création indépendante → Studio actif, sans changement des liens de retour et de propriété.

### UI3 — P3 — Un libellé technique anglais « Subjects » reste rendu

Constat DOM : `<label>Subjects</label>` visible après les actions du formulaire, sur la préparation et le Studio. `AiIdeaType.php:47` déclare une collection ; `_subjects.html.twig:3` rend ses entrées mais la collection peut rester non marquée comme rendue, puis `studio.html.twig:40` appelle `form_end`.

Proposition : gérer explicitement le rendu de la collection après ses champs utiles, sans enlever le CSRF ni les champs requis. Critères : plus de libellé orphelin ; ajout/retrait de sujets et rendu sans JS restent fonctionnels. Pas de correction faite dans cet audit.

### UI4 — P2 — Lisibilité de la navigation des petits écrans à améliorer

À 320 px, six onglets ont des libellés de 9 px (`workspace-responsive.css:190`), dont Publications et Connexions. Les surfaces restent d'environ 58 px de haut, et le menu textuel fournit une autre entrée. Aucun défaut de clic n'est observé, mais la lecture est exigeante.

Proposition : tester des intitulés plus courts ou une répartition des actions moins serrée, en conservant le menu accessible et sans nouvelle section imposée. Critères : six destinations identifiables, texte lisible, cibles suffisamment espacées, aucun contenu masqué à 320 px et au zoom. Ne pas interpréter la petite police seule comme une violation WCAG démontrée.

### UI5 — P2 — Le retour d'un utilisateur déjà inscrit mais déconnecté est peu visible sur la landing mobile

À 390 px, le lien « Se connecter » de la navigation principale est masqué avec cette navigation. Le seul lien direct encore rendu vers `/connexion` se trouve dans le footer, autour de 9 123 px dans l'état en pause observé. Le premier écran montre « Créer mon compte ». Le chemin via l'inscription peut exister, mais ne remplace pas une entrée de connexion évidente.

Proposition minimale : garder une entrée « Se connecter » dans le premier écran ou un menu mobile accessible, sans supprimer le CTA principal ni les animations. Critères : un visiteur déjà inscrit rejoint la connexion depuis le haut de page à 320/390 px, sans ouvrir d'abord l'inscription ni parcourir toute la landing. Ce lien doit fonctionner sans JavaScript.

## Accessibilité : échantillon et limites

Sur le profil : libellés de 13 px, aide de 12 px. La paire calculée de l'aide `rgb(163,163,179)` sur `rgb(13,13,19)` donne un contraste d'environ 7,79:1. C'est une paire satisfaisante, pas une validation de tous les textes ou états. Les dégradés, focus, champs invalides, menus, lecteurs et éditeur doivent faire l'objet d'un passage plus large avant une déclaration de conformité. Le seuil courant pour du texte normal est 4,5:1 selon le [W3C](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).

À ajouter à la recette d'acceptation du lot UI : clavier complet, erreurs de formulaire, zoom 200 %, lecture d'écran ciblée et reduced motion système. Tester un vrai téléphone, notamment sélection de fichier et clavier virtuel ; cette recette reste un navigateur desktop redimensionné.

## Vitesse : distinguer navigation et rédaction

Le lot précédent mesure les GET locaux et réduit les requêtes : Studio 9→4, série de huit épisodes 38→14, calendrier 16→9. Les médianes locales finales optimisées sont de l'ordre de 185–362 ms selon page dans cette comparaison précise ; voir [le protocole et ses limites](performance-recette-v1.md). Ces valeurs ne sont pas un SLA et ne sont pas remesurées comme nouvelle série de benchmarks ici.

Le build courant contient `app.js` 242 031 octets et `app.css` 216 998 octets bruts ; les contrôleurs et styles de plusieurs écrans partagent cette entrée (`assets/app.js`). Three.js est dans un chunk séparé de 704 889 octets et importé dynamiquement depuis `studio_controller.js:75`. Il serait donc inexact d'affirmer que le Studio privé charge nécessairement Three.js à chaque visite. Compression et cache doivent être vérifiés sur le futur serveur public ; la taille brute ne démontre pas un problème d'interaction.

Ordre conseillé : traiter d'abord la capacité/durée du rédacteur et les pages de série non bornées ; instrumenter ensuite le serveur cible et les interactions avant de découper les assets par écran. Préserver la landing et son chargement conditionnel ; ne pas ajouter de framework ni réécrire le CSS complet pour gagner de la vitesse sans mesure.

L'évaluation réelle du rédacteur du 27 octobre est consignée dans le rapport qualité distinct. Les appels texte peuvent prendre plusieurs minutes ici même lorsque la navigation fonctionne correctement. Le suivi asynchrone, la possibilité de quitter la page et les erreurs explicites restent essentiels.
