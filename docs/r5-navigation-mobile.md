# R5.2 — navigation mobile et accès publics corrigés

1er novembre 2026. **Corrections et recette sur PC livrées ; R5.2 reste ouvert pour sa recette sur téléphone réel.** Le propriétaire a confirmé ne disposer que de l'accès depuis ce PC. Aucun accès réseau ou tunnel n'est créé pour contourner cette limite. Prochain travail indépendant possible : **R5.3**, clavier/accessibilité ; l'essai matériel R5.2 reste requis avant clôture globale R5.

## Comportement livré

Sur la landing à 1000 px et moins, un **Menu natif** remplace le bouton d'inscription du header. Il contient les trois liens de section, **Se connecter** et **Créer mon compte**. Le CTA principal du premier écran reste présent. Au-dessus de 1000 px, navigation et CTA desktop restent inchangés. L'utilisateur déjà vérifié continue d'être redirigé vers son Studio par le contrôleur existant ; cette correction ne change pas la politique d'accès.

Le menu utilise `<details>/<summary>`, comme le Studio. Il s'ouvre avec Entrée et fonctionne sans JavaScript. Avec JS, le contrôleur `options` existant referme le menu au clic extérieur ou avec Échap ; Échap rend le focus au résumé. La liste est scrollable en paysage court, pour conserver toutes les destinations. Les liens sont de vrais liens Symfony ; aucune navigation n'exige un script.

Dans le Studio, les six destinations restent disponibles. La barre basse utilise **Agenda**, **Envois** et **Comptes** pour les libellés auparavant trop serrés ; les autres restent Vidéos, Studio et Séries. La police passe à **11 px**, y compris à 320 px, au lieu de 9 px. Les noms complets restent dans le menu textuel et dans les noms accessibles (`Agenda · Calendrier`, `Envois · Publications`, `Comptes · Connexions`). Ces noms incluent le texte visible pour la commande vocale. Les routes, l'état actif et Mon compte restent conservés.

Fichiers : `templates/landing/index.html.twig`, `templates/studio/shell.html.twig`, `assets/styles/experience.css`, `assets/styles/workspace-responsive.css`. Aucun service PHP ni contrôleur JS nouveau. Le calcul des durées, les formulaires, devis, consentements, animations et références ne sont pas modifiés. R5-D2 et R5-D4 sont corrigés dans le périmètre PC ; **R5-D1 (superposition téléphone/texte) et R5-D3 (titres longs) restent ouverts pour R5.5**.

## Contrôles automatisés

Copie Linux isolée dans edit-worker, `shortflow_test` vérifiée, aucune suite DB concurrente. Les tests Account et ValidationQueue couvrent les vrais GET/retours de connexion/inscription, toutes les destinations et les noms accessibles de la barre basse.

- `php scripts/check.php` : **814 tests PHP / 9 471 assertions**, schémas dev/test, migrations test à jour, lints YAML/Twig/conteneur, PHPStan et style verts. Log local `artifacts/r52-full.log`.
- `npm run check` : réussi.
- `npm run test:ui` : **183 tests réussis**.
- `npm run build` : réussi. Avertissements connus de chunk Three.js et chemins médias/polices au runtime.
- `git diff --check` : réussi.

La première commande de style passait deux chemins à une commande qui en attend un : arrêt avant les tests. Les deux fichiers ont ensuite été traités séparément, puis le contrôle complet a réussi. Aucune exclusion ni affaiblissement de garde.

## Recette navigateur sur PC

[Résultats, exports et empreintes](fixtures/r5-navigation-20261101.json). Export Symfony authentifié de **29 réponses synthétiques**, rollback en finally et zéro HTTP fournisseur, après la fin des tests DB. Serveur de consultation GET uniquement sur `127.0.0.1:8021` ; résultat de rédaction et suivi simulés, aucun formulaire de coût/génération/publication soumis.

Playwright CLI : **16 contextes**, avec et sans JavaScript à 320 × 740, 390 × 780, 768 × 900, 1000 × 900, 1001 × 900, 1280 × 900, 844 × 390 et 740 × 320.

- Menu public : connexion et inscription rejoignent leurs vrais chemins exportés ; Entrée ouvre, Tab atteint le premier lien, Échap referme et rend le focus avec JS. Sans JS, ouverture et parcours natifs fonctionnent. À 1000/1001, la bascule mobile/desktop est vérifiée.
- Visiteur : les deux accès sont conservés. Utilisateur vérifié : GET `/` redirige toujours vers le Studio (export suivi jusqu'au tableau de bord). Un premier oracle de recette attendait à tort une landing authentifiée ; il a été corrigé pour vérifier la redirection existante, sans changement d'application.
- Menu Studio : six destinations plus Mon compte, état actif conservé, ouverture clavier et Échap vérifiés. À 320, les cibles de la barre basse font environ **51 × 58 px**, police **11 px**.
- Aucun débordement de document ni exception JS dans les 16 contextes retenus. Ce résultat ne signifie pas que les autres défauts de la landing sont corrigés.
- Paysage court : panneau public dans le viewport, hauteur maximale de 220 px à 740 × 320, liens conservés avec défilement ; clic extérieur referme le menu.
- Hauteur réduite 390 × 400 sur le préparateur : champ de texte focalisé puis centré au-dessus de la barre fixe (champ y≈144–195, barre dès y≈329). **Ce n'est pas un clavier virtuel réel.**

Captures inspectées : `artifacts/r52/public-320x740-js.png`, `studio-320x740-js.png`, `public-740x320-native.png`. Le JSON conserve les empreintes des 19 captures. Les warnings WebGL du contexte desktop initial sont ceux déjà relevés en R5.1 ; la recette finale des menus utilise le mouvement réduit, sans modifier les animations.

## Téléphone réel : condition non remplie

R5.2 exige dans la roadmap : **« Vérifier barres fixes, safe areas et clavier virtuel sur téléphone réel. »** L'émulation de viewport ne prouve pas le comportement de Safari/iOS ou du clavier Android ; les règles existantes `env(safe-area-inset-bottom)` et les marges de défilement restent conservées, sans certification matérielle.

Le propriétaire a répondu **« Non, seulement depuis ce PC »** concernant un accès privé depuis son téléphone. **La case R5.2 reste donc ouverte**, malgré la livraison des corrections. À la disponibilité d'un accès privé autorisé, vérifier portrait/paysage, navigation près de l'encoche et de la zone d'accueil, champ de formulaire avec clavier ouvert, accès aux actions après défilement et fermeture du menu. Aucun projet publiquement exposé ni nouveau service demandé dans ce lot.

Les contrôles R5.3 peuvent avancer indépendamment sur ce PC. La recette matérielle manquante doit rester dans la grille de clôture R5.5 ; elle ne disparaît pas avec cette possibilité de travail parallèle.

## Relecture et pédagogie

Revue indépendante en lecture seule : destinations, libellé visible inclus dans le nom accessible, fallback natif, priorités CSS et conservation du desktop/animations examinés ; aucun blocage de code identifié. Aucune suite DB exécutée par le relecteur. Les limites matérielles restent explicites.

Le template Twig choisit les routes existantes ; CSS détermine l'affichage au breakpoint ; le navigateur gère le menu natif et Stimulus ajoute seulement la fermeture confortable. Le menu reste donc utilisable si le bundle JS ne se charge pas. Le libellé court d'un lien ne change pas sa route ni les droits du serveur.

Essai manuel sans coût : ouvrir Menu en haut de la landing, rejoindre connexion puis inscription, revenir ; dans le Studio à 320 px, vérifier les six entrées et Mon compte dans Menu, puis Échap. Ne soumettre aucune génération ou publication pour cet essai.

Commit fonctionnel local `e6628f8 fix: restore mobile account access and readable studio navigation`. Serveur et navigateur de recette fermés en fin de travail. Aucun service PHP changé, cache dev/worker redémarré, migration applicative, inférence réelle, coût, activation, publication, déploiement ou push. Grand R5 non clôturé.
