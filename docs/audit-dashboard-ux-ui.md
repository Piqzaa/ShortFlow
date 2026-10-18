# Audit UX/UI du dashboard — 18 octobre 2026

## Améliorations réalisées après l’audit

Le diagnostic ci-dessous conserve l’état de départ. La passe suivante, autorisée par le propriétaire, applique les priorités : menu mobile natif repliable, activité remontée, prochains départs et brouillons réels, signalement des créneaux manqués/bloqués, aide repliable, textes et cibles agrandis, définitions CSS du dashboard regroupées. Les comptes sociaux restent inchangés.

À 360 × 800, le haut des compteurs passe d’environ 795 à **299 px**, celui de la section À reprendre de 1 151 à **577 px** sur la fixture normale. Le menu mobile s’ouvre avec Entrée et ses huit liens mesurent 44 px de haut. Sur 1024 × 400, le menu latéral défile et rend le profil et la déconnexion accessibles.

Nouveau contrôle des états normal/vide/titres sans espace et compteurs élevés à 320/360/390/768/1024/1440/2560 px : **21 cas sans débordement horizontal**, aucun élément du contenu principal hors largeur. Console consultée sans erreur/avertissement. Aperçus Brouillons à 320 px : vidéos chargées, en pause initiale, hauteur 240 px et aucun débordement. Cette vérification ne réaudite pas intégralement tous les écrans du produit.

Vrais templates rendus avec objets synthétiques en mémoire, sans écriture DB ni appel social ; captures ignorées `artifacts/dashboard-improved-desktop.png` et `artifacts/dashboard-improved-mobile.png`. Les destinations privées restent vérifiées par les tests fonctionnels. Pédagogie et commandes dans studio.md, résultats techniques dans verification.md.

## Verdict

L’identité visuelle est cohérente et suffisamment reconnaissable pour continuer : noir/anthracite, violet, titres Space Grotesk, texte DM Sans, états d’attention ambre et succès vert. Une refonte graphique complète n’est pas justifiée par les observations.

Le principal problème est la hiérarchie mobile : la page se comporte encore comme une introduction au studio, alors qu’un utilisateur régulier attend son activité et ses prochains départs. Un défaut CSS de barre latérale sur petite hauteur a été reproduit puis corrigé dans cet audit.

**Bilan technique : un défaut CSS corrigé ; aucun autre débordement horizontal ou chevauchement de texte constaté dans les cas vérifiés. Ce résultat porte sur ce périmètre, pas sur une garantie absolue d’absence de bugs.**

## Périmètre et méthode

- État audité : commit de départ `9bde55b`, puis correction locale ciblée de la barre latérale.
- Dashboard `/studio`, navigation commune, cartes de compteurs, activité, états des réseaux, vidéos récentes et état vide. Les autres écrans du studio ne sont pas intégralement réaudités ici.
- Lecture de `templates/studio/index.html.twig`, `templates/studio/base.html.twig`, `_activity.html.twig`, des cartes bibliothèque, du contrôleur dashboard, des styles et contrôleurs Stimulus concernés.
- Skills frontend-review et ui-ux-pro-max utilisés comme grilles de lecture. Conservation de la charte existante ; aucune nouvelle palette, police ou dépendance introduite à partir des suggestions génériques de l’outil.
- L’onglet disponible n’est pas authentifié : rendu des vrais templates avec objets synthétiques en mémoire, sans écriture en base et sans appel social. Les chiffres des captures sont des données de test. Les liens privés sont inspectés mais les destinations après authentification ne sont pas toutes parcourues dans cet audit.
- États : vide, activité normale, titres longs sans espace, compteurs élevés ; contrôle complémentaire d’un email long : retour à la ligne confirmé, aucun défilement horizontal interne du menu.
- Largeurs vérifiées : 320, 360, 390, 768, 1024, 1440 et 2560 px. Cas supplémentaire 1024 × 400 pour les petites hauteurs ; contrôles autour des seuils 760 et 1100 px (largeurs rapportées : 760, 762, 1100 et 1102 px). Les dimensions sont celles du viewport de contrôle, sans prétendre simuler tous les appareils physiques.
- Mesures DOM : débordement de page et interne, géométrie des sections, tailles des cibles et des textes. Contrastes calculés sur les couleurs CSS et fonds composités ; texte superposé aux vidéos exclu de ce calcul, qui ne constitue pas une certification automatique WCAG.
- Parcours clavier des 18 premiers contrôles, focus visible et lien d’évitement activé : la tabulation suivante arrive dans le contenu principal, sans repasser par le menu ; console inspectée.

## Points positifs à préserver

1. **Identité claire.** Palette, polices et arrondis donnent un studio sobre. Le violet est réservé à l’action principale et aux repères ; l’ambre distingue les éléments à traiter.
2. **Action principale identifiable.** Importer pour un compte vide, préparer une publication dès qu’une source existe. Les boutons secondaires disposent maintenant d’une surface visible.
3. **Compteurs compréhensibles.** Bibliothèque, envois actifs, éléments à traiter et publications réussies restent distingués. Le libellé indique que les publications réussies concernent les destinations, sans présenter ces résultats comme un nombre de vidéos sources.
4. **Priorité relative des problèmes.** Les éléments à reprendre précèdent les envois actifs dans le contenu. Le libellé complète la couleur : les états ne dépendent pas uniquement du rouge/vert.
5. **Résistance au contenu.** Aucun débordement horizontal de page ou interne observé sur les titres longs et les compteurs élevés aux largeurs contrôlées. Les cartes passent en deux colonnes sur les petits écrans.
6. **Base accessible.** HTML serveur, hiérarchie h1/h2/h3 cohérente, navigation nommée, page active annoncée et focus visible. Les 18 premiers éléments restent atteignables au clavier, sans piège constaté.
7. **Contraste des textes informatifs satisfaisant dans l’échantillon.** Sur le dashboard mobile normal, le plus faible ratio mesuré pour le texte informatif hors média est d’environ 6,72:1. Le seul résultat sous 4,5:1 est le séparateur décoratif `/` de l’en-tête, marqué aria-hidden.

## Problème CSS corrigé

### P1 — bas de la barre latérale inaccessible dans une fenêtre peu haute

- **Reproduction :** ouvrir le dashboard à 1024 × 400 px. La barre latérale sticky occupe 400 px, tandis que son contenu dépasse 560 px. Réseaux, Mon compte et la déconnexion descendent sous le viewport ; le panneau n’a pas de défilement propre (`overflow: visible`). Le défilement du contenu principal ne résout pas l’accès à ces éléments.
- **Cause :** hauteur liée au viewport et position sticky, sans gestion du contenu vertical excédentaire.
- **Correction appliquée :** dans `assets/styles/studio.css`, à partir de 761 px de large, `overflow-y: auto` et `overscroll-behavior-y: contain` sur `.studio-sidebar`. Le panneau mobile conserve son comportement normal.
- **Vérification :** défilement du panneau à la souris ; bouton de déconnexion revenu dans le viewport, vers y=329–365 sur 400 px de haut. Le défilement devient indépendant du contenu principal. Focus clavier et desktop normal recontrôlés.
- **Ce qu’il faut comprendre en CSS :** un élément sticky de hauteur viewport doit gérer son propre dépassement vertical. Le `minmax(0,1fr)` des colonnes traite un autre problème, celui du dépassement horizontal ; il ne suffit pas ici.

## Améliorations UX/UI à prioriser

| Priorité | Observation et impact | Proposition concrète | Fichiers principalement concernés |
| --- | --- | --- | --- |
| **P1 — usage mobile** | À 360 px, les compteurs commencent vers y=795 et la section à traiter vers y=1151. Sur un viewport de 800 px, presque tout le premier écran est consacré à navigation, introduction, tutoriel et raccourcis. | Remonter compteurs et urgences ; conserver une seule action principale compacte. Montrer le tutoriel de trois étapes surtout à l’arrivée ou sur demande. Regrouper les raccourcis Brouillons/Calendrier plutôt que les empiler avant l’activité. | `templates/studio/index.html.twig`, `assets/styles/studio.css` |
| **P2 — pilotage des programmations** | Le dashboard ne résume ni les programmations à venir ni les brouillons à reprendre : seuls deux raccourcis les exposent. Les quatre compteurs actuels lisent l’activité des envois existants. | Ajouter un bloc compact « Prochains départs » avec date/fuseau et état réel, plus un nombre de brouillons à reprendre. Utiliser les préparations existantes, sans transformer les programmations en jobs avant l’échéance. | `DashboardController`, dépôt des préparations et template dashboard |
| **P2 — lisibilité et confort tactile** | Dans l’échantillon mobile, 35 des 75 éléments portant du texte direct sont sous 12 px. Navigation autour de 10 px ; métadonnées souvent 10–11 px. Plusieurs liens de navigation mesurent 32–33 px de haut, des actions 40–41 px. | Viser 13–14 px pour navigation et actions courantes, 12 px minimum de confort pour les métadonnées utiles ; réserver le plus petit aux détails secondaires. Viser des cibles de 44 px pour les actions mobiles. Il s’agit d’un objectif de confort, pas d’une déclaration automatique de non-conformité WCAG pour toute cible sous 44 px. | Styles studio et publication |
| **P2 — maintenance CSS** | Les composants ont reçu plusieurs couches de règles. Exemple réel : largeur de l’aperçu brouillon définie à 110 px puis 160 px au même niveau ; variantes mobiles 90 px puis 160 px. Le design system décrit principalement la landing et diverge des tailles du studio. | Regrouper chaque composant et ses états/media queries, supprimer les valeurs devenues inactives, définir quelques tokens du studio : tailles de texte, contrôles, espacements et surfaces. Les media queries répétées ne sont pas, à elles seules, un bug. | `studio.css`, `publication.css`, `docs/design-system.md` |
| **P3 — cohérence du menu** | Deux entrées du menu desktop ont un symbole, les autres n’en ont pas. L’action Mon compte est également répétée dans la barre latérale et l’en-tête. | Choisir une navigation entièrement textuelle ou une famille SVG cohérente pour toutes les entrées. Réévaluer l’utilité du deuxième accès Mon compte sur desktop. | `templates/studio/base.html.twig` |
| **P3 — prise en main** | L’état vide propose correctement l’import, mais répète le tutoriel générique et les raccourcis vides. Un débutant ne sait pas encore précisément quand les réseaux doivent être configurés. | Prévoir une courte aide progressive et non bloquante : importer, préparer, connecter les destinations avant confirmation. Préserver la préparation hors connexion et éviter de forcer une configuration sociale pour créer un brouillon. | Dashboard et textes d’état vide |

## Architecture visuelle conseillée pour la prochaine passe

Pour l’utilisateur régulier : **titre compact + action principale → compteurs/urgences → prochains départs et brouillons → réseaux et vidéos récentes**. Sur mobile, afficher les informations utiles rapidement, sans reproduire une page d’introduction avant chaque consultation.

Conserver la palette, les polices, la distinction primaire/secondaire et les cartes actuelles. Le besoin est une hiérarchie plus opérationnelle et une typographie plus confortable, pas un nouveau thème. Aucune des réorganisations de ce tableau n’est implémentée dans cet audit.

## Vérifications exécutées et limites

| Contrôle | Résultat |
| --- | --- |
| 7 largeurs × 2 états de contenu | 14 combinaisons, aucun débordement horizontal de page constaté |
| Débordement interne sur mobile normal et titres/compteurs extrêmes | Aucun constaté |
| État vide mobile | Import proposé, aucun lecteur absent/cassé rendu, pas de débordement |
| Petite hauteur 1024 × 400 | Défaut reproduit puis correction vérifiée |
| Clavier | 18 premiers éléments atteignables avec focus visible ; aucun piège constaté |
| Console de la page de contrôle | Aucune erreur/avertissement capturé |
| npm run check | Code 0 |
| npm run test:ui | 6 tests réussis |
| npm run build après correction | Code 0 ; avertissements habituels sur médias statiques et chunk Three.js |
| Analyse PostCSS des 3 feuilles du studio | Parse réussi ; studio 212 règles/16,3 ko, publication 270 règles/20,6 ko, bibliothèque 110 règles/7,6 ko. Comptages indicatifs, pas une preuve de CSS inutilisé |

Pas de nouveau test PHP ni migration nécessaire pour la correction CSS. La suite globale n’a pas été répétée. Pas de test lecteur d’écran réel, d’audit exhaustif WCAG, de navigateur Safari/Firefox physique ou de soumission sociale. Le fallback HTML et les branches reduced-motion ont été revus dans le code, sans annoncer un scénario navigateur non exécuté.

Les captures et mesures locales sont dans `artifacts/dashboard-audit-*` (ignorées par Git). Les scripts/pages/vidéo temporaires sont retirés après les contrôles. Aucun accès social, aucune donnée dev modifiée, aucun commit automatique.

## Références de mesure

- [W3C — contraste minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) : 4,5:1 pour le texte courant, 3:1 pour le grand texte, avec les exceptions décrites par le critère.
- [W3C — reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html) : contrôle du contenu dans une largeur équivalente à 320 px CSS ; les essais de largeur ne remplacent pas tous les essais de zoom.
- [W3C — taille minimale des cibles](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) : critère AA à 24 px, avec exceptions dont l’espacement ; l’objectif de confort à 44 px recommandé ici est distinct.
