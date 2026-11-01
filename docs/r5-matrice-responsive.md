# R5.1 — matrice responsive et défauts reproduits

1er novembre 2026. **R5.1 terminé comme étape de vérification ; grand R5 en cours, aucune correction livrée ici. Prochain point : R5.2**, navigation étroite et accès publics. Les autres défauts restent ouverts pour R5.3–R5.5 ; ce rapport ne clôture pas le grand R5.

## Méthode et périmètre

Sources applicatives identiques à `ac3bcac` (R4.4) ; HEAD documentaire pendant la recette `8d63450`. Export par Symfony/KernelBrowser authentifié avec propriétaire synthétique dans `shortflow_test`, transaction annulée en finally et clients HTTP applicatifs configurés pour refuser tout appel. **28 réponses exportées**, dont un POST ancien refusé en 422 ; zéro HTTP interdit. Vidéos, titres, erreurs, comptes et états sont synthétiques ; les montants affichés ne constituent pas une recette de tarification. Aucun média privé ou compte réel utilisé.

Les HTML et assets construits sont servis sur `127.0.0.1:8020`, **GET uniquement**. Les états de suivi, résultat de rédaction vide et forme d'onde sont simulés pour éviter toute lecture de l'application réelle. Le clip d'aperçu est un média synthétique local déjà disponible. Aucune soumission, inférence, programmation, export vidéo ou envoi social réel ne peut partir de ce serveur.

Playwright CLI local : 112 contrôles DOM/captures de base, puis 25 relevés landing, 12 compléments dialogue/paysage/WebGL et trois contrôles de reflow. [Résultats complets et empreintes des captures](fixtures/r5-responsive-20261101.json). Les captures restent locales dans `artifacts/r51/`, mais la matrice, les constats et leurs conditions sont versionnés. L'inspection visuelle a été ciblée sur les captures de défauts, le premier écran landing, l'éditeur paysage, son dialogue d'export et le rendu WebGL ; ne pas interpréter 112 contrôles comme une lecture humaine de chaque pixel des 112 images.

## Matrice principale

Chaque état ci-dessous est chargé à **320, 390, 768 et 1280 px**, hauteur 900 px, mouvement réduit émulé, polices chargées avant mesure/capture. Le scroll horizontal interne de timeline n'est pas assimilé à un débordement du document. La colonne concerne le document, pas la validation complète de l'écran.

| Écran / états | États exportés | Débordement document |
| --- | ---: | --- |
| Landing publique | 1 | Aucun à hauteur 900 ; chevauchement à d'autres hauteurs ci-dessous |
| Connexion / inscription | 2 | Aucun |
| Bibliothèque vide / remplie avec titre long | 2 | Aucun |
| Studio vide / préparation d'épisode enregistrée à deux plans | 2 | Aucun |
| Séries vide / liste remplie / dernière page / saisie refusée | 4 | Aucun |
| Calendrier vide / brouillon sans date | 2 | Aucun |
| Publications vide / envoi en attente / erreur | 3 | Aucun |
| Connexions sans accès enregistré | 1 | Aucun |
| Montage liste vide / remplie / éditeur brouillon / export actif / erreur | 5 | **Export actif : oui aux quatre largeurs** |
| Historique de création vide / rempli | 2 | Aucun |
| Profil | 1 | Aucun |
| Génération active / à vérifier après erreur | 2 | **Oui aux quatre largeurs** |
| Préparateur de publication avec brouillon | 1 | Aucun |

Total : **28 états × 4 largeurs = 112 cas**. **100 sans débordement de document, 12 avec débordement confirmé**. Les 112 cas n'ont relevé ni erreur JS ni réponse HTTP en échec dans cette recette. Cela ne suffit pas à certifier la lisibilité, le clavier ou l'accessibilité de tous leurs composants. La préparation d'épisode est une préparation enregistrée avant son GET, pas un rejeu de toutes les anciennes versions de schéma ; leur compatibilité métier reste couverte par les tests historiques R3/R4.

## Défauts à corriger avant clôture R5

| ID | Défaut reproduit | Impact et suite |
| --- | --- | --- |
| R5-D1 | Landing 390 × 780 : téléphone jusqu'à y≈637, bloc texte/CTA dès y≈574, soit environ 63 px de recouvrement. Visible sur capture. Aussi reproduit à 320 × 740 (environ 4 px), en paysage 844 × 390 et 740 × 320. | Lecture gênée. Revoir le flux et l'espace du téléphone/CTA en R5.5, en conservant animations et fallback. Recontrôle public en R6.1. |
| R5-D2 | Lien Se connecter du header invisible à 320/390/768 et aux deux paysages. Aucun menu public mobile de remplacement ; CTA inscription visible. Lien de connexion visible à 1280. | Retour des utilisateurs inscrits difficile. Correction dans **R5.2**, avec connexion/inscription accessibles au clavier et sans JS. |
| R5-D3 | Titre valide contenant un segment sans espace sur génération active/à vérifier et export actif : document de 1 268 à 2 090 px pour des viewports 320–1280. | Défilement horizontal et action de retour d'export repoussée hors écran à 768/1280. Corriger les contraintes de largeur/retour à la ligne en R5.5 ; conserver le titre et le texte humain. |
| R5-D4 | Barre basse à 320 : six destinations, police de 9 px, lecture serrée ; menu natif fournit une autre entrée. | Amélioration de lisibilité dans **R5.2**. La petite police seule n'est pas présentée comme une violation WCAG démontrée. |

Captures décisives : `landing-390x780-normal.png`, `landing-390x780-reduced.png`, `landing-390x780-no-js.png`, `generation-active-320.png` et `edit-export-768.png`. Les grands titres débordants sont conservés dans les fixtures ; aucun masquage global de l'overflow ne doit servir à annoncer le défaut corrigé.

## États complémentaires

Landing : 320 × 740, 390 × 780, 768 × 900, 1280 × 900, 844 × 390 et 740 × 320, en mouvement normal, reduced motion système émulé et sans JS ; pause testée quand son bouton est disponible. Le chevauchement persiste en pause, en mouvement réduit et sans JS : il n'est donc pas attribué uniquement à l'animation. À 390 × 844 le premier écran ne reproduit pas le même recouvrement ; la hauteur est déterminante. Les rectangles de bounding box sont une alerte géométrique, confirmée visuellement sur le cas 390 × 780 ; le léger cas 320 ne prouve pas à lui seul une occlusion du texte.

Desktop : attente de l'état **webgl**, capture inspectée, pause vers **static** vérifiée. Deux avertissements WebGL `texImage3D ... FLIP_Y or PREMULTIPLY_ALPHA` apparaissent dans Chromium local, sans exception JS et avec rendu obtenu. Leur portée dépend du moteur/GPU : point à recontrôler en R5.4, sans conclure à un défaut fournisseur ou à une panne universelle. À 768 et en mobile, état **mobile** ; reduced motion mène à **static**.

Dialogue « Exporter le montage » ouvert sans confirmation à quatre largeurs : contenu et bouton Exporter ma vidéo visibles, dialogue de 282 px à 320, 352 px à 390 et 560 px à 768/1280 ; aucun débordement document. Fermeture testée. L'éditeur, la bibliothèque remplie et la saisie refusée sont aussi chargés en paysage 844 × 390 et 740 × 320, sans débordement document. Les zones internes de montage peuvent défiler volontairement ; la capture de l'éditeur paysage n'est pas une validation complète de toutes ses actions.

Zoom : simulation CSS 200 % de la landing, puis reflow à viewport CSS 640 × 450 avec facteur d'échelle 2 (équivalent géométrique d'un écran 1280 × 900 à 200 %) sur landing, éditeur et saisie refusée. **Ce n'est pas un test du zoom de l'interface du navigateur.** Le vrai zoom 200 %, lecteur d'écran, téléphone physique, safe areas et clavier virtuel restent à contrôler en R5.2/R5.3. Les dimensions et la limite exacte de cette émulation sont conservées dans le JSON.

## Reproductibilité, incidents et contrôles

Programmes locaux : `artifacts/r51/export.php`, `server.cjs`, `matrix-code.js`, `landing-code.js` et `extra-code.js`. Commandes exécutées :

```text
APP_ENV=test APP_DEBUG=0 php artifacts/r51/export.php
node artifacts/r51/server.cjs
npx --yes --package @playwright/cli playwright-cli -s=r51 run-code --filename artifacts/r51/matrix-code.js
npx --yes --package @playwright/cli playwright-cli -s=r51 run-code --filename artifacts/r51/landing-code.js
npx --yes --package @playwright/cli playwright-cli -s=r51 run-code --filename artifacts/r51/extra-code.js
```

L'export utilise la copie Linux isolée du projet dans edit-worker, pas le PHP 8.1 hôte. Premiers essais écartés : propriétaire détaché après les GET (rechargé comme référence managée), titre YouTube de fixture trop long (borné à la limite), état `failed` non reconnu pour une génération (remplacé par l'état métier `review`). Aucune garde ou source applicative modifiée. Chaque transaction d'essai est annulée ; les séquences de test consommées restent la limite habituelle du banc.

R5.1 ne modifie ni application ni tests : **pas de nouvelle suite complète PHP/frontend ni build**. Les derniers contrôles verts restent ceux de R4.4, **813 PHP / 9 437 assertions et 183 frontend**, historiques. Les nouveaux résultats sont ceux de l'export et du navigateur ci-dessus ; ne pas transformer les défauts ouverts en tests verts. JSON, empreintes des captures et liens de ce bilan vérifiés. Revue indépendante ciblée des sources/matrice sans accès DB ; défauts et limites consignés.

Pédagogie : un écran sans scroll horizontal peut tout de même superposer deux éléments absolus ; il faut varier aussi la hauteur et vérifier les pixels. Inversement, une timeline qui défile dans son propre panneau n'est pas un débordement global. Les longs mots testent la capacité d'un élément flex/grid à rétrécir ; ne pas tronquer les données pour contourner ce cas.

**R5.1 est vérifié, pas « tout responsive vert ».** Les quatre défauts ci-dessus restent ouverts et empêchent la clôture globale de R5 avant correction/recette. Prochain point exact **R5.2** ; R5.3/R5.4/R5.5 et revue globale encore à faire. Aucun appel réel, coût, activation, migration applicative, déploiement, publication ou push. Pas de redémarrage cache dev/worker ; serveur et navigateur de recette fermés à la fin.
