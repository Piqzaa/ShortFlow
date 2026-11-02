# Résonance : cartes invisibles en descente — 3 novembre 2026

Correctif livré après R6.2, sur signalement du propriétaire **sur PC** : les cartes de « 02 / La résonance » n’apparaissent qu’en remontant. La recette navigateur reproduit une opacité desktop figée à 0,15 lors de la descente, même après pause de 1,3 s alors que le progrès du tween atteint 0,896 ; en remontant elle revient à 1. En largeur mobile, le retard est mesuré à 0,8, sans disparition complète démontrée.

## Cause établie

Le HTML charge l’entrée `/build/app.js?v=…`. Le chunk construit de `screen-composer` importait les helpers de dessin depuis **`../app.js` sans query**. Les deux URL sont distinctes pour le navigateur : la même entrée démarrant Stimulus pouvait s’exécuter deux fois. Preuve réseau avant correctif : les deux URL chargées malgré un seul tag script. Deux applications/jeux d’animations pilotaient les mêmes cartes : 26 triggers et 12 observers recensés, au lieu de 13/6 après correction.

Le deuxième `gsap.from` pouvait prendre pour valeurs finales celles déjà modifiées par le premier. Cela explique le résultat dépendant du sens de défilement ; la vidéo automatique et le chargement différé ne sont pas la cause de l’opacité/transform mesurées.

## Correction et pédagogie

- `vite.config.js` place les modules purs `horizon-sequence.js` et `edit-sequence.js` dans un chunk partagé `landing-sequences`. Le compositeur importe ce chunk sans réimporter le démarrage applicatif. Le cache-busting du HTML reste en place ; pas de singleton cachant un double enregistrement des contrôleurs.
- Les cartes restent opaques et dans leur cadre. Leur légère entrée verticale est jouée une seule fois, `immediateRender:false`, sans opacity/échelle/décalage horizontal/rotation 3D pilotés par le scrub. Les rotations CSS desktop au repos et les previews vidéo sont conservées.
- Le contrôleur conserve des générations monotones lors d’une reconnexion et la pause choisie par l’utilisateur. Chargement unique par génération ; callbacks/erreurs et résultats d’await périmés ignorés ; les observers capturent leur propre instance afin qu’un ancien callback ne déconnecte pas son remplaçant. Ces gardes protègent le cycle asynchrone ; elles ne sont pas présentées comme le substitut au vrai correctif de bundle.
- La commande Pause fixe appartient à la hero. Des scènes suivantes pouvaient intercepter son clic car elles étaient dans le même niveau de contexte. Le contexte de la hero passe à z-index 3 **sur desktop seulement**, sans modification géométrique ni navigation. Mobile garde son bouton dans le flux.

## Preuves finales

- **195 tests frontend verts**, check et build verts. Cinq nouveaux cas de cycle de vie (reconnexion, notifications répétées, observateur périmé, rejet tardif, ancien callback) et un test de graphe exécutant un vrai build en mémoire. Ce dernier interdit qu’un chunk charge l’entrée applicative sous une autre URL.
- Aucun service PHP, schéma, formulaire métier ou média changé ; **pas de suite PHP ni DB rejouée** pour ce correctif. Les compteurs R6.2 restent historiques.
- Même descente/remontée réelle à 1280×800 et 393×852 : toutes les cartes à opacité 1 dans les deux sens. Captures avant/après inspectées ; contenu immédiatement lisible, présentation 3D desktop conservée. Le relevé « avant » retient la capture arrêtée desktop originale ; les captures directionnelles finales sont identifiées « après ».
- Initialisation : une seule entrée JS chargée ; 13 triggers uniques/6 observers avant passage, contre 26/12 auparavant. Les triggers once peuvent ensuite disparaître normalement ; pas d’attente de nombre constant après exécution.
- Accès direct `#distribution-title`, reflow PC→mobile→PC, pause/reprise, reduced motion/restauration, sans JS : opacité 1 et aucun débordement. Une seule URL d’entrée reste chargée après tous ces changements. La pause fonctionne par clic réel ; une fois la story quittée, le bouton est masqué.
- Un premier essai de clic a révélé le conflit de calques. Deux recontrôles naviguant vers la même ancre conservaient l’ancienne feuille CSS dans le document ; reprise sur document neuf et clics réussis. Le bilan final s’appuie sur `states-fresh.log`, pas sur les attentes expirées. Warnings WebGL déjà connus ; pas de garantie tous GPU.
- Revue indépendante en lecture seule : cause du bundle et dépendance exacte identifiées, correction de graphe, gardes asynchrones et calques examinés sans blocage de code ; aucun test/DB exécuté par le relecteur.

[Manifeste avant/après](fixtures/resonance-scroll-20261103.json), journaux locaux dans `artifacts/resonance`. Tests finaux : `frontend-verified.log`, `check-verified.log`, `build-delivery.log` ; mesures `after.log`, `initialization-after.log`, `states-fresh.log`, `control-exit.log`.

## Reprise

Sur PC, recharger la landing avec Ctrl+F5 puis descendre normalement vers Résonance, s’arrêter sur les cartes et remonter. Confirmation humaine du correctif encore attendue ; aucune nouvelle recette iPhone inventée. R6.2 reste livré, **prochaine tâche indépendante R6.3**. Aucun coût, appel fournisseur/rédacteur, génération IA, publication, activation, migration applicative, worker redémarré, déploiement ou push.