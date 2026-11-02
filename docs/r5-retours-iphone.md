# R5 — retours iPhone 14 Pro, 2 novembre 2026

Le propriétaire rapporte un rendu responsive correct sur iPhone 14 Pro, avec trois réserves : flèche nord-est rendue comme un emoji bleu (capture), hero figée sur sa première image et cartes « Un seul élan, trois horizons » trop hautes. Ce retour ne documente pas séparément tous les cas clavier virtuel/safe areas ; R5.2 reste ouvert pour la recette matérielle et la revalidation de ces corrections.

## Corrections

- Icône partagée `templates/icons/_arrow_up_right.html.twig` : SVG décoratif currentColor, sans focus ni nom accessible parasite. Utilisée dans les six-destinations de navigation Studio pour Envois/Publications, les actions de création, le compte, les liens Contact et les actions publiques de landing. Les noms et destinations sont conservés. Les glyphes des autres écrans historiques ne sont pas tous refondus dans ce lot.
- Hero mobile : son ancienne vidéo WebM automatique est remplacée dans le téléphone de repli par un canvas de démonstration. Cinq images existantes uniquement, avec les mêmes textes et fondus de `drawFilm` ; zoom léger et flottement de 5 px. Aucun nouvel appel IA ou média généré. Le canvas dessine au maximum 15 fois par seconde ; son ticker est retiré hors vue/onglet masqué, à la pause, au changement de préférence et à la déconnexion. Les chargements tardifs sont invalidés. Les réductions de mouvement et économie de données restent statiques ; en cas d’image manquante, la première image de repli reste visible.
- La lecture ne dépend plus de `video.play()`. La batterie jaune sur la capture suggère le mode économie d’énergie ; son rôle dans le refus réel de lecture n’est pas établi par une mesure. Ce correctif n’est pas une validation matérielle de ce mode.
- Distribution à <=1000 px : carte en deux colonnes, visuel vertical complet 9:16 à gauche et textes à droite. Trois cartes empilées, sans carrousel ni recadrage. Animation d’entrée limitée à 12 px, sans inclinaison mobile pour éviter un recouvrement du texte suivant. Desktop conserve les cartes et le rendu 3D.

## Contrôles exécutés

- **24 PHP ciblés / 336 assertions**, AccountTest, ValidationQueueTest et AccessibilityTest sur shortflow_test ; **119 Twig** valides. Aucun service PHP ni schéma changé. Pas de nouvelle suite complète : **825 / 9 596** reste le passage précédent.
- **189 frontend**, check et build verts. Les trois nouveaux cas protègent l’arrêt hors vue/onglet, le nettoyage, la fréquence de dessin, l’invalidation d’images tardives et le repli si image absente. Premier passage : 186 verts/3 erreurs de banc VM (remplacement du premier import dynamique seulement) ; banc corrigé avec replaceAll, attentes conservées et deux passages finaux 189 verts.
- Recette Chromium avec viewport tactile 393×852, mouvement normal et `HTMLMediaElement.play` volontairement refusé : image/texte du canvas changent après 3,6 s, transformation du téléphone change, seulement cinq images hero chargées ; pause retrouve le visuel statique, reduced motion reste statique. Cela ne remplace pas l’iPhone physique.
- Cartes : 208 px de haut à 320 de large, **202 px à 393**, 313 px à 768/1000 ; ratio CSS 9:16, aucun débordement document à ces quatre largeurs. Captures hero/cartes/Studio inspectées. Six entrées basses, flèche Envois SVG et deux flèches d’en-tête SVG vérifiées à 393 px.
- Desktop 1280×900 atteint `webgl`. Zéro erreur JS dans le contexte mobile ; warnings WebGL desktop déjà connus, aucune garantie tous GPU revendiquée.
- **23 exports actualisés après build**, rollback/11 compteurs et zéro HTTP fournisseur/inférence. La recette LAN reste synthétique GET uniquement. [Manifeste](fixtures/r5-iphone-20261102.json).
- Revue indépendante en lecture seule : aucun blocage de code identifié ; revalidation réelle iPhone nécessaire, particulièrement animation et mode économie d’énergie.

## Reprise propriétaire

Recharger sur l’iPhone les liens déjà accessibles : `/qa/landing` puis `/qa/creation` sur le serveur local port 8025. Vérifier flèches monochromes, changement des images/textes après quelques secondes, léger mouvement et cartes compactes. Réduction des animations activée : une hero statique reste attendue. Conserver la recette clavier virtuel, portrait/paysage et zones de la barre basse avant clôture R5.2/R5.5. Ne pas soumettre les formulaires synthétiques.

Aucun coût, appel réel, activation, publication, migration applicative, redémarrage worker, déploiement ou push. Le serveur LAN existant est conservé ; aucune nouvelle règle de pare-feu ni exposition supplémentaire.
## Validation propriétaire des trois correctifs

Après demande de rechargement sur son iPhone 14 Pro, le propriétaire répond « Ok oui la c’est supp ». Les trois corrections (flèches, hero, cartes) sont acceptées sur ce périmètre. Aucun état système de batterie/réduction des animations n’a été mesuré : pas de certification générale du mode économie d’énergie. Les essais ciblés clavier virtuel, portrait/paysage et zones basses restent à confirmer avant clôture R5.2/R5.5. Aucun code changé ni test rejoué pour cette validation ; compteurs précédents historiques. Commit applicatif `30f6aba`, aucun push.