# R6 — revue globale et clôture

**3 novembre 2026 : R6.1–R6.6 terminés sur le périmètre local contrôlé.** Revue indépendante de l’ensemble du lot, puis relecture des preuves finales : aucun blocage applicatif identifié. Prochain point exact : **R7.1**, recette produit avec API simulées. Aucune ouverture publique ou activation n’est induite par cette clôture.

## Grille de sortie

| Étape | Résultat vérifié | Preuves et limites |
| --- | --- | --- |
| R6.1 | Textes actuels, bêta et illustrations explicites, accès compte conservé | [Bilan](r6-landing-mentions.md), recette publique actuelle ; animations et repli statique conservés |
| R6.2 | Import, IA et série expliqués ; coût fournisseur distinct ; confirmation génération et publication séparées | [Bilan et démonstration](r6-parcours-couts-demo.md) ; véritable import et brouillon test, relecture capturée et non publication réussie ; pas de nouvel essai média en R6.6 |
| R6.3 | Guide fonctionnement/coûts/réseaux/premiers pas, liens natifs | [Bilan](r6-guide-fonctionnement.md), sommaire et liens rejoués ; aucune offre provisoire vendue |
| R6.4 | Email lisible/copiable, sujets mailto, guide depuis Contact, aide depuis Mon compte | [Bilan](r6-aide-support.md), aucun délai de réponse annoncé ; Contact absent sidebar/menu, six destinations métier conservées |
| R6.5 | Métadonnées, partage texte, canonical fixe, sitemap et indexation volontaire | [Bilan](r6-seo-partage.md), vydflow.com retenu, indexation toujours désactivée ; achat/DNS et aperçu externe non certifiés |
| R6.6 | Recette responsive/clavier/sans JS, liens, revue globale et preuves | [Manifeste final](fixtures/r66-cloture-20261103.json), résultats ci-dessous |

La porte de sortie R6 est satisfaite : parcours et coût expliqués en HTML, aide accessible et promesse cohérente avec la bêta. Cette conclusion ne prétend pas démontrer la compréhension de vrais testeurs (R7.2), la qualité audiovisuelle fournisseur (R7.4–R7.5), la production email (R8.4), la capacité d’hébergement ou une conformité exhaustive.

## Recette actuelle

- **36 contextes publics Chromium** : accueil, fonctionnement, Contact × 320/390/768/1280 px × normal/reduced motion/JavaScript réellement désactivé. HTTP 200, largeur document égale au viewport, zéro erreur JavaScript, ressource HTTP en échec ou sortie externe constatée par ce banc. Aucun ID dupliqué ni ancre interne absente. Canonical présent dans les 36 réponses finales ; noindex maintenu.
- Téléphone et CTA mobile : espacement positif, environ 23–25 px à 320/390. Trois cartes Résonance à opacité 1 après descente/remontée dans les douze contextes landing. Captures landing/guide/contact à 390/1280 et compte mobile inspectées ; pas de défaut visuel bloquant identifié. Les captures full-page ne prouvent pas à elles seules toutes les positions d’un calque WebGL fixe.
- **12 vues membre sans JS**, sur les trois réponses Symfony exportées : Mon compte, Contact et guide × quatre largeurs. Zéro débordement ; compte et Contact conservent les six destinations Studio, Contact absent sidebar/menu. **Huit navigations** compte → Contact → guide réussies. Le guide connecté reste une page de guide et n’invente pas une barre Studio.
- Clavier à 390 px, avec et sans JS : premier Tab = lien d’évitement sur les trois pages, Entrée focalise `main#contenu`. Sommaire Réseaux accessible par Tab/Entrée, ancre visible à environ 64 px du haut après le défilement natif ; menu mobile ouvrable au clavier, connexion et inscription atteintes avec leurs vrais champs. Échap ferme le menu avec JS ; repli natif conservé sans JS. Aucun formulaire soumis et aucun mailto activé dans cette recette navigateur.
- **Neuf destinations publiques GET** vérifiées : accueil, fonctionnement, Contact, connexion, inscription, confidentialité, informations légales, robots et sitemap : HTTP 200 et noindex. Les autres liens du sommaire sont contrôlés par présence de leurs cibles ; les adresses mailto sont du texte/liens, sans envoi réel.

Le premier passage navigateur utilisait un ancien conteneur de services compilé en dev : la variable Twig `public_seo` n’y existait pas encore, donc les balises canoniques étaient absentes. **Cache dev actualisé avec `cache:clear --env=dev --no-debug`**, sans redémarrage worker ; les 36 relevés finaux ont été entièrement rejoués et sont ceux du manifeste. Aucun défaut de sources corrigé pour ce décalage de cache.

Deux corrections du banc uniquement : parenthèse JavaScript et retour des résultats plutôt qu’un console.log non retourné par le CLI. L’ancre mesurée après 250 ms était encore en défilement ; mesure finale après 1 400 ms. Ces essais préparatoires ne sont pas présentés comme des défauts du produit.

## Tests et isolation

- **19 PHP ciblés / 228 assertions**, AccountTest + tests SEO fonctionnels/unitaires, verts. Base test validée avant lancement ; schéma test en phase avec les mappings. Aucun test DB exécuté en parallèle d’un autre test DB ou de l’export.
- **195 frontend**, syntaxe JS (`npm run check`) et build Vite réussis. Avertissements de taille Three.js et références `/fonts/editor/*.ttf` non résolues au build : pas d’échec ; aucun défaut HTTP de ressource sur les pages publiques contrôlées. Cela ne valide pas tous les écrans de montage.
- **120 Twig**, **19 YAML**, conteneur, PHPStan et syntaxe/style du script d’export verts. Pas de nouvelle suite globale PHP ; le résultat 825 / 9 596 antérieur reste historique et n’est pas un résultat R6.6.
- `scripts/export-r6-review.php` génère trois réponses avec propriétaire synthétique uniquement dans `shortflow_test`. Les cinq clients fournisseur/rédacteur sont remplacés par des mocks qui refusent tout HTTP ; rollback en `finally`, onze compteurs égaux avant/après, zéro appel fournisseur. Aucune vidéo importée pour cette étape, aucun fichier privé ajouté, aucune migration applicative.
- Les réponses connectées sont relues dans Chromium par interception locale, sans créer de session authentifiée de développement. Le banc refuse les mutations et les sorties hors loopback. Empreintes des exports et captures consignées ; fichiers HTML/captures et journaux détaillés restent sous `artifacts/r66` et `output/playwright`, ignorés par Git.

## Revue indépendante et limites conservées

Un agent indépendant en lecture seule a confronté toutes les étapes R6 aux sources et bilans, puis vérifié le manifeste, l’exporteur et les trois empreintes d’export. Aucun blocage identifié. Il n’a pas lui-même exécuté les tests PHP ni Chromium ; les observations de recette ci-dessus viennent du travail principal.

**Retour humain Résonance PC après Ctrl+F5 encore attendu**, comme dans [le bilan du correctif](resonance-scroll-correctif.md). La nouvelle recette confirme la lisibilité automatisée des cartes ; elle ne vaut pas cette confirmation du propriétaire. Ce suivi reste explicite et ne bloque pas la clôture technique bornée R6, aucun défaut bloquant restant n’étant démontré. Aucun nouvel essai matériel iPhone, lecteur d’écran, préférence Windows réelle, Safari ou tous GPU.

Indexation désactivée, boîte email confirmée par le propriétaire mais transport local Mailpit distinct de l’envoi de production. Pas d’email réel, appel fournisseur réel, coût, publication sociale, activation, achat de domaine, déploiement ou push. Aucun worker redémarré ; seul le cache web dev a été actualisé.

## Pédagogie et reprise

Cette étape n’ajoute pas de fonctionnalité applicative. Le nouveau script d’export utilise le Kernel Symfony et BrowserKit pour obtenir les véritables templates connectés, puis annule les écritures test. Son rôle est de rendre le rendu contrôlable dans le navigateur sans conserver de compte fictif ni exposer de données réelles.

Pour reproduire l’export : `docker compose exec -T php php scripts/export-r6-review.php`. Pour les tests ciblés : `docker compose exec -T php php vendor/bin/phpunit tests/Functional/AccountTest.php tests/Functional/PublicSeoTest.php tests/Unit/PublicSeoTest.php`. Ne pas exécuter simultanément ces deux commandes sur la même base test. Les scripts temporaires Chromium et leurs résultats se trouvent dans `output/playwright/r66-*` ; modes réels de contexte JavaScript activé/désactivé et reduced motion.

Point Symfony à retenir : modifier une définition de service ou un global Twig peut exiger un renouvellement du cache du **bon environnement**. Un test vert en `test` ne garantit pas que le cache `dev` du serveur ouvert utilise déjà cette configuration. Ici la recette a permis de vérifier cette différence puis de confirmer le résultat servi.

**Suite : R7.1 seulement**, parcours produit complets avec API simulées. R7.2 nécessite des testeurs autorisés ; les essais vidéo et sociaux réels restent des portes distinctes avec accord explicite et budget propre.
